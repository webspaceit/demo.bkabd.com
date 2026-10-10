<?php

namespace App\Services;

use App\Core\Cache;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;

/**
 * Public result portal data layer.
 *
 * Optimizations for result day (~10k concurrent users):
 *  - dropdown lists cached 60s (result rows change rarely → TTL invalidation),
 *  - one prepared JOIN query for the whole result sheet (no N+1),
 *  - found result rows cached 60s (popular students are hit repeatedly),
 *  - indexed lookups: students.registration (UNIQUE),
 *    unique_roll_session_class (session, roll, class), idx_session_center_class,
 *  - lightweight per-IP file throttle on the search POST (no DB/session writes).
 */
final class ResultPortalService
{
    private const CACHE_TTL = 60;

    // ---------------------------------------------------------------
    // Dropdown data (cached under own prefixes — invalidated by TTL)
    // ---------------------------------------------------------------

    /** Sessions whose results are listed in the search form (original: Session::where('result',1)->orderBy session desc). */
    public static function resultSessions(): array
    {
        return Cache::remember('result_sessions:list', self::CACHE_TTL, fn(): array => Database::select(
            'SELECT `session` FROM `sessions` WHERE `result` = 1 ORDER BY `session` DESC'
        ));
    }

    public static function divisions(): array
    {
        return Cache::remember('result_divisions:list', self::CACHE_TTL, fn(): array => Database::select(
            'SELECT `id`, `name` FROM `divisions` ORDER BY `name` ASC'
        ));
    }

    /** Active centers (original: users ⋈ centers WHERE centers.active = 1, ordered by users.name). */
    public static function centers(): array
    {
        return Cache::remember('result_centers:list', self::CACHE_TTL, fn(): array => Database::select(
            'SELECT `centers`.`id`, `users`.`name` '
            . 'FROM `users` INNER JOIN `centers` ON `users`.`id` = `centers`.`user_id` '
            . 'WHERE `centers`.`active` = 1 ORDER BY `users`.`name` ASC'
        ));
    }

    // ---------------------------------------------------------------
    // Gate — original ResultController checks `result_time < NOW()`;
    // `result = 1` is also enforced here (the form dropdown only lists
    // result=1 sessions, a hand-crafted POST must not bypass that).
    // ---------------------------------------------------------------

    /** @return array{session:int,result_time:string}|null Row when the session's result is published, else null. */
    public static function gate(string $session): ?array
    {
        return Database::selectOne(
            'SELECT `session`, `result_time` FROM `sessions` '
            . 'WHERE `session` = ? AND `result` = 1 AND `result_time` < NOW() LIMIT 1',
            [$session]
        );
    }

    // ---------------------------------------------------------------
    // Result lookup
    // ---------------------------------------------------------------

    /**
     * Locate the student + all display names in ONE prepared JOIN query.
     *
     * Registration is UNIQUE, so when it is supplied it alone identifies the
     * student. Otherwise the (session, class, roll) UNIQUE index is used as a
     * fallback for students who only hold their admit card / roll number.
     * Found rows are cached briefly (marks are frozen once results publish).
     *
     * @param array{registration?:string,session_id?:string|int,session?:string|int,class?:string|int,roll?:string|int} $input
     * @return array<string,mixed>|null
     */
    public static function findStudent(array $input): ?array
    {
        $registration = trim((string) ($input['registration'] ?? ''));

        if ($registration !== '') {
            $key = 'result_student:reg:' . sha1($registration);
            $where = 'WHERE s.`registration` = ?';
            $params = [$registration];
        } else {
            $session = (string) ($input['session_id'] ?? $input['session'] ?? '');
            $class = (string) ($input['class'] ?? '');
            $roll = (string) ($input['roll'] ?? '');
            $key = 'result_student:roll:' . sha1($session . '|' . $class . '|' . $roll);
            $where = 'WHERE s.`session` = ? AND s.`class` = ? AND s.`roll` = ?';
            $params = [$session, $class, $roll];
        }

        return Cache::remember($key, self::CACHE_TTL, function () use ($where, $params): ?array {
            return Database::selectOne(
                'SELECT s.`session`, s.`roll`, s.`registration`, s.`class`, s.`center_id`, s.`division_id`, '
                . 's.`name`, s.`father_name`, s.`mother_name`, s.`dob`, '
                . 's.`bangla`, s.`english`, s.`math`, s.`total`, s.`percentage`, s.`grade`, s.`ml`, '
                . 'd.`name` AS division_name, dt.`name` AS district_name, uz.`name` AS upazila_name, '
                . 'cu.`name` AS center_name, i.`name` AS institute_name '
                . 'FROM `students` s '
                . 'LEFT JOIN `divisions` d ON d.`id` = s.`division_id` '
                . 'LEFT JOIN `districts` dt ON dt.`id` = s.`district_id` '
                . 'LEFT JOIN `upozilas` uz ON uz.`id` = s.`upazila_id` '
                . 'LEFT JOIN `centers` c ON c.`id` = s.`center_id` '
                . 'LEFT JOIN `users` cu ON cu.`id` = c.`user_id` '
                . 'LEFT JOIN `institutes` i ON i.`id` = s.`institute_id` '
                . $where
                . ' LIMIT 1',
                $params
            );
        });
    }

    /** Full marks config for a session (original: marks->where('session')->first()). Cached 60s. */
    public static function fullMarks(string $session): ?array
    {
        return Cache::remember('result_fullmarks:' . $session, self::CACHE_TTL, fn(): ?array => Database::selectOne(
            'SELECT `bangla`, `english`, `math`, `total` FROM `marks` WHERE `session` = ? ORDER BY `id` LIMIT 1',
            [$session]
        ));
    }

    // ---------------------------------------------------------------
    // Abuse protection
    // ---------------------------------------------------------------

    /**
     * Max 30 search POSTs per IP per minute — tiny file counter under
     * storage/cache (no DB row, no session lock). Over the limit → 429
     * (JSON for Ajax, error page for plain posts).
     */
    public static function assertSearchThrottle(Request $request): void
    {
        $max = 30;
        $window = 60;
        $dir = BASE_PATH . '/storage/cache/throttle';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir . '/' . sha1($request->ip()) . '.json';
        $now = time();

        $start = $now;
        $count = 0;
        $raw = @file_get_contents($file);
        $row = $raw === false ? null : json_decode($raw, true);
        if (is_array($row) && isset($row['start'], $row['count'])
            && $now - (int) $row['start'] < $window) {
            $start = (int) $row['start'];
            $count = (int) $row['count'];
        }
        $count++;

        // Opportunistic GC: drop counter files abandoned 10+ minutes ago.
        if (mt_rand(1, 50) === 1) {
            foreach (glob($dir . '/*.json') ?: [] as $stale) {
                if (@filemtime($stale) < $now - 600) {
                    @unlink($stale);
                }
            }
        }

        @file_put_contents($file, json_encode(['start' => $start, 'count' => $count]), LOCK_EX);

        if ($count > $max) {
            throw new HttpException(429, 'Too many result searches. Please wait a minute and try again.');
        }
    }
}
