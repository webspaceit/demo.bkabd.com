<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Query;

/**
 * Data layer for the report sheets (ported from the original
 * ReportController report methods).
 *
 * Every query is a single prepared statement (no N+1). Row shape mirrors the
 * original Eloquent results: students.* + `institute_name` / `center_name`.
 */
final class ReportSheetService
{
    /** Class display order used by the original reports (KG first). */
    private const FIELD_CLASS = 'FIELD(`students`.`class`,6,1,2,3,4,5)';

    /** Class display order for queries that alias `students` as `s`. */
    private const FIELD_CLASS_S = 'FIELD(`s`.`class`,6,1,2,3,4,5)';

    /**
     * Student rows for a report scope.
     *
     * Supported keys: session, center_id, institute_id, class, grades[], mls[],
     * orders[][] (['col'=>,'dir'=>] or ['raw'=>]).
     */
    public static function rows(array $o): array
    {
        $session = $o['session'] ?? Auth::activeSession();

        $q = (new Query('students'))
            ->select('students.*', 'institutes.name as institute_name', 'users.name as center_name')
            ->leftJoin('institutes', 'institutes.id', '=', 'students.institute_id')
            ->leftJoin('centers', 'centers.id', '=', 'students.center_id')
            ->leftJoin('users', 'users.id', '=', 'centers.user_id')
            ->where('students.session', (int) $session);

        if (!empty($o['center_id'])) {
            $q->where('students.center_id', (int) $o['center_id']);
        }
        if (!empty($o['institute_id'])) {
            $q->where('students.institute_id', (int) $o['institute_id']);
        }
        if (!empty($o['class'])) {
            $q->where('students.class', (int) $o['class']);
        }
        if (!empty($o['grades'])) {
            $q->whereIn('students.grade', array_map('intval', $o['grades']));
        }
        if (!empty($o['mls'])) {
            $q->whereIn('students.ml', array_map('intval', $o['mls']));
        }

        $orders = $o['orders'] ?? [
            ['col' => 'institutes.name', 'dir' => 'asc'],
            ['raw' => self::FIELD_CLASS],
            ['col' => 'students.roll', 'dir' => 'asc'],
        ];
        foreach ($orders as $ord) {
            if (isset($ord['raw'])) {
                $q->orderByRaw($ord['raw']);
            } else {
                $q->orderBy($ord['col'], $ord['dir'] ?? 'asc');
            }
        }

        return $q->get();
    }

    /** Highest-marks ordering: class first, then total desc (original). */
    public static function orderClassThenTotal(): array
    {
        return [
            ['raw' => self::FIELD_CLASS],
            ['col' => 'students.total', 'dir' => 'desc'],
        ];
    }

    /** Merit ordering: class, merit position, center, institute, roll (original). */
    public static function orderMerit(): array
    {
        return [
            ['raw' => self::FIELD_CLASS],
            ['col' => 'students.ml', 'dir' => 'asc'],
            ['col' => 'users.name', 'dir' => 'asc'],
            ['col' => 'institutes.name', 'dir' => 'asc'],
            ['col' => 'students.roll', 'dir' => 'asc'],
        ];
    }

    // ---------------------------------------------------------------
    // Named lookups
    // ---------------------------------------------------------------

    public static function instituteName(int $id): string
    {
        $row = Database::selectOne('SELECT `name` FROM `institutes` WHERE `id` = ? LIMIT 1', [$id]);
        return (string) ($row['name'] ?? '');
    }

    // ---------------------------------------------------------------
    // Center info (original centerinfo)
    // ---------------------------------------------------------------

