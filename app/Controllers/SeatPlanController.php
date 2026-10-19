<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Student;
use App\Services\SeatAssignmentService;

final class SeatPlanController extends Controller
{
    /** Return room assignments for one session/center/class. */
    public function assignments(Request $request): array
    {
        [$session, $centerId, $class] = $this->assignmentScope(
            $request->query('session'), $request->query('center_id'), $request->query('class')
        );
        return ['assignments' => Database::select(
            'SELECT `id`, `building_name`, `floor_number`, `room_number`, `roll_start`, `roll_end`, `room_capacity` '
            . 'FROM `seat_plan_assignments` WHERE `session` = ? AND `center_id` = ? AND `class` = ? '
            . 'ORDER BY `sort_order` ASC, `id` ASC',
            [$session, $centerId, $class]
        )];
    }

    /** Replace all room assignments in a scope atomically. */
    public function saveAssignments(Request $request): array
    {
        [$session, $centerId, $class] = $this->assignmentScope(
            $request->input('session'), $request->input('center_id'), $request->input('class')
        );
        $rows = $request->input('assignments', []);
        if (!is_array($rows) || $rows === [] || count($rows) > 100) {
            throw new HttpException(422, 'Add at least one room assignment (maximum 100).');
        }

        $assignments = [];
        $ranges = [];
        foreach (array_values($rows) as $index => $row) {
            if (!is_array($row)) {
                throw new HttpException(422, 'Each room assignment must be an object.');
            }
            $building = trim((string) ($row['building_name'] ?? ''));
            $floor = trim((string) ($row['floor_number'] ?? ''));
            $room = trim((string) ($row['room_number'] ?? ''));
            if ($building === '' || $floor === '' || $room === '') {
                throw new HttpException(422, 'Building name, floor number, and room number are required.');
            }
            if (mb_strlen($building) > 120 || mb_strlen($floor) > 50 || mb_strlen($room) > 50) {
                throw new HttpException(422, 'Room assignment text is too long.');
            }

            $mode = (string) ($row['mode'] ?? '');
            if ($mode === 'range') {
                $start = filter_var($row['roll_start'] ?? null, FILTER_VALIDATE_INT);
                $end = filter_var($row['roll_end'] ?? null, FILTER_VALIDATE_INT);
                if ($start === false || $end === false || $start <= 0 || $end < $start) {
                    throw new HttpException(422, 'Enter a valid start and end roll for each roll-range room.');
                }
                foreach ($ranges as [$existingStart, $existingEnd]) {
                    if ($start <= $existingEnd && $end >= $existingStart) {
                        throw new HttpException(422, 'Roll ranges cannot overlap.');
                    }
                }
                $ranges[] = [$start, $end];
                $capacity = null;
            } elseif ($mode === 'capacity') {
                $start = $end = null;
                $capacity = filter_var($row['room_capacity'] ?? null, FILTER_VALIDATE_INT);
                if ($capacity === false || $capacity <= 0) {
                    throw new HttpException(422, 'Enter a valid positive room capacity.');
                }
            } else {
                throw new HttpException(422, 'Choose a roll range or room capacity for each room.');
            }

            $assignments[] = [
                'building_name' => $building,
                'floor_number' => $floor,
                'room_number' => $room,
                'roll_start' => $start,
                'roll_end' => $end,
                'room_capacity' => $capacity,
                'sort_order' => $index + 1,
            ];
        }

        Database::transaction(static function () use ($assignments, $session, $centerId, $class): void {
            Database::delete('seat_plan_assignments', '`session` = ? AND `center_id` = ? AND `class` = ?', [$session, $centerId, $class]);
            foreach ($assignments as $assignment) {
                Database::insert('seat_plan_assignments', $assignment + [
                    'session' => $session,
                    'center_id' => $centerId,
                    'class' => $class,
                    'user_id' => Auth::id(),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        });

        return ['status' => 'success', 'saved' => count($assignments)];
    }

    /** Validate scope and enforce center-level access. */
    private function assignmentScope(mixed $session, mixed $centerId, mixed $class): array
    {
        $session = filter_var($session, FILTER_VALIDATE_INT);
        $centerId = filter_var($centerId, FILTER_VALIDATE_INT);
        $class = filter_var($class, FILTER_VALIDATE_INT);
        if ($session === false || $session <= 0 || $centerId === false || $centerId <= 0
            || $class === false || !in_array($class, [1, 2, 3, 4, 5, 6], true)) {
            throw new HttpException(422, 'Select a valid session, center, and class.');
        }
        Auth::assertCenterAccess($centerId);
        if (Database::selectOne('SELECT `id` FROM `sessions` WHERE `session` = ? LIMIT 1', [$session]) === null) {
            throw new HttpException(404, 'Exam session not found.');
        }
        if (Database::selectOne('SELECT `id` FROM `centers` WHERE `id` = ? LIMIT 1', [$centerId]) === null) {
            throw new HttpException(404, 'Center not found.');
        }
        return [(int) $session, (int) $centerId, (int) $class];
    }

    /**
     * Fetch seat plan data
     */
    public function fetchSeatPlan(Request $request): mixed
    {
        if (!Auth::check()) {
            Response::json(['status' => 'errors'], 401);
        }
        if (!Auth::can('seatplan')) {
            Response::json(['message' => 'This action is unauthorized.'], 403);
        }

        $id = (int) $request->params['id']; // institute_id
        $class = (int) $request->params['class'];
        $activeSession = (int) $request->query('session', $this->activeSession());

        // RLS/BOLA: the institute must belong to the caller's center.
        Auth::assertInstituteAccess($id);

        // In original, seatplan is called with institute_id/class based on some routes,
        // but seatplan_print takes center_id/class - need to check context
        $students = Student::query()
            ->select('id', 'session', 'name', 'class', 'roll', 'center_id', 'institute_id')
            ->where('institute_id', $id)
            ->where('session', $activeSession)
            ->where('class', $class)
            // Approval workflow: pending registrations get no seat.
            ->where('active', 1)
            ->orderBy('roll', 'asc')
            ->get();

        // Return view for seatplan (HTML)
        return $this->renderSeatPlan($students, $activeSession, $id, $class);
    }

    /**
     * Print seat plan
     * Original: GET /api/seatplan_print/{center_id}/{class}
     */
    public function printSeatPlan(Request $request): mixed
    {
        if (!Auth::check()) {
            Response::json(['status' => 'errors'], 401);
        }
        if (!Auth::can('seatplan')) {
            Response::json(['message' => 'This action is unauthorized.'], 403);
        }

        $centerId = (int) $request->params['id'];
        $class = (int) $request->params['class'];
        $activeSession = (int) $request->query('session', $this->activeSession());

        // Center scoping for non-admin
        if (!Auth::isAdmin()) {
            $authCenterId = Auth::centerId();
            if ($authCenterId !== null && $authCenterId != $centerId) {
                Response::json(['message' => 'This action is unauthorized.'], 403);
            }
        }

        $students = Student::query()
            ->select('id', 'session', 'name', 'class', 'roll', 'center_id', 'institute_id')
            ->where('center_id', $centerId)
            ->where('session', $activeSession)
            ->where('class', $class)
            // Approval workflow: pending registrations get no seat.
            ->where('active', 1)
            ->orderBy('roll', 'asc')
            ->get();

        return $this->renderSeatPlanPrint(SeatAssignmentService::attachLocations($students), $activeSession);
    }

    /** Print compact seat-arrangement cards matching the supplied 8-up layout. */
    public function printSeatArrangementCards(Request $request): string
    {
        if (!Auth::check()) {
            Response::json(['status' => 'errors'], 401);
        }
        if (!Auth::can('seatplan')) {
            Response::json(['message' => 'This action is unauthorized.'], 403);
        }

        [$session, $centerId, $class] = $this->assignmentScope(
            $request->query('session'), $request->params['id'] ?? null, $request->params['class'] ?? null
        );
        $students = Database::select(
            "SELECT s.`id`, s.`session`, s.`name`, s.`class`, s.`roll`, s.`center_id`,
                    u.`name` AS center_name
               FROM `students` s
          LEFT JOIN `centers` c ON c.`id` = s.`center_id`
          LEFT JOIN `users` u ON u.`id` = c.`user_id`
              WHERE s.`session` = ? AND s.`center_id` = ? AND s.`class` = ?
                AND s.`active` = 1
           ORDER BY s.`roll` ASC, s.`id` ASC",
            [$session, $centerId, $class]
        );
        if ($students === []) {
            return '<!doctype html><html><body style="font:16px Arial;padding:2rem">No active students found for this session, center, and class.</body></html>';
        }

        return \App\Core\View::render('seatplan.cards', [
            'title' => 'Seat Arrangement Cards',
            'students' => SeatAssignmentService::attachLocations($students),
            'session' => $session,
            'className' => $this->getClassName($class),
        ]);
    }

    private function renderSeatPlan($students, $activeSession, $instituteId, $class): string
    {
        // Simple seat plan view - similar to what would be expected
        return $this->renderSeatPlanPrint(SeatAssignmentService::attachLocations($students), $activeSession);
    }

    private function renderSeatPlanPrint($students, $activeSession): string
    {
        $first = $students[0] ?? [];
        $class = (int) ($first['class'] ?? 0);
        $centerId = (int) ($first['center_id'] ?? 0);
        $center = $centerId > 0 ? Database::selectOne(
            'SELECT u.`name` FROM `centers` c JOIN `users` u ON u.`id` = c.`user_id` WHERE c.`id` = ?',
            [$centerId]
        ) : null;

        return \App\Core\View::render('seatplan/roster_print', [
            'title' => 'Seat Plan - ' . $this->getClassName($class),
            'students' => $students,
            'session' => $activeSession,
            'className' => $this->getClassName($class),
            'centerName' => $center['name'] ?? (string) $centerId,
            'centerId' => $centerId,
        ]);
    }

    private function getClassName(int $class): string
    {
        return match ($class) {
            6 => 'KG',
            1 => 'One',
            2 => 'Two',
            3 => 'Three',
            4 => 'Four',
            5 => 'Five',
            default => (string)$class
        };
    }
}
