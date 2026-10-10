<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Cache;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Query;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Core\View;
use App\Models\Center;
use App\Models\District;
use App\Models\Upazila;

/**
 * Exam centers (ported from exam/app/Http/Controllers/CenterController.php).
 *
 * A center's login/display data (name, email, phone) lives on the linked
 * `users` row (type 4, role id 4) — creating a center inserts BOTH rows in one
 * transaction, and the center's name in every list is users.name.
 */
class CenterController extends Controller
{
    private const MODEL_TYPE = 'App\Models\User';

    /** Upload targets + resize boxes (original: Intervention Image). */
    private const UPLOAD_FIELDS = [
        'authority_photo' => ['dir' => 'images/authorities', 'w' => 150, 'h' => 150],
        'authority_signature' => ['dir' => 'images/authority_signatures', 'w' => 100, 'h' => 30],
    ];

    /** GET /api/centers — paginated list, original returned the raw Laravel paginator. */
    public function index(Request $request): array
    {
        $this->requirePermission('center-list');
        $query = (new Query('users'))
            ->leftJoin('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->leftJoin('centers', 'users.id', '=', 'centers.user_id')
            ->leftJoin('divisions', 'divisions.id', '=', 'centers.division_id')
            ->leftJoin('districts', 'districts.id', '=', 'centers.district_id')
            ->leftJoin('upozilas', 'upozilas.id', '=', 'centers.upazila_id')
            ->select(
                'users.name',
                'users.email',
                'users.phone',
                'centers.id',
                'centers.authority_person',
                'centers.institute_name',
                'centers.code',
                'centers.active',
                'divisions.name AS division',
                'districts.name AS district',
                'upozilas.name AS upazila'
            )
            ->whereRaw('`model_has_roles`.`role_id` = ?', [4])
            ->orderByRaw('`users`.`id` DESC');

        $term = trim((string) $request->input('search', ''));
        if ($term !== '') {
            $status = match (strtolower($term)) {
                'approved' => 1,
                'pending' => 0,
                default => null,
            };
            if ($status !== null) {
                $query->whereRaw('`centers`.`active` = ?', [$status]);
            } else {
                $like = '%' . $term . '%';
                $query->whereRaw(
                    '(`users`.`name` LIKE ? OR `centers`.`authority_person` LIKE ?'
                    . ' OR `divisions`.`name` LIKE ? OR `districts`.`name` LIKE ?'
                    . ' OR `upozilas`.`name` LIKE ? OR `users`.`phone` LIKE ?'
                    . ' OR `centers`.`code` LIKE ? OR `users`.`email` LIKE ?)',
                    array_fill(0, 8, $like)
                );
            }
        }

        return $query->paginate(max(1, (int) $request->input('per_page', 20)));
    }

    /**
     * GET /api/center — active-center dropdown used by other modules
     * (original: GET /api/center → index2, returned {centerList:[...]}).
     */
    public function index2(Request $request): array
    {
        $me = Auth::user();
        if ((int) $me['type'] === 4) {
            $rows = (new Query('users'))
                ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                ->join('centers', 'users.id', '=', 'centers.user_id')
                ->select('users.name', 'centers.id')
                ->whereRaw('`model_has_roles`.`role_id` IN (4,10) AND `users`.`id` = ? AND `centers`.`active` = 1', [(int) $me['id']])
                ->orderByRaw('`users`.`name` ASC')
                ->get();
        } else {
            $rows = (new Query('users'))
                ->leftJoin('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                ->leftJoin('centers', 'users.id', '=', 'centers.user_id')
                ->leftJoin('divisions', 'divisions.id', '=', 'centers.division_id')
                ->leftJoin('districts', 'districts.id', '=', 'centers.district_id')
                ->select(
                    'users.name',
                    'users.email',
                    'users.phone',
                    'centers.id',
                    'centers.authority_person',
                    'centers.code',
                    'centers.active',
                    'divisions.name AS division',
                    'districts.name AS district'
                )
                ->whereRaw('`model_has_roles`.`role_id` IN (4,10) AND `centers`.`active` = 1')
                ->orderByRaw('`users`.`name` ASC')
                ->get();
        }

        return ['centerList' => $rows];
    }

    /** GET /api/centers/{id} — original shape: {centerById, districts, upazilas}. */
    public function show(Request $request): array
    {
        $this->requirePermission('center-list');
        $id = (int) $request->params['id'];
        $centerById = (new Query('users'))
            ->join('centers', 'users.id', '=', 'centers.user_id')
            ->select('users.*', 'centers.*')
            ->whereRaw('`centers`.`id` = ?', [$id])
            ->first();
        if ($centerById === null) {
            throw new HttpException(404, 'Record not found');
        }
        unset($centerById['password']); // never expose the hash (original leaked it)

        return [
            'centerById' => $centerById,
            'districts' => District::where('division_id', $centerById['division_id'])->get(),
            'upazilas' => Upazila::where('district_id', $centerById['district_id'])->get(),
        ];
    }

    /** POST /api/centers — user (type 4 + role 4) + center row in ONE transaction. */
    public function store(Request $request): array
    {
        $this->requirePermission('center-create');
        $data = Validator::validate($request, [
            'name' => 'required',
            'institute_name' => 'required',
            'authority_person' => 'required',
            'code' => 'required',
            'phone' => 'required',
            'division_id' => 'required|integer',
            'district_id' => 'required|integer',
            'upazila_id' => 'required|integer',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|same:confirmpassword',
            'website' => 'nullable|string|max:255',
            'note' => 'nullable|string',
            'active' => 'nullable|in:0,1',
        ], [
            'division_id.required' => 'The division field is required',
            'district_id.required' => 'The district code field is required',
            'upazila_id.required' => 'The upazila name field is required',
        ]);
        $this->validateUpload($request, 'authority_photo');
        $this->validateUpload($request, 'authority_signature');

        $now = date('Y-m-d H:i:s');
        $photo = $this->saveUpload($request, 'authority_photo');
        $signature = $this->saveUpload($request, 'authority_signature');

        try {
            Database::transaction(function () use ($data, $photo, $signature, $now) {
                $userId = Database::insert('users', [
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'password' => password_hash((string) $data['password'], PASSWORD_DEFAULT),
                    'type' => 4,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                Database::insert('model_has_roles', [
                    'role_id' => 4,
                    'model_type' => 'App\Models\User',
                    'model_id' => $userId,
                ]);
                Database::insert('centers', [
                    'user_id' => $userId,
                    'website' => $data['website'],
                    'division_id' => (int) $data['division_id'],
                    'district_id' => (int) $data['district_id'],
                    'upazila_id' => (int) $data['upazila_id'],
                    'authority_person' => $data['authority_person'],
                    'institute_name' => $data['institute_name'],
                    'code' => $data['code'],
                    'active' => (int) ($data['active'] ?? 0),
                    'note' => $data['note'],
                    'authority_photo' => $photo,
                    'authority_signature' => $signature,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
        } catch (\Throwable $e) {
            $this->dropUpload('authority_photo', $photo);
            $this->dropUpload('authority_signature', $signature);
            throw $e;
        }

        $msg = 'Dear ' . $data['authority_person'] . ', Congratulations from BKABD. From now on you will fulfill all the duties of this center with devotion, for any consultation regarding BKABD please contact this number 01711168094. Thanks';
        send_sms((string) $data['phone'], $msg);

        return ['status' => 'success'];
    }

    /** PUT /api/centers/{id} (original apiResource) + POST twin for multipart forms. */
    public function update(Request $request): array
    {
        $this->requirePermission('center-edit');
        $id = (int) $request->params['id'];
        $center = Center::find($id);
        if ($center === null) {
            throw new HttpException(404, 'Record not found');
        }
        $userId = (int) $center['user_id'];

        $data = Validator::validate($request, [
            'name' => 'required',
            'institute_name' => 'required',
            'authority_person' => 'required',
            'code' => 'required',
            'phone' => 'required',
            'division_id' => 'required|integer',
            'district_id' => 'required|integer',
            'upazila_id' => 'required|integer',
            'email' => 'required|email|unique:users,email,' . $userId,
            'password' => 'nullable|same:confirmpassword',
            'website' => 'nullable|string|max:255',
            'note' => 'nullable|string',
        ], [
            'division_id.required' => 'The division field is required',
            'district_id.required' => 'The district code field is required',
            'upazila_id.required' => 'The upazila name field is required',
        ]);
        $this->validateUpload($request, 'authority_photo');
        $this->validateUpload($request, 'authority_signature');

        $now = date('Y-m-d H:i:s');
        $photo = $this->saveUpload($request, 'authority_photo');
        $signature = $this->saveUpload($request, 'authority_signature');

        try {
            Database::transaction(function () use ($data, $center, $id, $userId, $photo, $signature, $now) {
                $userRow = [
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'updated_at' => $now,
                ];
                if (($data['password'] ?? '') !== '') {
                    $userRow['password'] = password_hash((string) $data['password'], PASSWORD_DEFAULT);
                }
                // Query builder, not Database::update(): that helper mixes named SET
                // params with the positional WHERE params here and PDO throws HY093.
                (new Query('users'))->where('id', $userId)->update($userRow);

                $centerRow = [
                    'website' => $data['website'],
                    'division_id' => (int) $data['division_id'],
                    'district_id' => (int) $data['district_id'],
                    'upazila_id' => (int) $data['upazila_id'],
                    'authority_person' => $data['authority_person'],
                    'institute_name' => $data['institute_name'],
                    'code' => $data['code'],
                    'note' => $data['note'],
                    'updated_at' => $now,
                ];
                if ($photo !== null) {
                    $centerRow['authority_photo'] = $photo;
                }
                if ($signature !== null) {
                    $centerRow['authority_signature'] = $signature;
                }
                (new Query('centers'))->where('id', $id)->update($centerRow);
            });
        } catch (\Throwable $e) {
            $this->dropUpload('authority_photo', $photo);
            $this->dropUpload('authority_signature', $signature);
            throw $e;
        }

        // Rows point at the new files now — retire the previous ones.
        if ($photo !== null && !empty($center['authority_photo'])) {
            $this->dropUpload('authority_photo', $center['authority_photo']);
        }
        if ($signature !== null && !empty($center['authority_signature'])) {
            $this->dropUpload('authority_signature', $center['authority_signature']);
        }

        return ['status' => 'success'];
    }

    /** DELETE /api/centers/{id} — 406 when institutes still reference the center. */
    public function destroy(Request $request): array
    {
        $this->requirePermission('center-delete');
        $id = (int) $request->params['id'];
        $institutes = (new Query('institutes'))->where('center_id', $id)->count();
        if ($institutes > 0) {
            // Original guard (406 {status:'error'}); the extra keys are
            // additive so the existing front-end 406 branch still matches and
            // can tell the operator how many institutes block the delete.
            Response::json([
                'status'     => 'error',
                'message'    => $institutes . ' institute' . ($institutes === 1 ? '' : 's') . ' still registered in this center.',
                'institutes' => $institutes,
            ], 406);
        }
        $center = Center::find($id);
        if ($center === null) {
            throw new HttpException(404, 'Record not found');
        }

        Database::transaction(function () use ($center, $id) {
            $userId = (int) $center['user_id'];
            Database::delete('model_has_roles', 'model_id = ? AND model_type = ?', [$userId, 'App\Models\User']);
            Database::delete('users', 'id = ?', [$userId]);
            Database::delete('centers', 'id = ?', [$id]);
        });

        $this->dropUpload('authority_photo', $center['authority_photo'] ?? null);
        $this->dropUpload('authority_signature', $center['authority_signature'] ?? null);

        return ['status' => 'success'];
    }

    /**
     * POST /api/centers/changestatus/{id}/{sta}
     * Original was an UNAUTHENTICATED GET returning an empty body — now
     * auth + permission:center-status + POST (blueprint hard rules 3/4/6).
     */
    public function changestatus(Request $request): array
    {
        $this->requirePermission('center-status');
        $id = (int) $request->params['id'];
        $sta = (string) $request->params['sta'];
        if ($sta !== '0' && $sta !== '1') {
            // ValidationException lives in Validator.php (no file of its own) and
            // this action never calls Validator::validate() — load it first.
            class_exists(Validator::class);
            throw new ValidationException(['sta' => ['The selected status is invalid.']]);
        }
        if (Center::find($id) === null) {
            throw new HttpException(404, 'Record not found');
        }
        Center::updateRow($id, ['active' => (int) $sta]);
        Cache::forgetPrefix('center_id'); // Auth::centerId() memo may be stale

        return ['status' => 'success'];
    }

    // ---------------------------------------------------------------
    // Pages
    // ---------------------------------------------------------------

    /** GET /centers — list page (permission flags feed data-perm-* in the view). */
    public function pageIndex(Request $request): string
    {
        $this->requirePermission('center-list');

        return View::render('centers.index', [
            'title' => 'Manage Center',
            'pageScripts' => ['/assets/js/centers.js'],
            'perms' => $this->permissionMap(['center-list', 'center-create', 'center-edit', 'center-delete', 'center-status', 'institute-list']),
        ], 'layouts.dashboard');
    }

    /** GET /create-center — create page. */
    public function pageCreate(Request $request): string
    {
        $this->requirePermission('center-create');

        return View::render('centers.create', [
            'title' => 'Add Center',
            'pageScripts' => ['/assets/js/centers.js'],
        ], 'layouts.dashboard');
    }

    /** GET /edit-center/{id} — edit page (data loads from GET /api/centers/{id}). */
    public function pageEdit(Request $request): string
    {
        $this->requirePermission('center-edit');
        $id = (int) $request->params['id'];
        if (Center::find($id) === null) {
            throw new HttpException(404, 'Record not found');
        }

        return View::render('centers.edit', [
            'title' => 'Edit Center',
            'centerId' => $id,
            'pageScripts' => ['/assets/js/centers.js'],
        ], 'layouts.dashboard');
    }

    // ---------------------------------------------------------------
    // Permission gate (original route middleware: permission:center-*)
    // ---------------------------------------------------------------

    /**
     * Require at least one of the given permissions (Spatie permission:x|y
     * semantics), else 403 with the original message.
     *
     * Bound query on purpose: App\Core\Auth::permissions() compares model_type
     * as an inline SQL literal and MySQL strips the backslashes
     * ('App\Models\User' -> 'AppModelsUser'), so it never matches — same core
     * bug reported in routes/users.php / UserController::requirePermission().
     */
    private function requirePermission(string ...$permissions): void
    {
        if (Auth::id() === null) {
            throw new HttpException(401, 'Unauthenticated.');
        }
        if (!in_array(true, array_values($this->permissionMap($permissions)), true)) {
            throw new HttpException(403, 'This action is unauthorized.');
        }
    }

    /**
     * @param string[] $names
     * @return array<string,bool> permission name => granted
     */
    private function permissionMap(array $names): array
    {
        $names = array_values(array_unique($names));
        $userId = Auth::id();
        $map = array_fill_keys($names, false);
        if ($userId === null || $names === []) {
            return $map;
        }
        $marks = implode(',', array_fill(0, count($names), '?'));
        $rows = Database::select(
            "SELECT DISTINCT p.`name` AS name
               FROM `permissions` p
               JOIN `role_has_permissions` rp ON rp.`permission_id` = p.`id`
               JOIN `model_has_roles` mr ON mr.`role_id` = rp.`role_id`
              WHERE mr.`model_type` = ? AND mr.`model_id` = ?
                AND p.`name` IN ($marks)
             UNION
             SELECT DISTINCT p.`name` AS name
               FROM `permissions` p
               JOIN `model_has_permissions` mp ON mp.`permission_id` = p.`id`
              WHERE mp.`model_type` = ? AND mp.`model_id` = ?
                AND p.`name` IN ($marks)",
            array_merge([self::MODEL_TYPE, $userId], $names, [self::MODEL_TYPE, $userId], $names)
        );
        foreach (array_column($rows, 'name') as $granted) {
            $map[$granted] = true;
        }
        return $map;
    }

    // -----------------------------------------------------------------
    // Uploads
    // -----------------------------------------------------------------

    /**
     * Original validated photo/signature as required image|mimes|max:1024.
     * The image rules are kept, `required` dropped so JSON clients can call
     * the API without multipart (edit keeps the stored file when none sent).
     */
    private function validateUpload(Request $request, string $field): void
    {
        $file = $request->file($field);
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return;
        }
        $label = str_replace('_', ' ', $field);
        $errors = [];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "The $label failed to upload.";
        } elseif (@getimagesize($file['tmp_name']) === false) {
            $errors[] = "The $label must be an image.";
        } elseif (!in_array(strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION)), ['jpeg', 'png', 'jpg', 'gif'], true)) {
            $errors[] = "The $label must be a file of type: jpeg, png, jpg, gif.";
        } elseif ((int) ($file['size'] ?? 0) > 1024 * 1024) {
            $errors[] = "The $label may not be greater than 1024 kilobytes.";
        }
        if ($errors !== []) {
            throw new ValidationException([$field => $errors]);
        }
    }

    /** Move a validated upload into public/images/… and resize it. Returns filename or null. */
    private function saveUpload(Request $request, string $field): ?string
    {
        $file = $request->file($field);
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $name = time() . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
        $dir = BASE_PATH . '/public/' . self::UPLOAD_FIELDS[$field]['dir'];
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new HttpException(500, 'Could not store uploaded file');
        }
        $path = $dir . '/' . $name;
        if (!move_uploaded_file($file['tmp_name'], $path)) {
            throw new HttpException(500, 'Could not store uploaded file');
        }
        $this->resizeUpload($path, self::UPLOAD_FIELDS[$field]['w'], self::UPLOAD_FIELDS[$field]['h']);
        return $name;
    }

    /** Aspect-preserving fit into the given box (original: Image::make()->resize()). */
    private function resizeUpload(string $path, int $maxW, int $maxH): void
    {
        $info = @getimagesize($path);
        if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF], true)) {
            return;
        }
        [$w, $h, $type] = $info;
        $scale = min($maxW / $w, $maxH / $h, 1.0);
        if ($scale >= 1.0) {
            return;
        }
        $src = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            default => @imagecreatefromgif($path),
        };
        if ($src === false) {
            return;
        }
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefilledrectangle($dst, 0, 0, $nw, $nh, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);
        match ($type) {
            IMAGETYPE_JPEG => imagejpeg($dst, $path, 85),
            IMAGETYPE_PNG => imagepng($dst, $path),
            default => imagegif($dst, $path),
        };
        imagedestroy($dst);
    }

    private function dropUpload(string $field, ?string $file): void
    {
        if ($file === null || $file === '') {
            return;
        }
        @unlink(BASE_PATH . '/public/' . self::UPLOAD_FIELDS[$field]['dir'] . '/' . $file);
    }
}
