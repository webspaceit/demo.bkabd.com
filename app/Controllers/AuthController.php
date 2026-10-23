<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Totp;
use App\Core\Validator;
use App\Models\User;

/**
 * Login / logout / register + two-factor authentication (TOTP).
 *
 * Response shapes kept identical to the original for the classic flow:
 *  - success: {success:true, data:{token,name}, message:'User login successfully.'}
 *  - failure: {"status":"errors"} 401 (bad credentials) / 403 (inactive center)
 *
 * Secure-hash: passwords are stored with password_hash(PASSWORD_DEFAULT)
 * (verify in Auth::credentials) and rehashed in place when the algorithm or
 * cost changes (password_needs_rehash) — see Auth::credentials().
 *
 * Rate limiting (fixed-window, per IP — see RateLimiter):
 *  - login:       20 attempts / 15 min per IP + 10 / 15 min per account
 *  - mfa challenge: 10 / 15 min per IP (only reached with a correct password)
 *  - mfa setup:   10 / 15 min per user
 *
 * MFA flow (RFC 6238 TOTP, pure PHP — see App\Core\Totp):
 *  1. POST /api/login -> 200 {mfa_required:true, mfa_token, ...} when the
 *     account has two_factor_enabled=1 (no session created yet).
 *  2. POST /api/login/mfa {mfa_token, code} -> completes the login; the
 *     challenge lives in the session for 5 minutes.
 *  Setup: GET /api/mfa/setup, POST /api/mfa/enable {code}, POST /api/mfa/disable {code}.
 */
class AuthController extends Controller
{
    public function login(Request $request)
    {
        $email = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');

        RateLimiter::hit('login', 20, 900);                        // per IP (shared-office friendly)
        RateLimiter::hit('login:' . strtolower($email), 10, 900);  // per account

        $user = Auth::credentials($email, $password);
        if ($user === null) {
            Response::json(['status' => 'errors'], Auth::loginFailureCode($email));
        }

        // Refuse known default/weak passwords outside local dev (rotate with
        // `php tools/set_password.php <email>`). Override by setting
        // BLOCK_DEFAULT_PASSWORDS=false in .env.
        if (self::isKnownDefaultPassword($password) && self::blockDefaultPasswords()) {
            throw new HttpException(422, 'This account still uses a default password. An administrator must rotate it (php tools/set_password.php ' . $email . ') before you can sign in.');
        }

        // Account has MFA enabled: issue a short-lived challenge, do NOT log in.
        if ((int) ($user['two_factor_enabled'] ?? 0) === 1) {
            $token = bin2hex(random_bytes(24));
            Session::put('mfa_pending_user', (int) $user['id']);
            Session::put('mfa_pending_token', hash('sha256', $token));
            Session::put('mfa_pending_expires', time() + 300); // 5 minutes

            return $this->sendResponse([
                'mfa_required' => true,
                'mfa_token' => $token,
            ], 'Enter the 6-digit code from your authenticator app.');
        }

        RateLimiter::clear('login', $request->ip());
        RateLimiter::clear('login:' . strtolower($email));
        Auth::completeLogin($user);
        \App\Services\LoginHistoryService::recordSuccessfulLogin($user, $request);
        $user = Auth::user();
        return $this->sendResponse([
            'token' => Session::get('access_token'),
            'name' => $user['name'] ?? '',
        ], 'User login successfully.');
    }

    /**
     * Step 2 of the MFA login: verify the TOTP code and complete the session.
     * POST /api/login/mfa {mfa_token, code}
     */
    public function mfaChallenge(Request $request)
    {
        RateLimiter::hit('login-mfa', 10, 900); // per IP

        $token = (string) $request->input('mfa_token', '');
        $code = (string) $request->input('code', '');

        $userId = Session::get('mfa_pending_user');
        $expectedToken = Session::get('mfa_pending_token');
        $expiresAt = (int) Session::get('mfa_pending_expires', 0);

        $stale = $userId === null
            || !is_string($expectedToken)
            || time() > $expiresAt
            || !hash_equals($expectedToken, hash('sha256', $token));
        if ($stale) {
            Session::forget('mfa_pending_user');
            Session::forget('mfa_pending_token');
            Session::forget('mfa_pending_expires');
            throw new HttpException(422, 'Your login session has expired. Please sign in again.');
        }

        $user = Database::selectOne('SELECT * FROM `users` WHERE `id` = ?', [(int) $userId]);
        if (
            $user === null
            || (int) ($user['two_factor_enabled'] ?? 0) !== 1
            || (string) ($user['two_factor_secret'] ?? '') === ''
        ) {
            throw new HttpException(422, 'Two-factor authentication is not enabled for this account.');
        }
        if (!Totp::verify((string) $user['two_factor_secret'], $code)) {
            throw new HttpException(422, 'The verification code is incorrect.');
        }

        Session::forget('mfa_pending_user');
        Session::forget('mfa_pending_token');
        Session::forget('mfa_pending_expires');
        Auth::completeLogin($user);
        \App\Services\LoginHistoryService::recordSuccessfulLogin($user, $request);
        return $this->sendResponse([
            'token' => Session::get('access_token'),
            'name' => $user['name'] ?? '',
        ], 'User login successfully.');
    }

