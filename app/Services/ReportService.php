<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Cache;
use App\Core\Database;
use App\Core\Query;

/**
 * Report service — data layer for the report + certificate endpoints from the
 * original ReportController. All queries use prepared statements
 * (Query builder / Database::select with bindings).
 *
 * Paginator JSON shape preserved: {current_page, data, first_page_url, from,
 * last_page, last_page_url, links, next_page_url, path, per_page,
 * prev_page_url, to, total}.
 *
 * High-traffic considerations (Part 1.3):
 * - Cache dropdown data with Cache::remember() (TTL 60s, prefix invalidation)
 * - Single JOIN queries for report sheets (no N+1)
 * - Indexes added to schema.sql for result-day queries
 */
final class ReportService
{
    /** Exam-class display order used by every original report (6=KG first). */
    public const CLASS_ORDER = 6;

    /** SQL fragment for the original class ordering (KG,One,Two,Three,Four,Five). */
    public const CLASS_ORDER_SQL = 'FIELD(`students`.`class`,6,1,2,3,4,5)';

    // ---------------------------------------------------------------
    // Student list report (GET /api/report-student-list)
    // ---------------------------------------------------------------

    /**
     * Student list for reports — filtered by session/center/institute/class/
     * roll/registration. Rows carry joined center/institute/division/district/
     * upazila/union names in one query. Pagination Laravel-shape.
     */
    public static function studentList(array $filters = []): array
    {
        $q = (new Query('students'))->select(
            'students.*',
            'users.name as center_name',
            'institutes.name as institute_name',
            'divisions.name as division_name',
            'districts.name as district_name',
            'upozilas.name as upazila_name',
            'unions.name as union_name'
        )
          ->join('centers', 'centers.id', '=', 'students.center_id')
          ->join('users', 'users.id', '=', 'centers.user_id')
          ->join('institutes', 'institutes.id', '=', 'students.institute_id')
          ->leftJoin('divisions', 'divisions.id', '=', 'students.division_id')
          ->leftJoin('districts', 'districts.id', '=', 'students.district_id')
          ->leftJoin('upozilas', 'upozilas.id', '=', 'students.upazila_id')
          ->leftJoin('unions', 'unions.id', '=', 'students.union_id')
          ->whereGroup(function (Query $query): void {
              // Centre-user scoping
              $centerId = Auth::centerId();
              if ($centerId !== null && !Auth::isAdmin()) {
                  $query->where('students.center_id', $centerId);
              }
          });

        // Filters
        if (($session = $filters['session'] ?? null) !== null && $session !== '') {
            $q->where('students.session', (int) $session);
        }
        if (($class = $filters['class'] ?? null) !== null && $class !== '') {
            $q->where('students.class', (int) $class);
        }
        if (($instituteId = $filters['institute_id'] ?? null) !== null && $instituteId !== '') {
            $q->where('students.institute_id', (int) $instituteId);
        }
        if (($centerId = $filters['center_id'] ?? null) !== null && $centerId !== '') {
            $q->where('students.center_id', (int) $centerId);
        }
        if (($roll = $filters['roll'] ?? null) !== null && $roll !== '') {
            $q->where('students.roll', (int) $roll);
        }
        if (($registration = $filters['registration'] ?? null) !== null && $registration !== '') {
            $q->where('students.registration', $registration);
        }

        $q->orderBy('students.center_id', 'asc')
          ->orderBy('students.institute_id', 'asc')
          ->orderBy('students.registration', 'asc');

        return $q->paginate((int) ($filters['per_page'] ?? 20), null, '/reports/student-list');
    }

    // ---------------------------------------------------------------
    // Config / dropdown data (cached)
    // ---------------------------------------------------------------

    /** Full marks config for a session. GET /api/fullmarks equivalent. */
    public static function fullmarks(int $session): array|false
    {
        return Database::selectOne('SELECT * FROM `marks` WHERE `session` = ? LIMIT 1', [(int) $session]) ?: false;
    }

