<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Cache;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Query;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Services\InstituteService;

/**
 * Institutes — ported from the original InstituteController (apiResource 'institutes').
 *
 * JSON contracts kept from the original:
 *   GET    /api/institutes          -> paginator JSON (search name/director/certificate)
 *   POST   /api/institutes          -> {status:'success'}
 *   GET    /api/institutes/{id}     -> {instituteById:{...}}
 *   GET    /api/instituteById/{centerId} -> {instituteById:[...]} active institutes of a center
 *   PUT    /api/institutes/{id}     -> {status:'success'}   (POST twin for multipart)
 *   DELETE /api/institutes/{id}     -> {status:'success'}
 *   POST   /api/institutes/changestatus/{id}/{sta} -> {status:'success'}
 *   PUT    /api/institutevalidity/{id}             -> {status:'success'}
 *
 * Center users only see the institutes that belong to their own center.
 */
final class InstituteController extends Controller
{
    /** Columns a create/update may set (mass-assignment whitelist). */
    private const FIELDS = [
        'center_id', 'name', 'director_name', 'phone', 'email', 'website',
        'division_id', 'district_id', 'upazila_id', 'union_id', 'address',
        'certificate_no', 'established_date', 'membership_type', 'member_type', 'active',
    ];

    /** GET /api/institutes — paginated list with the owning center's name. */
    public function index(Request $request): array
    {
        $hasSyncedDisplayName = Cache::remember('schema:centers.display_name', 300, static function (): bool {
            return (int) Database::scalar(
                'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                ['centers', 'display_name']
            ) > 0;
        });
        $centerName = $hasSyncedDisplayName
            ? "COALESCE(NULLIF(centers.display_name, ''), NULLIF(users.name, ''), CASE WHEN institutes.center_id IS NOT NULL THEN CONCAT('Center #', institutes.center_id) ELSE 'Unassigned' END)"
            : "COALESCE(NULLIF(users.name, ''), CASE WHEN institutes.center_id IS NOT NULL THEN CONCAT('Center #', institutes.center_id) ELSE 'Unassigned' END)";
        $q = (new Query('institutes'))
            ->leftJoin('centers', 'centers.id', '=', 'institutes.center_id')
            ->leftJoin('users', 'users.id', '=', 'centers.user_id')
            ->select('institutes.*')
            // The synced center label is independent of the linked account so
            // it still displays when historical centers have a missing user.
            ->selectRaw($centerName . ' AS center_name')
            ->orderBy('institutes.id', 'desc');

        $this->scopeToOwnCenter($q);

        // Optional center filter, used by the /centers delete guard link:
        // /institutes?center_id=N (the paginator preserves it across pages).
        $centerId = (int) $request->input('center_id', 0);
        if ($centerId > 0) {
            $q->where('institutes.center_id', $centerId);
        }

        $term = trim((string) $request->input('search', ''));
        if ($term !== '') {
            $like = '%' . $term . '%';
            $q->whereRaw(
                '(`institutes`.`name` LIKE ? OR `institutes`.`director_name` LIKE ? OR `institutes`.`certificate_no` LIKE ?)',
                [$like, $like, $like]
            );
        }

        // The filters above only reference institutes columns. Counting the
        // total through the center and user display joins adds work without
        // affecting the result, so keep those joins only for the page rows.
        return $q->paginate(
            max(1, (int) $request->input('per_page', 20)),
            null,
            '/api/institutes',
            $q->withoutJoins()
        );
    }

    /** GET /api/institutes/{id} — single institute (edit form / detail). */
    public function show(Request $request): array
    {
        $id = (int) $request->params['id'];
        Auth::assertInstituteAccess($id); // RLS/BOLA
        return ['instituteById' => $this->findOrFail($id)];
    }

