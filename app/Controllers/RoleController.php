<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Role;

/**
 * Role controller — ported from original RoleController.
 *
 * List/detail endpoints return the flat JSON the original Vue pages read
 * (`roleList`, `role`, `rolePermissions`, `roleWisePermissionList`,
 * `permissionList`); the create/update/delete actions keep the standard
 * `{success,data,message}` envelope.
 *
 * NOTE: `model_has_roles`.`model_type` must be compared via a BOUND parameter
 * ('App\\Models\\User'). MySQL treats a backslash inside a string literal as an
 * escape, so an inline '\App\Models\User' collapses to 'AppModelsUser' and
 * silently matches nothing — that bug made roleWisePermissionList return [].
 */
final class RoleController extends Controller
{
    /** GET /api/roleList -> {roleList:[{id,name,guard_name}]} */
    public function index(Request $request): array
    {
        if (!Auth::isAdmin()) {
            Response::json(['status' => 'errors'], 403);
        }

        $q = Role::query()->select('id', 'name', 'guard_name');

        $search = $request->input('search');
        if ($search) {
            $q->where('name', 'like', "%{$search}%");
        }
        $q->orderBy('name', 'asc');

        return ['roleList' => $q->get()];
    }

    /** GET /api/roleById/{id} -> {role:{id,name}, rolePermissions:[ids]} */
    public function show(Request $request): array
    {
        $id = (int) $request->params['id'];
        $role = Role::find($id);
        if ($role === null) {
            throw new HttpException(404, 'Role not found');
        }

        $perms = Database::select(
            "SELECT p.`id` FROM `permissions` p
               JOIN `role_has_permissions` rp ON rp.`permission_id` = p.`id`
              WHERE rp.`role_id` = ?",
            [$id]
        );

        return [
            'role'            => ['id' => (int) $role['id'], 'name' => $role['name']],
            'rolePermissions' => array_map('intval', array_column($perms, 'id')),
        ];
    }

    /** POST /api/roleCreate (alias /api/roleSave) — body {name, permission:[ids]} */
    public function store(Request $request): array
    {
        if (!Auth::isAdmin()) {
            Response::json(['status' => 'errors'], 403);
        }

        $data = Validator::validate($request, [
            'name'          => 'required|string|unique:roles,name',
            'permission'    => 'array',
            'permission.*'  => 'exists:permissions,id',
        ]);

        $roleId = Database::transaction(function () use ($data) {
            $id = Role::create(['name' => $data['name'], 'guard_name' => 'sanctum']);
            $this->syncPermissions($id, $data['permission'] ?? []);
            return $id;
        });

        return $this->sendResponse(
            ['id' => $roleId, 'name' => $data['name']],
            'Role registered successfully.'
        );
    }

    /** POST /api/roleUpdate/{id} — body {name, permission:[ids]} */
    public function update(Request $request): array
    {
        $id = (int) $request->params['id'];
        if (!Auth::isAdmin()) {
            Response::json(['status' => 'errors'], 403);
        }

        $role = Role::find($id);
        if ($role === null) {
            throw new HttpException(404, 'Role not found');
        }

        $data = Validator::validate($request, [
            'name'          => 'required|string|unique:roles,name,' . $id,
            'permission'    => 'array',
            'permission.*'  => 'exists:permissions,id',
        ]);

        Database::transaction(function () use ($data, $id) {
            Role::updateRow($id, ['name' => $data['name']]);
            $this->syncPermissions($id, $data['permission'] ?? []);
        });

        return $this->sendResponse(
            ['id' => $id, 'name' => $data['name']],
            'Role updated successfully.'
        );
    }

    /** DELETE /api/roleDelete/{id} */
    public function destroy(Request $request): array
    {
        $id = (int) $request->params['id'];
        if (!Auth::isAdmin()) {
            Response::json(['status' => 'errors'], 403);
        }

        $role = Role::find($id);
        if ($role === null) {
            throw new HttpException(404, 'Role not found');
        }

        Database::delete('role_has_permissions', 'role_id = ?', [$id]);
        Database::delete('model_has_roles', 'role_id = ?', [$id]);
        Role::deleteRow($id);

        return $this->sendResponse(['deleted' => true], 'Role deleted successfully.');
    }

    /** GET /api/roleWisePermissionList -> {roleWisePermissionList:[{name}]} */
    public function roleWisePermissionList(): array
    {
        $id = Auth::id();
        if ($id === null) {
            Response::json(['status' => 'errors'], 401);
        }

        // model_type is BOUND (not an inline literal) — see the class docblock.
        $rows = Database::select(
            "SELECT DISTINCT p.`name` AS name
               FROM `permissions` p
               JOIN `role_has_permissions` rp ON rp.`permission_id` = p.`id`
               JOIN `model_has_roles` mr ON mr.`role_id` = rp.`role_id`
              WHERE mr.`model_type` = ? AND mr.`model_id` = ?
             UNION
             SELECT DISTINCT p.`name` AS name
               FROM `permissions` p
               JOIN `model_has_permissions` mp ON mp.`permission_id` = p.`id`
              WHERE mp.`model_type` = ? AND mp.`model_id` = ?",
            ['App\\Models\\User', $id, 'App\\Models\\User', $id]
        );

        return ['roleWisePermissionList' => $rows];
    }

    /** GET /api/permissionList -> {permissionList:[{id,name,guard_name}]} */
    public function permissionList(): array
    {
        $rows = Database::select('SELECT `id`, `name`, `guard_name` FROM `permissions` ORDER BY `name`');

        return ['permissionList' => $rows];
    }

    /** Replace a role's permission set (used by store/update). */
    private function syncPermissions(int $roleId, array $permissionIds): void
    {
        Database::delete('role_has_permissions', 'role_id = ?', [$roleId]);
        foreach (array_values($permissionIds) as $permId) {
            Database::insert('role_has_permissions', [
                'permission_id' => (int) $permId,
                'role_id'       => $roleId,
            ]);
        }
    }
}