    /** Active center rows (id + name) for report dropdowns. Cache 60s. */
    public static function centersCache(): array
    {
        return Cache::remember('report_centers', 60, function () {
            return Database::select(
                'SELECT c.`id`, u.`name` FROM `centers` c
                   JOIN `users` u ON u.`id` = c.`user_id`
                  WHERE c.`active` = 1
                  ORDER BY u.`name`'
            );
        });
    }

    /** Institute list filtered by center_id. Cache 60s. */
    public static function institutesByCenterCache(int $centerId): array
    {
        return Cache::remember('report_institutes_center:' . $centerId, 60, function () use ($centerId) {
            return Database::select(
                'SELECT `id`, `name` FROM `institutes` WHERE `center_id` = ? AND `active` = 1 ORDER BY `name`',
                [(int) $centerId]
            );
        });
    }

    // ---------------------------------------------------------------
    // Report sheet queries (single JOIN — no N+1)
    // ---------------------------------------------------------------

    /**
     * All students of a center for the active session, joined with institute
     * name, ordered institute → exam class → roll. Used by the center-wise
     * details / attend-sheet family of reports.
     */
    public static function studentsByCenter(int $centerId, string|int $session, bool $withPhoto = true): array
    {
        $q = (new Query('students'))
            ->select('students.*', 'institutes.name as institute_name')
            ->leftJoin('institutes', 'institutes.id', '=', 'students.institute_id')
            ->where('students.session', (int) $session)
            ->where('students.center_id', (int) $centerId)
            ->orderByRaw('`institutes`.`name` ASC')
            ->orderByRaw(self::CLASS_ORDER_SQL)
            ->orderBy('students.roll', 'asc');

        if (!$withPhoto) {
            $q->select(
                'students.id', 'students.name', 'students.roll', 'students.registration',
                'students.class', 'students.session', 'students.center_id', 'students.institute_id',
                'students.father_name', 'students.mother_name', 'students.gender',
                'students.education_type', 'students.division_id', 'students.district_id',
                'students.upazila_id', 'institutes.name as institute_name'
            );
        }
        return $q->get();
    }

    /** All students of an institute for the active session (certificate/admit reports). */
    public static function studentsByInstitute(int $instituteId, string|int $session, ?int $class = null): array
    {
        $q = (new Query('students'))
            ->select('students.*', 'institutes.name as institute_name', 'users.name as center_name')
            ->leftJoin('institutes', 'institutes.id', '=', 'students.institute_id')
            ->join('centers', 'centers.id', '=', 'students.center_id')
            ->join('users', 'users.id', '=', 'centers.user_id')
            ->where('students.session', (int) $session)
            ->where('students.institute_id', (int) $instituteId)
            ->orderBy('students.roll', 'asc');

        if ($class !== null) {
            $q->where('students.class', (int) $class);
        }
        return $q->get();
    }

    /** Grade 1–3 students of a center (for certificate sheets / grade-sheet reports). */
    public static function certifiedStudentsByCenter(int $centerId, string|int $session): array
    {
        return (new Query('students'))
            ->select('students.*', 'institutes.name as institute_name')
            ->leftJoin('institutes', 'institutes.id', '=', 'students.institute_id')
            ->where('students.session', (int) $session)
            ->where('students.center_id', (int) $centerId)
            ->whereIn('students.grade', [1, 2, 3])
            ->orderByRaw(self::CLASS_ORDER_SQL)
            ->orderBy('students.total', 'desc')
            ->get();
    }

    /** Grade 1–3 students (center-wide or one institute). */
    public static function certifiedStudents(int $centerId, string|int $session, ?int $instituteId = null): array
    {
        $q = (new Query('students'))
            ->select('students.*', 'institutes.name as institute_name')
            ->leftJoin('institutes', 'institutes.id', '=', 'students.institute_id')
            ->where('students.session', (int) $session)
            ->where('students.center_id', (int) $centerId)
            ->whereIn('students.grade', [1, 2, 3]);
        if ($instituteId !== null && $instituteId > 0) {
            $q->where('students.institute_id', (int) $instituteId);
        }
        return $q
            ->orderByRaw('`institutes`.`name` ASC')
            ->orderByRaw(self::CLASS_ORDER_SQL)
            ->orderBy('students.total', 'desc')
            ->get();
    }