    /** GET /api/mfa/setup — current (or freshly generated) secret + provisioning URI. */
    public function mfaSetup(Request $request)
    {
        $user = Auth::user();
        if ((int) ($user['two_factor_enabled'] ?? 0) === 1) {
            throw new HttpException(403, 'Two-factor authentication is already enabled.');
        }
        $secret = (string) ($user['two_factor_secret'] ?? '');
        if ($secret === '') {
            $secret = Totp::generateSecret();
            Database::update('users', ['two_factor_secret' => $secret], '`id` = :id', [':id' => (int) $user['id']]);
        }
        return $this->sendResponse([
            'secret' => $secret,
            'otpauth' => Totp::provisioningUri($secret, (string) ($user['email'] ?? '')),
            'enabled' => false,
        ], 'Two-factor setup data retrieved.');
    }

    /** POST /api/mfa/enable {code} — verify once, then turn MFA on. */
    public function mfaEnable(Request $request)
    {
        RateLimiter::hit('mfa-setup:' . (int) Auth::id(), 10, 900);
        $user = Auth::user();
        if ((int) ($user['two_factor_enabled'] ?? 0) === 1) {
            throw new HttpException(403, 'Two-factor authentication is already enabled.');
        }
        $secret = (string) ($user['two_factor_secret'] ?? '');
        if ($secret === '') {
            throw new HttpException(422, 'Generate a secret first.');
        }
        $code = (string) $request->input('code', '');
        if (!Totp::verify($secret, $code)) {
            throw new HttpException(422, 'The verification code is incorrect.');
        }
        Database::update('users', ['two_factor_enabled' => 1], '`id` = :id', [':id' => (int) $user['id']]);
        return $this->sendResponse(['enabled' => true], 'Two-factor authentication enabled.');
    }

    /** POST /api/mfa/disable {code} — verify before turning MFA off. */
    public function mfaDisable(Request $request)
    {
        RateLimiter::hit('mfa-setup:' . (int) Auth::id(), 10, 900);
        $user = Auth::user();
        if ((int) ($user['two_factor_enabled'] ?? 0) !== 1) {
            throw new HttpException(422, 'Two-factor authentication is not enabled.');
        }
        $code = (string) $request->input('code', '');
        if (!Totp::verify((string) $user['two_factor_secret'], $code)) {
            throw new HttpException(422, 'The verification code is incorrect.');
        }
        Database::update('users', ['two_factor_secret' => '', 'two_factor_enabled' => 0], '`id` = :id', [':id' => (int) $user['id']]);
        return $this->sendResponse(['enabled' => false], 'Two-factor authentication disabled.');
    }

    public function logout(Request $request)
    {
        $deleted = 0;
        if (Auth::check()) {
            // Original deleted all personal access tokens for the user.
            $deleted = Database::delete(
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
        $data = Validator::validate($request, [
            'name' => 'required',
            'email' => 'required|email|unique:users,email',
            'password' => 'required',
            'c_password' => 'required|same:password',
        ]);

        $id = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_DEFAULT),
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

    /** Passwords that must never be accepted in production. */
    private static function isKnownDefaultPassword(string $password): bool
    {
        static $defaults = [
            'admin123', 'center123', 'password', 'admin', 'administrator',
            '123456', '12345678', 'qwerty', 'letmein', 'welcome', 'changeme',
        ];
        return in_array(strtolower(trim($password)), $defaults, true);
    }

    private static function blockDefaultPasswords(): bool
    {
        return Config::getBool('BLOCK_DEFAULT_PASSWORDS', Config::get('APP_ENV', 'production') !== 'local');
    }
}