    /**
     * GET /api/instituteById/{centerId} — active institutes that belong to a
     * center. Ported from original InstituteController::instituteById(), which
     * (despite the singular name) returns a LIST under the `instituteById` key.
     * Distinct from show(), which returns one institute by institute id.
     */
    public function instituteById(Request $request): array
    {
        $centerId = (int) $request->params['id'];
        Auth::assertCenterAccess($centerId); // RLS/BOLA: {id} is a CENTER id
        $rows = (new Query('institutes'))
            ->where('center_id', (int) $request->params['id'])
            ->where('active', 1)
            ->orderBy('name', 'asc')
            ->get();

        return ['instituteById' => $rows];
    }

    /** POST /api/institutes */
    public function store(Request $request): array
    {
        $data = $this->validated($request);
        Auth::assertCenterAccess((int) $data['center_id']); // RLS/BOLA
        $row = $this->pick($data);
        $row['created_at'] = date('Y-m-d H:i:s');
        $row['updated_at'] = date('Y-m-d H:i:s');
        Database::insert('institutes', $row);
        Cache::forget('filter:active_institutes');

        return ['status' => 'success'];
    }

    /** PUT (or POST twin) /api/institutes/{id} */
    public function update(Request $request): array
    {
        $id = (int) $request->params['id'];
        Auth::assertInstituteAccess($id); // RLS/BOLA
        $this->findOrFail($id);
        $data = $this->validated($request);
        if (isset($data['center_id']) && $data['center_id'] !== null && $data['center_id'] !== '') {
            Auth::assertCenterAccess((int) $data['center_id']); // must not move center
        }
        $row = $this->pick($data);
        $row['updated_at'] = date('Y-m-d H:i:s');
        (new Query('institutes'))->where('id', $id)->update($row);
        Cache::forget('filter:active_institutes');

        return ['status' => 'success'];
    }

    /** DELETE /api/institutes/{id} */
    public function destroy(Request $request): array
    {
        $id = (int) $request->params['id'];
        Auth::assertInstituteAccess($id); // RLS/BOLA
        $this->findOrFail($id);
        (new Query('institutes'))->where('id', $id)->delete();
        Cache::forget('filter:active_institutes');

        return ['status' => 'success'];
    }

    /** POST /api/institutes/changestatus/{id}/{sta} — sta is 0 or 1. */
    public function changestatus(Request $request): array
    {
        $id = (int) $request->params['id'];
        $sta = (int) $request->params['sta'];
        Auth::assertInstituteAccess($id); // RLS/BOLA
        $this->findOrFail($id);
        (new Query('institutes'))->where('id', $id)->update(['active' => $sta === 1 ? 1 : 0]);
        Cache::forget('filter:active_institutes');

        return ['status' => 'success'];
    }

    /**
     * PUT /api/institutevalidity/{id} — original InstituteController::updateValidity().
     * Requires both dates and stamps the acting admin (UserId under
     * `validity_user_id`). Columns are DATE, so the original's +6h is a no-op here.
     */
    public function updateValidity(Request $request): array
    {
        $id = (int) $request->params['id'];
        Auth::assertInstituteAccess($id); // RLS/BOLA
        $this->findOrFail($id);
        $data = Validator::validate($request, [
            'last_renewal_date' => 'required|string|max:10',
            'validity_date' => 'required|string|max:10',
        ]);
        (new Query('institutes'))->where('id', $id)->update([
            'last_renewal_date' => $data['last_renewal_date'],
            'validity_date' => $data['validity_date'],
            'validity_user_id' => Auth::id(),
        ]);

        return ['status' => 'success'];
    }

    /**
     * Total institutes. GET /api/totalInstitute
     * Center users (type 4) only count institutes under their own centers
     * (original behaviour).
     */
    public function totalInstitute(): array
    {
        if (!Auth::isAdmin()) {
            $userId = Auth::id();
            $total = (int) (Database::selectOne(
                'SELECT COUNT(*) AS c FROM `institutes` i
                   JOIN `centers` c ON c.`id` = i.`center_id`
                  WHERE c.`user_id` = ?',
                [$userId]
            )['c'] ?? 0);
        } else {
            $total = (int) (Database::selectOne('SELECT COUNT(*) AS c FROM `institutes`')['c'] ?? 0);
        }

        return ['totalinstitutes' => $total];
    }