    /** Per-center class counts for the session. */
    public static function centerClassCounts(string|int $session): array
    {
        return Database::select(
            "SELECT c.`id`, u.`name` AS group_name,
                    COALESCE(SUM(s.`class` = 6), 0) AS kg,
                    COALESCE(SUM(s.`class` = 1), 0) AS one,
                    COALESCE(SUM(s.`class` = 2), 0) AS two,
                    COALESCE(SUM(s.`class` = 3), 0) AS three,
                    COALESCE(SUM(s.`class` = 4), 0) AS four,
                    COALESCE(SUM(s.`class` = 5), 0) AS five,
                    COUNT(s.`id`) AS total
               FROM `centers` c
               JOIN `users` u ON u.`id` = c.`user_id`
          LEFT JOIN `students` s ON s.`center_id` = c.`id` AND s.`session` = :s
           GROUP BY c.`id`, u.`name`
           ORDER BY u.`name` ASC",
            [':s' => (int) $session]
        );
    }

    /** Per-institute class counts, optionally for one center. */
    public static function instituteClassCounts(string|int $session, ?int $centerId = null): array
    {
        $sql = "SELECT i.`id`, i.`name` AS group_name, u.`name` AS center_name,
                       COALESCE(SUM(s.`class` = 6), 0) AS kg,
                       COALESCE(SUM(s.`class` = 1), 0) AS one,
                       COALESCE(SUM(s.`class` = 2), 0) AS two,
                       COALESCE(SUM(s.`class` = 3), 0) AS three,
                       COALESCE(SUM(s.`class` = 4), 0) AS four,
                       COALESCE(SUM(s.`class` = 5), 0) AS five,
                       COUNT(s.`id`) AS total
                  FROM `institutes` i
             LEFT JOIN `centers` c ON c.`id` = i.`center_id`
             LEFT JOIN `users` u ON u.`id` = c.`user_id`
             LEFT JOIN `students` s ON s.`institute_id` = i.`id` AND s.`session` = :s";
        $params = [':s' => (int) $session];
        if ($centerId !== null && $centerId > 0) {
            $sql .= ' WHERE i.`center_id` = :c';
            $params[':c'] = $centerId;
        }
        $sql .= ' GROUP BY i.`id`, i.`name`, u.`name` ORDER BY u.`name` ASC, i.`name` ASC';
        return Database::select($sql, $params);
    }

    // ---------------------------------------------------------------
    // Documents distribution (original DocumentsDistributionReport)
    // ---------------------------------------------------------------

    /** Center/class student counts grouped by division/district/upazila. */
    public static function documentsDistribution(string|int $session): array
    {
        return Database::select(
            "SELECT u.`name` AS center_name, d.`name` AS division, dt.`name` AS district,
                    uz.`name` AS upazila, s.`class`, COUNT(s.`id`) AS class_count
               FROM `centers` c
               JOIN `students` s ON s.`center_id` = c.`id`
               JOIN `users` u ON u.`id` = c.`user_id`
               JOIN `divisions` d ON d.`id` = c.`division_id`
               JOIN `districts` dt ON dt.`id` = c.`district_id`
               JOIN `upozilas` uz ON uz.`id` = c.`upazila_id`
              WHERE s.`session` = :s
           GROUP BY u.`name`, d.`name`, dt.`name`, uz.`name`, s.`class`
           ORDER BY d.`name` ASC, dt.`name` ASC, uz.`name` ASC, u.`name` ASC, " . self::FIELD_CLASS_S,
            [':s' => (int) $session]
        );
    }

    // ---------------------------------------------------------------
    // Educational type counts (original EducationalTypeStudentCountReport)
    // ---------------------------------------------------------------

    /** Per center + class counts split by education type. */
    public static function educationalType(string|int $session): array
    {
        return Database::select(
            "SELECT u.`name` AS center_name, s.`class`,
                    COALESCE(SUM(s.`education_type` = 1), 0) AS bangla,
                    COALESCE(SUM(s.`education_type` = 2), 0) AS english,
                    COALESCE(SUM(s.`education_type` = 3), 0) AS arabic,
                    COUNT(s.`id`) AS total
               FROM `centers` c
          LEFT JOIN `users` u ON u.`id` = c.`user_id`
          LEFT JOIN `students` s ON s.`center_id` = c.`id` AND s.`session` = :s
              WHERE s.`education_type` IS NOT NULL AND s.`education_type` <> ''
           GROUP BY u.`name`, s.`class`
           ORDER BY u.`name` ASC, " . self::FIELD_CLASS_S,
            [':s' => (int) $session]
        );
    }