    /** Merit-list students (ml 1–3); center-wide or one institute. */
    public static function meritStudents(int $centerId, string|int $session, ?int $instituteId = null): array
    {
        $q = (new Query('students'))
            ->select('students.*', 'institutes.name as institute_name', 'users.name as center_name')
            ->leftJoin('institutes', 'institutes.id', '=', 'students.institute_id')
            ->join('centers', 'centers.id', '=', 'students.center_id')
            ->join('users', 'users.id', '=', 'centers.user_id')
            ->where('students.session', (int) $session)
            ->whereIn('students.ml', [1, 2, 3]);
        if ($centerId > 0) {
            $q->where('students.center_id', (int) $centerId);
        }
        if ($instituteId !== null && $instituteId > 0) {
            $q->where('students.institute_id', (int) $instituteId);
        }
        return $q
            ->orderByRaw(self::CLASS_ORDER_SQL)
            ->orderBy('students.ml', 'asc')
            ->orderBy('users.name', 'asc')
            ->orderBy('institutes.name', 'asc')
            ->orderBy('students.roll', 'asc')
            ->get();
    }

    // ---------------------------------------------------------------
    // Counts (one grouped query per scope — no N+1)
    // ---------------------------------------------------------------

    /**
     * Class-wise student counts for a scope: session, optional center,
     * optional institute. Returns {kg,one,two,three,four,five,total}.
     */
    public static function classCounts(
        string|int $session,
        ?int $centerId = null,
        ?int $instituteId = null
    ): array {
        $where = '`session` = :s';
        $params = [':s' => (int) $session];
        if ($centerId !== null) {
            $where .= ' AND `center_id` = :c';
            $params[':c'] = (int) $centerId;
        }
        if ($instituteId !== null && $instituteId > 0) {
            $where .= ' AND `institute_id` = :i';
            $params[':i'] = (int) $instituteId;
        }
        $rows = Database::select(
            "SELECT `class`, COUNT(*) AS c FROM `students` WHERE $where GROUP BY `class`",
            $params
        );
        $byClass = [];
        foreach ($rows as $r) {
            $byClass[(int) $r['class']] = (int) $r['c'];
        }
        $total = array_sum($byClass);
        return [
            'kg' => $byClass[6] ?? 0,
            'one' => $byClass[1] ?? 0,
            'two' => $byClass[2] ?? 0,
            'three' => $byClass[3] ?? 0,
            'four' => $byClass[4] ?? 0,
            'five' => $byClass[5] ?? 0,
            'total' => $total,
        ];
    }

    /** Grade-wise counts for a scope (tp=1, gg=2, sg=3, ab=4). */
    public static function gradeCounts(
        string|int $session,
        ?int $centerId = null,
        ?int $instituteId = null
    ): array {
        $where = '`session` = :s';
        $params = [':s' => (int) $session];
        if ($centerId !== null) {
            $where .= ' AND `center_id` = :c';
            $params[':c'] = (int) $centerId;
        }
        if ($instituteId !== null && $instituteId > 0) {
            $where .= ' AND `institute_id` = :i';
            $params[':i'] = (int) $instituteId;
        }
        $rows = Database::select(
            "SELECT `grade`, COUNT(*) AS c FROM `students` WHERE $where GROUP BY `grade`",
            $params
        );
        $byGrade = [];
        foreach ($rows as $r) {
            $byGrade[(int) $r['grade']] = (int) $r['c'];
        }
        return [
            'tp' => $byGrade[1] ?? 0,
            'gg' => $byGrade[2] ?? 0,
            'sg' => $byGrade[3] ?? 0,
            'ab' => $byGrade[4] ?? 0,
            'tot' => ($byGrade[1] ?? 0) + ($byGrade[2] ?? 0) + ($byGrade[3] ?? 0),
            'total' => array_sum($byGrade),
        ];
    }

