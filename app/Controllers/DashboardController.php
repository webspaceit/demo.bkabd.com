<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Cache;
use App\Core\Database;
use App\Core\Query;
use App\Models\Center;
use App\Models\District;
use App\Models\Division;
use App\Models\Institute;
use App\Models\Notice;
use App\Models\Student;
use App\Models\Upazila;
use App\Models\User;

/**
 * Dashboard statistics — ported from original DashboardController::dashboardcount.
 *
 * Response keys identical to the original Dashboard.vue contract:
 *   totalstudents, totalinstitutes, totalcenter, totaldivision, totaldistrict,
 *   totalupazila, totalnotice, totaluseraccess, attendcenter, attendinstitute.
 *
 * Center users (type 4) get counts scoped to the centers they manage;
 * admin users get global counts (original behaviour kept 1:1).
 */
final class DashboardController extends Controller
{
    /** GET /api/dashboard — counts for the current exam session. */
    public function dashboardcount(): array
    {
        $session  = (int) Auth::activeSession();
        $centerUser = !Auth::isAdmin();
        $userId   = (int) Auth::id();

        // Dashboard totals are read-only and can be shared briefly between
        // page loads. Scope the cache by session and user to avoid mixing
        // center-specific totals with admin or another center's results.
        $cacheKey = 'dashboard_stats:' . $session . ':' . ($centerUser ? 'center:' . $userId : 'admin');
        return Cache::remember($cacheKey, 30, fn(): array => $this->buildDashboardStats($session, $centerUser, $userId));
    }

    /** Calculate the dashboard totals on a cache miss. */
    private function buildDashboardStats(int $session, bool $centerUser, int $userId): array
    {

        if ($centerUser) {
            // Center user: students of their centers in the active session.
            $totalstudents = (new Query('students'))
                ->join('centers', 'centers.id', '=', 'students.center_id')
                ->where('centers.user_id', $userId)
                ->where('students.session', $session)
                ->count();

            // Center user: institutes registered under their centers.
            $totalinstitutes = (new Query('institutes'))
                ->join('centers', 'centers.id', '=', 'institutes.center_id')
                ->where('centers.user_id', $userId)
                ->count();

            // Centers that actually have students in the active session.
            $attendcenter = (int) (Database::selectOne(
                'SELECT COUNT(DISTINCT s.`center_id`) AS c
                   FROM `students` s
                   JOIN `centers` c ON c.`id` = s.`center_id`
                  WHERE c.`user_id` = ? AND s.`session` = ?',
                [$userId, $session]
            )['c'] ?? 0);

            // Institutes that actually have students in the active session.
            $attendinstitute = (int) (Database::selectOne(
                'SELECT COUNT(DISTINCT s.`institute_id`) AS c
                   FROM `students` s
                   JOIN `centers` c ON c.`id` = s.`center_id`
                  WHERE c.`user_id` = ? AND s.`session` = ?',
                [$userId, $session]
            )['c'] ?? 0);
        } else {
            $totalstudents = (new Query('students'))->where('session', $session)->count();
            $totalinstitutes = Institute::count();

            $attendcenter = (int) (Database::selectOne(
                'SELECT COUNT(DISTINCT `center_id`) AS c FROM `students` WHERE `session` = ?',
                [$session]
            )['c'] ?? 0);

            $attendinstitute = (int) (Database::selectOne(
                'SELECT COUNT(DISTINCT `institute_id`) AS c FROM `students` WHERE `session` = ?',
                [$session]
            )['c'] ?? 0);
        }

        return [
            'totalstudents'   => $totalstudents,
            'totalinstitutes' => $totalinstitutes,
            'totalcenter'     => Center::count(),
            'totaldivision'   => Division::count(),
            'totaldistrict'   => District::count(),
            'totalupazila'    => Upazila::count(),
            'totalnotice'     => Notice::count(),
            'totaluseraccess' => User::count(),
            'attendcenter'    => $attendcenter,
            'attendinstitute' => $attendinstitute,
        ];
    }
}
