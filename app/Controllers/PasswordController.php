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
        return \App\Core\View::render('auth.passchange', [
            'title' => 'Password Change',
            'pageScripts' => ['/assets/js/users.js'],
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
}