    /**
     * Printable membership registration card.
     * GET /api/institute-registration/{id} — original instituteregistrationpdf()
     * (public) streamed a PDF; the clone renders printable HTML like certificates.
     */
    public function instituteregistrationpdf(Request $request): string
    {
        // Public read-back endpoint for a newly self-registered institute's own
        // card — rate-limited per IP so it can't be used to enumerate other
        // institutes' director / contact PII.
        RateLimiter::hit('institute-registration-pdf', 30, 60);

        $institute = (new InstituteService())->registrationCard((int) $request->params['id']);

        return View::render('institutes.registration_card', [
            'institute' => $institute,
        ]);
    }

    /**
     * Public self-registration form.
     * GET /membership-institute-registration — original InstituteController::institutecreate()
     * rendered the public registration Blade page (no auth).
     */
    public function pagePublicCreate(Request $request): string
    {
        $divisions = (new Query('divisions'))->orderBy('name', 'asc')->get();

        $html = View::render('institutes.public_form', [
            'title' => 'New Institute Registration',
            'divisions' => $divisions,
        ], 'layouts.guest');

        // Public page: release the session lock after the CSRF token is embedded
        // so concurrent visitors aren't serialized (see ResultPortalController).
        Session::close();

        return $html;
    }

    /**
     * Public self-registration submit.
     * POST /api/ins-store — original InstituteController::store2().
     *
     * No auth: this is the public membership form. Duplicate name+phone+email
     * returns 401 {status:'errors'} exactly like the original. Logo is required,
     * principal photo optional; both are stored through InstituteService.
     */
    public function store2(Request $request): array
    {
        // Public form: per-IP limit so bots can't spam the institutes table.
        RateLimiter::hit('ins-store', 10, 3600);

        $data = Validator::validate($request, [
            'name' => 'required|string|max:255',
            'director_name' => 'required|string|max:255',
            'phone' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'website' => 'nullable|string|max:255',
            'division_id' => 'required|integer',
            'district_id' => 'required|integer',
            'upazila_id' => 'required|integer',
            'address' => 'required|string|max:255',
            'zip' => 'required|string|max:255',
            'established_date' => 'required|date_format:Y-m-d',
            'total_students' => 'required|integer',
            'total_teachers' => 'required|integer',
            'membership_type' => 'required|in:1,2',
            'member_type' => 'required|in:1,2',
            'type' => 'required|in:1,2,3',
            'recognized' => 'required|in:1,2',
            'institution_run' => 'required|in:1,2,3',
            'monthly_income' => 'nullable|string|max:255',
            'monthly_expense' => 'nullable|string|max:255',
            'note' => 'nullable|string',
            'certificate_no' => 'nullable|string|max:255',
        ]);

        // Duplicate guard: same institute already registered.
        $dup = Database::selectOne(
            'SELECT `id` FROM `institutes` WHERE `phone` = ? AND `email` = ? AND `name` = ? LIMIT 1',
            [$data['phone'], $data['email'], $data['name']]
        );
        if ($dup !== null) {
            Response::json(['status' => 'errors'], 401);
        }

        if (!$request->hasFile('logo')) {
            Response::validationError(['logo' => ['The institute logo field is required.']]);
        }

        $svc = new InstituteService();
        $logo = $svc->saveUpload($request->file('logo'), InstituteService::LOGO_DIR, 'logo');
        $principal = $svc->saveUpload($request->file('principal_photo'), InstituteService::PRINCIPAL_DIR, 'principal_photo');

        // New members get no certificate number; old members carry their own.
        $certificate = ((string) $data['member_type'] === '1') ? null : ($data['certificate_no'] ?? null);

        $id = Database::insert('institutes', [
            'center_id' => null,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'website' => $data['website'] ?? null,
            'division_id' => (int) $data['division_id'],
            'district_id' => (int) $data['district_id'],
            'upazila_id' => (int) $data['upazila_id'],
            'director_name' => $data['director_name'],
            'address' => $data['address'],
            'zip' => $data['zip'],
            'country' => 1,
            // Established date taken verbatim (the original added an extra day).
            'established_date' => $data['established_date'],
            'recognized' => (int) $data['recognized'],
            'total_students' => (int) $data['total_students'],
            'total_teachers' => (int) $data['total_teachers'],
            'type' => (int) $data['type'],
            'monthly_income' => $data['monthly_income'] ?? null,
            'monthly_expense' => $data['monthly_expense'] ?? null,
            'membership_type' => (int) $data['membership_type'],
            'member_type' => (int) $data['member_type'],
            'certificate_no' => $certificate,
            'note' => $data['note'] ?? null,
            'institution_run' => $data['institution_run'],
            'principal_photo' => $principal,
            'logo' => $logo,
            'active' => 0,
            'user_id' => Auth::id() ?? 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return ['status' => 'success', 'id' => $id];
    }

    // ---------------------------------------------------------------
    // Pages
    // ---------------------------------------------------------------

    /** GET /institutes — list page. */
    public function pageIndex(Request $request): string
    {
        return View::render('institutes.index', [
            'title' => 'Manage Institute',
        ], 'layouts.dashboard');
    }

    /** GET /institute-registration — create form. */
    public function pageCreate(Request $request): string
    {
        return View::render('institutes.form', [
            'title' => 'Add Institute',
            'mode' => 'create',
            'instituteId' => 0,
        ], 'layouts.dashboard');
    }

    /** GET /edit-institute/{id} — edit form (data loads from GET /api/institutes/{id}). */
    public function pageEdit(Request $request): string
    {
        $id = (int) $request->params['id'];
        Auth::assertInstituteAccess($id); // RLS/BOLA
        $this->findOrFail($id);

        return View::render('institutes.form', [
            'title' => 'Edit Institute',
            'mode' => 'edit',
            'instituteId' => $id,
        ], 'layouts.dashboard');
    }

    /**
     * GET /validityinstitute/{id} — "Set Institute Validity" form.
     * Original SPA route /validityinstitute/:instituteId (Vue institutes/validity.vue);
     * data loads from GET /api/institutes/{id}, save via PUT /api/institutevalidity/{id}.
     */
    public function pageValidity(Request $request): string
    {
        $id = (int) $request->params['id'];
        Auth::assertInstituteAccess($id); // RLS/BOLA
        $this->findOrFail($id);

        return View::render('institutes.validity', [
            'title' => 'Set Institute Validity',
            'instituteId' => $id,
        ], 'layouts.dashboard');
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /** @return array<string,mixed> */
    private function findOrFail(int $id): array
    {
        $row = (new Query('institutes'))->where('id', $id)->first();
        if ($row === null) {
            throw new HttpException(404, 'Record not found');
        }
        return $row;
    }

    private function scopeToOwnCenter(Query $q): void
    {
        $centerId = Auth::centerId();
        if ($centerId !== null && !Auth::isAdmin()) {
            $q->where('institutes.center_id', (int) $centerId);
        }
    }

    private function validated(Request $request): array
    {
        return Validator::validate($request, [
            'center_id' => 'required|integer',
            'name' => 'required|string|max:255',
            'director_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|string|max:255',
            'division_id' => 'nullable|integer',
            'district_id' => 'nullable|integer',
            'upazila_id' => 'nullable|integer',
            'union_id' => 'nullable|integer',
            'address' => 'nullable|string|max:255',
            'certificate_no' => 'nullable|string|max:255',
            'established_date' => 'nullable|string|max:10',
            'membership_type' => 'nullable|integer',
            'member_type' => 'nullable|integer',
            'active' => 'nullable|in:0,1',
        ]);
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    private function pick(array $data): array
    {
        $row = [];
        foreach (self::FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $row[$field] = $data[$field] === '' ? null : $data[$field];
            }
        }
        return $row;
    }
}
