<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Role;
use App\Models\Permission;

/**
 * Role controller — ported from original RoleController.
 *
 * Endpoint contracts (URL + JSON shape) identical to original routes/api.php.
 * Auth: 'auth' middleware; permission gates per original constructor strings.
 */
final class RoleController extends Controller
{
    /** List roles with pagination. GET /api/roleList */
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

        return $this->sendResponse(
            $q->paginate((int) ($request->input('per_page', 20)), null, '/api/roleList'),
            'Role list retrieved.'
        );
    }

    /** Create role. POST /api/roleCreate */
    public function store(Request $request): array
    {
        if (!Auth::isAdmin()) {
            Response::json(['status' => 'errors'], 403);
        }

        $rules = [
            'name' => 'required|string|unique:roles,name',
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'exists:permissions,id',
        ];

        try {
            $data = Validator::validate($request, $rules);
        } catch (\App\Core\Validator\ValidationException $e) {
            Response::validationError($e->errors, $e->getMessage());
        }

        // Begin transaction: create role + assign permissions
        \App\Core\Database::transaction(function () use ($data) {
            $role = Role::create(['name' => $data['name'], 'guard_name' => 'sanctum']);

            // Sync permissions via role_has_permissions (many-to-many)
            // First clear any existing permissions for this role
            Database::delete('role_has_permissions', 'role_id = ?', [$role['id']]);

            // Insert new permissions
            $perms = array_values($data['permission_ids']);
            foreach ($perms as $permId) {
                Database::insert('role_has_permissions', [
                    'permission_id' => (int) $permId,
                    'role_id'       => (int) $role['id'],
                ]);
            }
        });

        return $this->sendResponse(
            ['id' => $role['id'], 'name' => $data['name']],
            'Role registered successfully.'
        );
    }

    /** View role. GET /api/roleById/{id} */
    public function show(int $id): array
    {
        $role = Role::find($id);
        if ($role === null) {
            throw new \App\Core\HttpException(404, 'Role not found');
        }

        // Load assigned permissions
        $perms = Database::select(
            "SELECT p.* FROM `permissions` p
               JOIN `role_has_permissions` rp ON rp.`permission_id` = p.`id`
              WHERE rp.`role_id` = ?",
            [$id]
        );

        $roleData = ['id' => $role['id'], 'name' => $role['name'], 'permissions' => $perms];

        return $this->sendResponse($roleData, 'Role details retrieved.');
    }

    /** Update role. POST /api/roleUpdate/{id} */
    public function update(Request $request, int $id): array
    {
        if (!Auth::isAdmin()) {
            Response::json(['status' => 'errors'], 403);
        }

        $role = Role::find($id);
        if ($role === null) {
            throw new \App\Core\HttpException(404, 'Role not found');
        }

        $rules = [
            'name' => 'required|string|unique:roles,name,' . $id,
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'exists:permissions,id',
        ];

        try {
            $data = Validator::validate($request, $rules);
        } catch (\App\Core\Validator\ValidationException $e) {
            Response::validationError($e->errors, $e->getMessage());
        }

        // Begin transaction: update role + re-assign permissions
        \App\Core\Database::transaction(function () use ($data, $id) {
            Role::updateRow($id, ['name' => $data['name']]);

            // Clear existing permissions and re-assign
            Database::delete('role_has_permissions', 'role_id = ?', [$id]);

            $perms = array_values($data['permission_ids']);
            foreach ($perms as $permId) {
                Database::insert('role_has_permissions', [
                    'permission_id' => (int) $permId,
                    'role_id'       => (int) $id,
                ]);
            }
        });

        return $this->sendResponse(
            ['id' => $id, 'name' => $data['name']],
            'Role updated successfully.'
        );
    }

    /** Delete role. DELETE /api/roleDelete/{id} */
    public function destroy(int $id): array
    {
        if (!Auth::isAdmin()) {
            Response::json(['status' => 'errors'], 403);
        }

        $role = Role::find($id);
        if ($role === null) {
            throw new \App\Core\HttpException(404, 'Role not found');
        }

        // Delete role and its permission assignments
        Database::delete('role_has_permissions', 'role_id = ?', [$id]);
        Database::delete('model_has_roles', 'role_id = ?', [$id]); // clean orphaned model_has_roles

        Role::deleteRow($id);

        return $this->sendResponse(
            ['deleted' => true],
            'Role deleted successfully.'
        );
    }

    /** Role-wise permission list. GET /api/roleWisePermissionList */
    public function roleWisePermissionList(): array
    {
        $id = Auth::id();
        if ($id === null) {
            Response::json(['status' => 'errors'], 401);
        }

        $rows = Database::select(
            "SELECT DISTINCT p.`name` AS name
               FROM `permissions` p
               JOIN `role_has_permissions` rp ON rp.`permission_id` = p.`id`
               JOIN `model_has_roles` mr ON mr.`role_id` = rp.`role_id`
              WHERE mr.`model_type` = 'App\Models\User' AND mr.`model_id` = ?
             UNION
             SELECT DISTINCT p.`name` AS name
               FROM `permissions` p
               JOIN `model_has_permissions` mp ON mp.`permission_id` = p.`id`
              WHERE mp.`model_type` = 'App\Models\User' AND mp.`model_id` = ?",
            [$id, $id]
        );

        return $this->sendResponse(['roleWisePermissionList' => array_column($rows, 'name')], 'Permission list retrieved.');
    }

    /** All permissions list. GET /api/permissionList */
    public function permissionList(): array
    {
        $rows = Database::select('SELECT id, name, guard_name FROM `permissions` ORDER BY name');

        return $this->sendResponse(array_column($rows, 'name', 'id'), 'Permission list retrieved.');
    }
}