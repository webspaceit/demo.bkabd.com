<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\User;

/**
 * User controller — ported from original UserController.
 *
 * Endpoint contracts (URL + JSON shape) identical to original routes/api.php.
 * Auth: 'auth' middleware on all routes (except permissionList which is public for role create/edit).
 * Passwords: hashed with password_hash() at create; never logged or exposed in JSON.
 */
final class UserController extends Controller
{
    /** List users with pagination and optional search. GET /api/userList */
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

        // Optional filter by type (1=admin, 4=center)
        $type = $request->input('type');
        if ($type !== null) {
            $q->where('type', (int) $type);
        }

        return $this->sendResponse(
            $q->paginate((int) ($request->input('per_page', 20)), null, '/api/userList'),
            'User list retrieved.'
        );
    }

    /** Create user. POST /api/userCreate */
    public function store(Request $request): array
    {
        if (!Auth::isAdmin()) {
            Response::json(['status' => 'errors'], 403);
        }

        $rules = [
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|max:255|unique:users,email',
            'phone'     => 'nullable|string|max:11',
            'password'  => 'required|string|min:6',
            'type'      => 'required|in:1,2,3,4',
        ];

        try {
            $data = Validator::validate($request, $rules);
        } catch (\App\Core\Validator\ValidationException $e) {
            Response::validationError($e->errors, $e->getMessage());
        }

        $id = User::create(array_filter([
            'name'       => $data['name'],
            'email'      => $data['email'],
            'phone'      => $data['phone'] ?? '',
            'password'   => password_hash($data['password'], PASSWORD_BCRYPT),
            'type'       => (int) $data['type'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]));

        return $this->sendResponse(
            ['id' => $id, 'name' => $data['name'], 'email' => $data['email']],
            'User registered successfully.'
        );
    }

    /** View user. GET /api/userById/{id} */
    public function show(int $id): array
    {
        $user = User::find($id);
        if ($user === null) {
            throw new \App\Core\HttpException(404, 'User not found');
        }

        // Strip password from output
        unset($user['password']);

        return $this->sendResponse($user, 'User details retrieved.');
    }

    /** Update user. POST /api/userUpdate/{id} */
    public function update(Request $request, int $id): array
    {
        if (!Auth::isAdmin()) {
            Response::json(['status' => 'errors'], 403);
        }

        $user = User::find($id);
        if ($user === null) {
            throw new \App\Core\HttpException(404, 'User not found');
        }

        $rules = [
            'name'      => 'nullable|string|max:255',
            'email'     => 'nullable|email|max:255|unique:users,email,' . $id,
            'phone'     => 'nullable|string|max:11',
            'password'  => 'nullable|string|min:6',
            'type'      => 'nullable|in:1,2,3,4',
        ];

        try {
            $data = Validator::validate($request, $rules);
        } catch (\App\Core\Validator\ValidationException $e) {
            Response::validationError($e->errors, $e->getMessage());
        }

        $merge = [
            'name'       => $data['name'] ?? $user['name'],
            'email'      => $data['email'] ?? $user['email'],
            'phone'      => $data['phone'] ?? $user['phone'],
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (!empty($data['password'])) {
            $merge['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }
        if (isset($data['type'])) {
            $merge['type'] = (int) $data['type'];
        }

        User::updateRow($id, array_filter($merge));

        return $this->sendResponse(
            ['id' => $id, 'name' => $merge['name'], 'email' => $merge['email']],
            'User updated successfully.'
        );
    }

    /** Delete user. DELETE /api/userDelete/{id} */
    public function destroy(int $id): array
    {
        if (!Auth::isAdmin()) {
            Response::json(['status' => 'errors'], 403);
        }

        $user = User::find($id);
        if ($user === null) {
            throw new \App\Core\HttpException(404, 'User not found');
        }

        // Also delete associated center if type was 4
        if ($user['type'] === 4) {
            Database::delete('centers', 'user_id = ?', [$id]);
        }

        // Delete personal access tokens (original did this in logout, but do it on delete too)
        Database::delete('personal_access_tokens', 'tokenable_type = ? AND tokenable_id = ?',
            ['App\Models\User', $id]);

        User::deleteRow($id);

        return $this->sendResponse(
            ['deleted' => true],
            'User deleted successfully.'
        );
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
}