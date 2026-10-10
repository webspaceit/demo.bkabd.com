<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\User;

/**
 * User controller — ported from original UserController.
 *
 * The user pages read flat JSON: GET /api/userList -> {userList:[...]} and
 * GET /api/userById/{id} -> {userById:{...,roles:<role id>}}. The form's role
 * select is a Spatie role (model_has_roles), assigned here; `users.type`
 * (0 = staff/admin, 4 = center) is a separate axis.
 *
 * Passwords: password_hash() at create/update; never logged or returned.
 */
final class UserController extends Controller
{
    private const MODEL_USER = 'App\\Models\\User';

    /** GET /api/userList -> {userList:[{id,name,email,phone,type,role_name}]} */
    public function index(Request $request): array
    {
        if (!Auth::isAdmin()) {
            Response::json(['status' => 'errors'], 403);
        }

        $q = User::query()->select('id', 'name', 'email', 'phone', 'type', 'created_at');

        // Optional search by name/email
        $search = $request->input('search');
        if ($search) {
            $q->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Optional filter by type (0/1/2/3 = staff, 4 = center)
        $type = $request->input('type');
        if ($type !== null && $type !== '') {
            $q->where('type', (int) $type);
        }

        $rows = $q->orderBy('id', 'asc')->get();

        // Attach each user's role name in one query (the list column shows it).
        if ($rows !== []) {
            $ids = array_map(static fn ($r) => (int) $r['id'], $rows);
            $place = implode(',', array_fill(0, count($ids), '?'));
            $roleRows = Database::select(
                "SELECT mr.`model_id`, r.`name` AS role_name
                   FROM `model_has_roles` mr
                   JOIN `roles` r ON r.`id` = mr.`role_id`
                  WHERE mr.`model_type` = ? AND mr.`model_id` IN ($place)",
                array_merge([self::MODEL_USER], $ids)
            );
            $byUser = [];
            foreach ($roleRows as $rr) {
                $byUser[(int) $rr['model_id']] = $rr['role_name'];
            }
            foreach ($rows as &$r) {
                $r['role_name'] = $byUser[(int) $r['id']] ?? '';
            }
            unset($r);
        }

        return ['userList' => $rows];
    }

    /** POST /api/userCreate (alias /api/userSave) */
    public function store(Request $request): array
    {
        if (!Auth::isAdmin()) {
            Response::json(['status' => 'errors'], 403);
        }

        $data = Validator::validate($request, [
            'name'            => 'required|string|max:255',
            'email'           => 'required|email|max:255|unique:users,email',
            'phone'           => 'nullable|string|max:20',
            'password'        => 'required|string|min:6',
            'confirmpassword' => 'nullable|same:password',
            'roles'           => 'nullable|integer',
            'type'            => 'nullable|in:0,1,2,3,4',
        ]);

        $id = Database::transaction(function () use ($data) {
            $id = User::create([
                'name'       => $data['name'],
                'email'      => $data['email'],
                'phone'      => $data['phone'] ?? '',
                'password'   => password_hash($data['password'], PASSWORD_DEFAULT),
                'type'       => (int) ($data['type'] ?? 0),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->syncRole($id, $data['roles'] ?? null);
            return $id;
        });

        return $this->sendResponse(
            ['id' => $id, 'name' => $data['name'], 'email' => $data['email']],
            'User registered successfully.'
        );
    }

    /** GET /api/userById/{id} -> {userById:{...user, roles:<role id>}} */
    public function show(Request $request): array
    {
        $id = (int) $request->params['id'];
        $user = User::find($id);
        if ($user === null) {
            throw new HttpException(404, 'User not found');
        }

        unset($user['password']);

        $role = Database::selectOne(
            "SELECT mr.`role_id` FROM `model_has_roles` mr
              WHERE mr.`model_type` = ? AND mr.`model_id` = ? LIMIT 1",
            [self::MODEL_USER, $id]
        );
        $user['roles'] = $role ? (int) $role['role_id'] : '';

        return ['userById' => $user];
    }

    /** POST /api/userUpdate/{id} */
    public function update(Request $request): array
    {
        $id = (int) $request->params['id'];
        if (!Auth::isAdmin()) {
            Response::json(['status' => 'errors'], 403);
        }

        $user = User::find($id);
        if ($user === null) {
            throw new HttpException(404, 'User not found');
        }

        $data = Validator::validate($request, [
            'name'      => 'nullable|string|max:255',
            'email'     => 'nullable|email|max:255|unique:users,email,' . $id,
            'phone'     => 'nullable|string|max:20',
            'password'  => 'nullable|string|min:6',
            'cpassword' => 'nullable|same:password',
            'roles'     => 'nullable|integer',
            'type'      => 'nullable|in:0,1,2,3,4',
        ]);

        $merge = [
            'name'       => $data['name'] ?? $user['name'],
            'email'      => $data['email'] ?? $user['email'],
            'phone'      => $data['phone'] ?? $user['phone'],
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if (!empty($data['password'])) {
            $merge['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        if (isset($data['type']) && $data['type'] !== '' && $data['type'] !== null) {
            $merge['type'] = (int) $data['type'];
        }

        Database::transaction(function () use ($id, $merge, $data) {
            User::updateRow($id, $merge);
            if (array_key_exists('roles', $data) && $data['roles'] !== null && $data['roles'] !== '') {
                $this->syncRole($id, $data['roles']);
            }
        });

        return $this->sendResponse(
            ['id' => $id, 'name' => $merge['name'], 'email' => $merge['email']],
            'User updated successfully.'
        );
    }

    /** DELETE /api/userDelete/{id} */
    public function destroy(Request $request): array
    {
        $id = (int) $request->params['id'];
        if (!Auth::isAdmin()) {
            Response::json(['status' => 'errors'], 403);
        }

        $user = User::find($id);
        if ($user === null) {
            throw new HttpException(404, 'User not found');
        }

        // Also delete associated center if type was 4
        if ((int) $user['type'] === 4) {
            Database::delete('centers', 'user_id = ?', [$id]);
        }

        Database::delete('personal_access_tokens', 'tokenable_type = ? AND tokenable_id = ?', [self::MODEL_USER, $id]);
        Database::delete('model_has_roles', 'model_type = ? AND model_id = ?', [self::MODEL_USER, $id]);
        Database::delete('model_has_permissions', 'model_type = ? AND model_id = ?', [self::MODEL_USER, $id]);

        User::deleteRow($id);

        return $this->sendResponse(['deleted' => true], 'User deleted successfully.');
    }

    /**
     * Current user's name. GET /api/user — original UserController::user()
     * returned `{username: auth()->user()->name}`.
     */
    public function me(): array
    {
        $user = Auth::user();
        if ($user === null) {
            Response::json(['status' => 'errors'], 401);
        }

        return ['username' => $user['name'] ?? ''];
    }

    /**
     * Whether the current user is an admin (type != 4).
     * GET /api/isAdmin — original returns `{status, isAdmin}`.
     */
    public function isAdminEndpoint(): array
    {
        return ['status' => 'success', 'isAdmin' => Auth::isAdmin()];
    }

    /**
     * Whether the current user is a center user (type == 4).
     * GET /api/isCenter — original also returns the flag under `isAdmin`
     * (the key the original frontend read), so keep that exact shape for
     * the URL/JSON contract.
     */
    public function isCenterEndpoint(): array
    {
        return ['status' => 'success', 'isAdmin' => !Auth::isAdmin()];
    }

    /** Assign exactly one role (or clear it) for a user. */
    private function syncRole(int $userId, mixed $roleId): void
    {
        Database::delete('model_has_roles', 'model_type = ? AND model_id = ?', [self::MODEL_USER, $userId]);
        if ($roleId !== null && $roleId !== '' && (int) $roleId > 0) {
            Database::insert('model_has_roles', [
                'role_id'    => (int) $roleId,
                'model_type' => self::MODEL_USER,
                'model_id'   => $userId,
            ]);
        }
    }
}
