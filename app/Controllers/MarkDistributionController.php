<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Validator;
use App\Core\View;
use App\Models\MarkConfig;

/**
 * Mark distribution (full-marks per session) — ported from original
 * MarkDistributionController (routes/api.php: Route::apiResource('markdistributions')).
 *
 * URL + JSON contracts:
 *   GET    /api/markdistributions       -> raw Laravel paginator JSON (search on `session`)
 *   POST   /api/markdistributions       -> {status:'success'}
 *   GET    /api/markdistributions/{id}  -> {markdistributionById:{...}}
 *   PUT    /api/markdistributions/{id}  -> {status:'success'}
 *   DELETE /api/markdistributions/{id}  -> {status:'success'}
 *   Pages  /mark-distributions, /createMarkDistribution, /editMarkDistribution/{id}
 *
 * Original bugs fixed while porting:
 *   - update() validated `unique:marks,session` without the ignore-id, so
 *     saving a row rejected its own session (here: unique:marks,session,{id}).
 *   - store() forgot user_id (added, like the original store did) and
 *     destroy() fataled on a missing row.
 *   - `mark-distribution-delete` does not exist in the permissions table, so
 *     DELETE is gated with `mark-distribution-edit` instead.
 */
final class MarkDistributionController extends Controller
{
    // ---------------------------------------------------------------
    // API
    // ---------------------------------------------------------------

    /** GET /api/markdistributions — paginated list (original returned the paginator verbatim). */
    public function index(Request $request): array
    {
        $q = MarkConfig::query()->orderBy('session', 'desc');

        $search = $request->input('search');
        if ($search !== null && $search !== '') {
            $q->where('session', 'LIKE', '%' . $search . '%');
        }

        return $q->paginate((int) $request->input('per_page', 20), null, '/api/markdistributions');
    }

    /** POST /api/markdistributions — create (original store()). */
    public function store(Request $request): array
    {
        $data = $this->validated($request);

        MarkConfig::create([
            'session' => (int) $data['session'],
            'total' => (int) $data['total'],
            'bangla' => (int) $data['bangla'],
            'english' => (int) $data['english'],
            'math' => (int) $data['math'],
            'user_id' => Auth::id(),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return ['status' => 'success'];
    }

    /** GET /api/markdistributions/{id} — original show(). */
    public function show(Request $request): array
    {
        $row = MarkConfig::find((int) $request->params['id']);
        if ($row === null) {
            throw new HttpException(404, 'Mark distribution not found');
        }

        return ['markdistributionById' => $row];
    }

    /** PUT /api/markdistributions/{id} — original update() with the ignore-id unique fix. */
    public function update(Request $request): array
    {
        $id = (int) $request->params['id'];
        if (MarkConfig::find($id) === null) {
            throw new HttpException(404, 'Mark distribution not found');
        }

        $data = $this->validated($request, $id);

        MarkConfig::updateRow($id, [
            'session' => (int) $data['session'],
            'total' => (int) $data['total'],
            'bangla' => (int) $data['bangla'],
            'english' => (int) $data['english'],
            'math' => (int) $data['math'],
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return ['status' => 'success'];
    }

    /** DELETE /api/markdistributions/{id} — original destroy() (404 instead of fatal on null). */
    public function destroy(Request $request): array
    {
        $id = (int) $request->params['id'];
        if (MarkConfig::find($id) === null) {
            throw new HttpException(404, 'Mark distribution not found');
        }

        MarkConfig::deleteRow($id);

        return ['status' => 'success'];
    }

    // ---------------------------------------------------------------
    // Pages
    // ---------------------------------------------------------------

    /** GET /mark-distributions — list page. */
    public function pageIndex(Request $request): string
    {
        return View::render('markdistributions.index', [
            'title' => 'Mark Distribution',
        ], 'layouts.dashboard');
    }

    /** GET /createMarkDistribution — create form (original: markdistributions/create.vue). */
    public function pageCreate(Request $request): string
    {
        return View::render('markdistributions.create', [
            'title' => 'Add Mark Distribution',
        ], 'layouts.dashboard');
    }

    /** GET /editMarkDistribution/{id} — edit form, data loads from GET /api/markdistributions/{id}. */
    public function pageEdit(Request $request): string
    {
        $id = (int) $request->params['id'];
        if (MarkConfig::find($id) === null) {
            throw new HttpException(404, 'Mark distribution not found');
        }

        return View::render('markdistributions.edit', [
            'title' => 'Edit Mark Distribution',
            'markDistributionId' => $id,
        ], 'layouts.dashboard');
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Original rules: session|unique + total/bangla/english/math required.
     * Added numeric checks so garbage cannot reach MySQL strict mode, and the
     * ignore-id on update (the original compared against its own row).
     *
     * @return array{session:int,total:int,bangla:int,english:int,math:int}
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $unique = $ignoreId === null
            ? 'required|unique:marks,session'
            : 'required|unique:marks,session,' . $ignoreId;

        return Validator::validate($request, [
            'session' => $unique,
            'total' => 'required|numeric',
            'bangla' => 'required|numeric',
            'english' => 'required|numeric',
            'math' => 'required|numeric',
        ]);
    }
}