    /** Merit-position counts (ml 1/2/3) for a scope. */
    public static function meritCounts(string|int $session, ?int $centerId = null): array
    {
        $where = '`session` = :s';
        $params = [':s' => (int) $session];
        if ($centerId !== null) {
            $where .= ' AND `center_id` = :c';
            $params[':c'] = (int) $centerId;
        }
        $rows = Database::select(
            "SELECT `ml`, COUNT(*) AS c FROM `students` WHERE $where AND `ml` IN (1,2,3) GROUP BY `ml`",
            $params
        );
        $byMl = [];
        foreach ($rows as $r) {
            $byMl[(int) $r['ml']] = (int) $r['c'];
        }
        return [
            'first' => $byMl[1] ?? 0,
            'second' => $byMl[2] ?? 0,
            'third' => $byMl[3] ?? 0,
            'tot' => ($byMl[1] ?? 0) + ($byMl[2] ?? 0) + ($byMl[3] ?? 0),
        ];
    }

    /** Centers participating in a session: {centerId => centerName} (users.name). */
    public static function centersBySession(string|int $session): array
    {
        $rows = Database::select(
            'SELECT DISTINCT c.`id`, u.`name`
               FROM `students` s
               JOIN `centers` c ON c.`id` = s.`center_id`
               JOIN `users` u ON u.`id` = c.`user_id`
              WHERE s.`session` = ?
              ORDER BY u.`name`',
            [(int) $session]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['id']] = (int) $r['id'] . ' - ' . $r['name'];
        }
        return $rows;
    }

    // ---------------------------------------------------------------
    // Named lookups for report headers
    // ---------------------------------------------------------------

    /** Center row joined with the users.name of the authority. */
    public static function centerWithUser(int $centerId): ?array
    {
        return Database::selectOne(
            'SELECT c.*, u.`name` AS center_name, u.`phone`, u.`email`
               FROM `centers` c
               JOIN `users` u ON u.`id` = c.`user_id`
              WHERE c.`id` = ? LIMIT 1',
            [(int) $centerId]
        );
    }

    /** Center name (users.name) — used for report file names. */
    public static function centerName(int $centerId): string
    {
        $row = Database::selectOne(
            'SELECT u.`name` FROM `centers` c JOIN `users` u ON u.`id` = c.`user_id` WHERE c.`id` = ? LIMIT 1',
            [(int) $centerId]
        );
        return $row['name'] ?? '';
    }

    /** All active institutes with their center name. */
    public static function institutesWithCenter(?int $centerId = null, ?int $membershipType = null): array
    {
        $sql = 'SELECT i.*, u.`name` AS center_name
                  FROM `institutes` i
             LEFT JOIN `centers` c ON c.`id` = i.`center_id`
             LEFT JOIN `users` u ON u.`id` = c.`user_id`';
        $wheres = [];
        $params = [];
        if ($centerId !== null) {
            $wheres[] = 'c.`id` = ?';
            $params[] = (int) $centerId;
        }
        if ($membershipType !== null) {
            $wheres[] = 'i.`membership_type` = ?';
            $params[] = (int) $membershipType;
        }
        if ($wheres !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $wheres);
        }
        $sql .= ' ORDER BY u.`name` ASC, i.`name` ASC';
        return Database::select($sql, $params);
    }

    /** All centers joined with authority user (AllCenterDetailsInformationReport). */
    public static function allCenters(): array
    {
        return Database::select(
            'SELECT c.*, u.`name` AS center_name, u.`phone`, u.`email`
               FROM `centers` c
               JOIN `users` u ON u.`id` = c.`user_id`
              ORDER BY u.`name` ASC'
        );
    }

    // ---------------------------------------------------------------
    // Mark entry payload helper (kept for /api/markentrystudent/updateTotalNumbers)
    // ---------------------------------------------------------------

    /** Mark entry payload shape: {students:[{id,bangla,english,math,total,percentage,grade,ml}]} */
    public static function markEntryPayload(array $students): array
    {
        $payload = ['students' => []];
        foreach ($students as $s) {
            $payload['students'][] = [
                'id' => $s['id'] ?? null,
                'bangla' => $s['bangla'] ?? 0,
                'english' => $s['english'] ?? 0,
                'math' => $s['math'] ?? 0,
                'total' => $s['total'] ?? 0,
                'percentage' => $s['percentage'] ?? 0.00,
                'grade' => $s['grade'] ?? 0,
                'ml' => $s['ml'] ?? 0,
            ];
        }
        return $payload;
    }
}