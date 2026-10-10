<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Login / logout / register (ported from original AuthController).
 *
 * Response shapes kept identical:
 *  - success: {success:true, data:{token,name}, message:'User login successfully.'}
 *  - failure: {"status":"errors"} 401 (bad credentials) / 403 (inactive center)
 */
class AuthController extends Controller
{
    public function login(Request $request)
    {
        $email = (string) $request->input('email', '');
        $password = (string) $request->input('password', '');

        $result = Auth::attempt($email, $password);
        if (!$result['ok']) {
            Response::json(['status' => 'errors'], $result['code']);
        }

        $user = Auth::user();
        return $this->sendResponse([
            'token' => Session::get('access_token'),
            'name' => $user['name'] ?? '',
        ], 'User login successfully.');
    }

    public function logout(Request $request)
    {
        $deleted = 0;
        if (Auth::check()) {
            // Original deleted all personal access tokens for the user.
            $deleted = \App\Core\Database::delete(
                'personal_access_tokens',
                'tokenable_type = ? AND tokenable_id = ?',
                ['App\\Models\\User', Auth::id()]
            );
        }
        Auth::logout();
        return $this->sendResponse($deleted, 'User logout successfully.');
    }

    public function register(Request $request)
    {
        $this->guardAdmin();
        $data = \App\Core\Validator::validate($request, [
            'name' => 'required',
            'email' => 'required|email|unique:users,email',
            'password' => 'required',
            'c_password' => 'required|same:password',
        ]);

        $id = \App\Models\User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_BCRYPT),
            'type' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        Session::put('access_token', bin2hex(random_bytes(24)));
        return $this->sendResponse([
            'token' => Session::get('access_token'),
            'name' => $data['name'],
        ], 'User register successfully.');
    }

    private function guardAdmin(): void
    {
        if (!Auth::isAdmin()) {
            Response::json(['message' => 'This action is unauthorized.'], 403);
        }
    }
}