    // ---------------------------------------------------------------
    // Center-wise grade / merit counts
    // ---------------------------------------------------------------

    /** Per-center grade + merit counts (original CenterWiseGradeListMeritPositionCountReport). */
    public static function centerGradeMerit(string|int $session): array
    {
        // Session is an integer; inlined so the placeholder is not reused
        // (PDO native prepares reject a repeated named marker).
        $s = (int) $session;
        return Database::select(
            "SELECT c.`id`, u.`name` AS center_name,
                    COUNT(CASE WHEN s.`session` = $s THEN s.`id` END) AS students_count,
                    COUNT(CASE WHEN s.`session` = $s AND s.`grade` <> 4 THEN s.`id` END) AS students_present,
                    COUNT(CASE WHEN s.`session` = $s AND s.`grade` = 4 THEN s.`id` END) AS students_absent,
                    COUNT(CASE WHEN s.`session` = $s AND s.`grade` = 1 THEN s.`id` END) AS tp,
                    COUNT(CASE WHEN s.`session` = $s AND s.`grade` = 2 THEN s.`id` END) AS gg,
                    COUNT(CASE WHEN s.`session` = $s AND s.`grade` = 3 THEN s.`id` END) AS sg,
                    COUNT(CASE WHEN s.`session` = $s AND s.`ml` = 1 THEN s.`id` END) AS position1,
                    COUNT(CASE WHEN s.`session` = $s AND s.`ml` = 2 THEN s.`id` END) AS position2,
                    COUNT(CASE WHEN s.`session` = $s AND s.`ml` = 3 THEN s.`id` END) AS position3
               FROM `centers` c
               JOIN `users` u ON u.`id` = c.`user_id`
          LEFT JOIN `students` s ON s.`center_id` = c.`id`
              WHERE s.`session` = $s
           GROUP BY c.`id`, u.`name`
           ORDER BY u.`name` ASC"
        );
    }

    // ---------------------------------------------------------------
    // Overall counts (for report headers)
    // ---------------------------------------------------------------

    /** Overall attendance + scholarship totals for a session. */
    public static function overallGradeMerit(string|int $session): array
    {
        $row = Database::selectOne(
            "SELECT COUNT(*) AS total,
                    COUNT(CASE WHEN `grade` <> 4 THEN 1 END) AS present,
                    COUNT(CASE WHEN `grade` = 4 THEN 1 END) AS absent,
                    COUNT(CASE WHEN `grade` = 1 THEN 1 END) AS tp,
                    COUNT(CASE WHEN `grade` = 2 THEN 1 END) AS gg,
                    COUNT(CASE WHEN `grade` = 3 THEN 1 END) AS sg,
                    COUNT(CASE WHEN `ml` = 1 THEN 1 END) AS position1,
                    COUNT(CASE WHEN `ml` = 2 THEN 1 END) AS position2,
                    COUNT(CASE WHEN `ml` = 3 THEN 1 END) AS position3
               FROM `students` WHERE `session` = ?",
            [(int) $session]
        ) ?: [];
        return array_map('intval', $row);
    }

    // ---------------------------------------------------------------
    // Center details (original AllCenterDetailsInformationReport)
    // ---------------------------------------------------------------

    /** All centers with authority + geo names, ordered by center name. */
    public static function allCenterDetails(): array
    {
        return Database::select(
            "SELECT c.`id`, c.`code`, c.`authority_person`, c.`active`,
                    u.`name` AS center_name, u.`phone`, u.`email`,
                    d.`name` AS division, dt.`name` AS district, uz.`name` AS upazila
               FROM `centers` c
               JOIN `users` u ON u.`id` = c.`user_id`
          LEFT JOIN `divisions` d ON d.`id` = c.`division_id`
          LEFT JOIN `districts` dt ON dt.`id` = c.`district_id`
          LEFT JOIN `upozilas` uz ON uz.`id` = c.`upazila_id`
           ORDER BY u.`name` ASC"
        );
    }
}
