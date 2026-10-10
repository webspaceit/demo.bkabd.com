<?php

namespace App\Core;

/**
 * Session-based authentication + Spatie-style RBAC (same tables as original).
 *
 * Login rules replicated from the original AuthController:
 *  - type != 4 (admin)  : email+password
 *  - type == 4 (center) : email+password AND centers.user_id row must be active
 *  - failures return {status:'errors'} with 401 (bad creds) / 403 (inactive center)
 */
final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;
    /** @var string[]|null Permission names of current user (per-request) */
    private static ?array $permissions = null;

    // ---------------------------------------------------------------
    // Session identity
    // ---------------------------------------------------------------

    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;
        $id = Session::get('auth_user_id');
        if ($id) {
            self::$user = Database::selectOne('SELECT * FROM `users` WHERE `id` = ?', [(int) $id]);
        }
        return self::$user;
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u ? (int) $u['id'] : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    /** @return array{ok:bool,code:int} code: 200 | 401 | 403 (original semantics) */
    public static function attempt(string $email, string $password): array
    {
        $user = Database::selectOne('SELECT * FROM `users` WHERE `email` = ? LIMIT 1', [$email]);
        if ($user === null) {
            return ['ok' => false, 'code' => 401];
        }
        if ((int) $user['type'] === 4) {
            $center = Database::selectOne('SELECT `active` FROM `centers` WHERE `user_id` = ? LIMIT 1', [(int) $user['id']]);
            if ($center === null || (int) $center['active'] !== 1) {
                return ['ok' => false, 'code' => 403];
            }
        }
        if (!password_verify($password, $user['password'])) {
            return ['ok' => false, 'code' => 401];
        }
        self::login($user);
        return ['ok' => true, 'code' => 200];
    }

    private static function login(array $user): void
    {
        Session::regenerate();
        // Rotate the CSRF token too (defense in depth against token leakage
        // via referrer / XSS before login); the old token stops working.
        Session::put('_token', bin2hex(random_bytes(20)));
        Session::put('auth_user_id', (int) $user['id']);
        Session::put('access_token', bin2hex(random_bytes(24)));
        self::$user = $user;
        self::$loaded = true;
        self::$permissions = null;
    }

    public static function logout(): void
    {
        Session::destroy();
        self::$user = null;
        self::$loaded = false;
        self::$permissions = null;
    }

    // ---------------------------------------------------------------
    // Authorization
    // ---------------------------------------------------------------

    /** Admin = anything except center user (type 4), same as original. */
    public static function isAdmin(): bool
    {
        $u = self::user();
        return $u !== null && (int) $u['type'] !== 4;
    }

    /** Center id for center users (centers.user_id = users.id), null for admins. */
    public static function centerId(): ?int
    {
        $u = self::user();
        if ($u === null) {
            return null;
        }
        $key = 'center_id:user:' . $u['id'];
        $cid = Cache::remember($key, 300, function () use ($u) {
            $row = Database::selectOne('SELECT `id` FROM `centers` WHERE `user_id` = ? LIMIT 1', [(int) $u['id']]);
            return $row ? (int) $row['id'] : 0;
        });
        return $cid ?: null;
    }

    /**
     * Permission names for current user (role permissions + direct grants).
     * Loaded once per request.
     * @return string[]
     */
    public static function permissions(): array
    {
        if (self::$permissions !== null) {
            return self::$permissions;
        }
        $id = self::id();
        if ($id === null) {
            return self::$permissions = [];
        }
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
            ['App\Models\User', $id, 'App\Models\User', $id]
        );
        return self::$permissions = array_column($rows, 'name');
    }

    public static function can(string $permission): bool
    {
        return in_array($permission, self::permissions(), true);
    }

    /** Any of the given permissions (pipe-separated string or array). */
    public static function canAny(string|array $permissions): bool
    {
        $list = is_array($permissions) ? $permissions : explode('|', $permissions);
        $mine = self::permissions();
        foreach ($list as $p) {
            if (in_array(trim($p), $mine, true)) {
                return true;
            }
        }
        return false;
    }

    /** Role names of current user. @return string[] */
    public static function roleNames(): array
    {
        $id = self::id();
        if ($id === null) {
            return [];
        }
        $rows = Database::select(
            "SELECT DISTINCT r.`name` AS name
               FROM `roles` r
               JOIN `model_has_roles` mr ON mr.`role_id` = r.`id`
              WHERE mr.`model_type` = 'App\\Models\\User' AND mr.`model_id` = ?",
            [$id]
        );
        return array_column($rows, 'name');
    }

    // ---------------------------------------------------------------
    // Exam session selection (replicates Controller::activeSession)
    // ---------------------------------------------------------------

    /** Latest sessions row's `session` value (cached 60s, invalidated on change). */
    public static function recentSession(): string|int
    {
        return Cache::remember('latest_session:row', 60, function () {
            $row = Database::selectOne('SELECT `session` FROM `sessions` ORDER BY `id` DESC LIMIT 1');
            return $row['session'] ?? '';
        });
    }

    /**
     * Exam session used to scope data: admins may override via
     * session key admin_session (GET /api/updateSession/{id}); everyone
     * else gets the latest session.
     */
    public static function activeSession(): string|int
    {
        if (self::isAdmin()) {
            $override = Session::get('admin_session');
            if ($override) {
                return $override;
            }
        }
        return self::recentSession();
    }
}
