<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Models\User;

/**
 * Password change module — ported from the original UserController::passchange
 * (routes/api.php: POST /api/passchange, auth-only, no permission gate).
 *
 * Contract kept: {status:'success'}, 422 errors on
 * password / npassword / cnpassword with the original custom messages
 * ("Password is incorrect.", "New Password and Confirm New Password is not
 * matched"). The hash is replaced with password_hash() and never returned.
 */
class PasswordController extends Controller
{
    /** GET /passchange → password change form. */
    public function page(Request $request): string
    {
        $user = Auth::user();
        return \App\Core\View::render('auth.passchange', [
            'title' => 'Account Security',
            'accountName' => (string) ($user['name'] ?? ''),
            'accountEmail' => (string) ($user['email'] ?? ''),
            'twoFactorEnabled' => (int) ($user['two_factor_enabled'] ?? 0) === 1,
            'pageScripts' => [
                '/assets/js/vendor/qrcode.min.js',
                '/assets/js/users.js',
                '/assets/js/mfa.js',
            ],
        ], 'layouts.dashboard');
    }

    /** POST /api/passchange  body: password, npassword, cnpassword → {status:'success'}. */
    public function change(Request $request): array
    {
        $data = Validator::validate($request, [
            'password' => 'required',
            'npassword' => 'required',
            'cnpassword' => 'required|same:npassword',
        ], [
            'cnpassword.same' => 'New Password and Confirm New Password is not matched',
        ]);

        $user = Auth::user();
        if ($user === null) {
            throw new ValidationException(['password' => ['Password is incorrect.']]);
        }

        // Original: custom rule Hash::check($value, Auth::user()->password).
        if (!password_verify($data['password'], (string) $user['password'])) {
            throw new ValidationException(['password' => ['Password is incorrect.']]);
        }

        User::updateRow((int) $user['id'], [
            'password' => password_hash($data['npassword'], PASSWORD_BCRYPT),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return ['status' => 'success'];
    }

    /** Update the signed-in user's own display name and email. */
    public function updateProfile(Request $request): array
    {
        $user = Auth::user();
        if ($user === null) {
            throw new ValidationException(['current_password' => ['Please sign in again.']]);
        }

        $data = Validator::validate($request, [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . (int) $user['id'],
            'current_password' => 'required',
        ]);

        if (!password_verify((string) $data['current_password'], (string) $user['password'])) {
            throw new ValidationException(['current_password' => ['Current password is incorrect.']]);
        }

        User::updateRow((int) $user['id'], [
            'name' => trim((string) $data['name']),
            'email' => strtolower(trim((string) $data['email'])),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return ['status' => 'success'];
    }
}
