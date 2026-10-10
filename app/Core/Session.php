<?php

namespace App\Core;

/**
 * Session wrapper: start once, CSRF token, flash messages, active-exam-session key.
 */
final class Session
{
    private static bool $started = false;

    /** Session cookie name (matches the original Laravel app: APP_NAME=BKABD). */
    private const COOKIE = 'bkabd_session';

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        // A cookie with this name can be left over from another app on the same
        // host — cookies are keyed by host, not port — and the original Laravel
        // app's encrypted value is not a legal PHP session id. PHP would raise a
        // warning and fail to start, so drop an invalid cookie and start fresh.
        $incoming = $_COOKIE[self::COOKIE] ?? null;
        if ($incoming !== null && !self::isValidId(is_string($incoming) ? $incoming : '')) {
            unset($_COOKIE[self::COOKIE]);
            session_id(bin2hex(random_bytes(16)));
        }

        $lifetime = Config::getInt('SESSION_LIFETIME', 120);
        ini_set('session.use_strict_mode', '1'); // reject unknown ids (anti session fixation)
        session_name(self::COOKIE);
        session_set_cookie_params([
            'lifetime' => $lifetime * 60,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            // 'secure' => true, // enable when serving over HTTPS
        ]);
        session_start();
        self::$started = true;
    }

    /** PHP session ids allow only A-Z a-z 0-9 , - and are length-limited. */
    private static function isValidId(string $id): bool
    {
        return $id !== '' && strlen($id) <= 128 && preg_match('/^[A-Za-z0-9,\-]+$/', $id) === 1;
    }

    /**
     * Release the session file lock after reading — critical under load:
     * concurrent requests of the same session are otherwise serialized.
     */
    public static function close(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        self::$started = false;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function all(): array
    {
        self::start();
        return $_SESSION;
    }

    public static function token(): string
    {
        self::start();
        if (empty($_SESSION['_token'])) {
            $_SESSION['_token'] = bin2hex(random_bytes(20));
        }
        return $_SESSION['_token'];
    }

    public static function checkToken(?string $token): bool
    {
        self::start();
        return is_string($token) && !empty($_SESSION['_token']) && hash_equals($_SESSION['_token'], $token);
    }

    /** One-shot flash message (survives one redirect). */
    public static function flash(string $key, mixed $value): void
    {
        self::start();
        $_SESSION['_flash'][$key] = $value;
    }

    public static function pullFlash(string $key, mixed $default = null): mixed
    {
        self::start();
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    /** Validation errors carried across a redirect (Laravel "with('errors')"). */
    public static function putErrors(array $errors): void
    {
        self::flash('errors', $errors);
    }

    public static function pullErrors(): array
    {
        $e = self::pullFlash('errors', []);
        return is_array($e) ? $e : [];
    }

    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool) $p['secure'], (bool) $p['httponly']);
        }
        session_destroy();
        self::$started = false;
    }
}
