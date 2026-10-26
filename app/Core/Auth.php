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

    /**
     * Verify email+password WITHOUT starting a session — used by the MFA
     * two-step login and by attempt().
     *
     * Returns the users row (with its hash) or null (bad creds / inactive
     * center). Mirrors the original semantics: type 4 (center) users must
     * also have an active centers.user_id row, otherwise 403.
     */
    public static function credentials(string $email, string $password): ?array
    {
        $user = Database::selectOne('SELECT * FROM `users` WHERE `email` = ? LIMIT 1', [$email]);
        if ($user === null) {
            return null;
        }
        if ((int) $user['type'] === 4) {
            $center = Database::selectOne('SELECT `active` FROM `centers` WHERE `user_id` = ? LIMIT 1', [(int) $user['id']]);
            if ($center === null || (int) $center['active'] !== 1) {
                return null;
            }
        }
        if (!password_verify($password, $user['password'])) {
            return null;
        }
        // Migrate hashes in place (PASSWORD_DEFAULT → current default algorithm).
        // Runs at most once per account; keeps older bcrypt-cost hashes upgraded
        // without forcing a password reset (OWASP password storage guidance).
        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            Database::update('users', ['password' => password_hash($password, PASSWORD_DEFAULT)], '`id` = :id', [':id' => (int) $user['id']]);
        }
        return $user;
    }

    /**
     * HTTP status for a failed login of $email with the original semantics:
     * 401 bad credentials, 403 known center user whose center is inactive.
     */
    public static function loginFailureCode(string $email): int
    {
        $existing = Database::selectOne('SELECT `id`, `type` FROM `users` WHERE `email` = ? LIMIT 1', [$email]);
        if ($existing !== null && (int) $existing['type'] === 4) {
            $center = Database::selectOne('SELECT `active` FROM `centers` WHERE `user_id` = ? LIMIT 1', [(int) $existing['id']]);
            if ($center === null || (int) $center['active'] !== 1) {
                return 403;
            }
        }
        return 401;
    }

    /** @return array{ok:bool,code:int} code: 200 | 401 | 403 (original semantics) */
    public static function attempt(string $email, string $password): array
    {
        $user = self::credentials($email, $password);
        if ($user === null) {
            return ['ok' => false, 'code' => self::loginFailureCode($email)];
        }
        self::login($user);
        return ['ok' => true, 'code' => 200];
    }

    /**
     * Complete login after the MFA challenge verified. Public wrapper around
     * the private login() (regenerate session, rotate CSRF, set identity).
     */
    public static function completeLogin(array $user): void
    {
        self::login($user);
    }

    private static function login(array $user): void
    {
        Session::regenerate(); // new session id (anti session fixation)
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

    // ---------------------------------------------------------------
    // Row Level Security (MySQL has no native RLS — application level).
    // center users (type 4) are scoped through centers.user_id = users.id;
    // admins see every row. Each helper throws HttpException(403) (or 404
    // for a nonexistent row) so no data can be read or written across the
    // caller's center boundary — the backend of every IDOR guard.
    // ---------------------------------------------------------------

    /** 403 unless the caller may access the given center. */
    public static function assertCenterAccess(int $centerId): void
    {
        if (self::isAdmin()) {
            return;
        }
        if ($centerId <= 0 || (int) self::centerId() !== $centerId) {
            throw new HttpException(403, 'This action is unauthorized.');
        }
    }

    /** 403 unless the caller may access the institute (via its owning center). */
    public static function assertInstituteAccess(int $instituteId): void
    {
        if (self::isAdmin()) {
            return;
        }
        if ($instituteId <= 0) {
            throw new HttpException(403, 'This action is unauthorized.');
        }
        $row = Database::selectOne('SELECT `center_id` FROM `institutes` WHERE `id` = ? LIMIT 1', [$instituteId]);
        if ($row === null) {
            throw new HttpException(404, 'Record not found');
        }
        self::assertCenterAccess((int) $row['center_id']);
    }

    /**
     * 403 unless EVERY given student id belongs to the caller's center.
     * Bulk ids are checked in one DISTINCT query (mark-entry submits).
     */
    public static function assertStudentAccess(int|array $studentIds): void
    {
        if (self::isAdmin()) {
            return;
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $studentIds), fn ($i) => $i > 0)));
        if ($ids === []) {
            throw new HttpException(403, 'This action is unauthorized.');
        }
        $rows = Database::select(
            'SELECT DISTINCT `center_id` FROM `students` WHERE `id` IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
            $ids
        );
        $mine = (int) self::centerId();
        foreach ($rows as $r) {
            if ((int) $r['center_id'] !== $mine) {
                throw new HttpException(403, 'This action is unauthorized.');
            }
        }
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
              WHERE mr.`model_type` = ? AND mr.`model_id` = ?",
            ['App\\Models\\User', $id]
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
