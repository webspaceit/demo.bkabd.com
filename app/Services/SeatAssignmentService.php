<?php

namespace App\Services;

use App\Core\Database;

/** Resolves saved room assignments to active student rolls. */
final class SeatAssignmentService
{
    /**
     * Add a seat_assignment entry to each provided student. Capacity rooms are
     * filled in ascending roll order after explicit roll ranges are applied.
     *
     * @param array<int,array<string,mixed>> $students
     * @return array<int,array<string,mixed>>
     */
    public static function attachLocations(array $students): array
    {
        $groups = [];
        foreach ($students as $student) {
            $session = (int) ($student['session'] ?? 0);
            $centerId = (int) ($student['center_id'] ?? 0);
            $class = (int) ($student['class'] ?? 0);
            if ($session <= 0 || $centerId <= 0 || $class <= 0) {
                continue;
            }
            $key = $session . ':' . $centerId . ':' . $class;
            $groups[$key] = ['session' => $session, 'center_id' => $centerId, 'class' => $class];
        }

        $locationByStudent = [];
        foreach ($groups as $group) {
            try {
                $assignments = Database::select(
                    'SELECT `id`, `building_name`, `floor_number`, `room_number`, `roll_start`, `roll_end`, `room_capacity` '
                    . 'FROM `seat_plan_assignments` WHERE `session` = ? AND `center_id` = ? AND `class` = ? '
                    . 'ORDER BY `sort_order` ASC, `id` ASC',
                    [$group['session'], $group['center_id'], $group['class']]
                );
            } catch (\PDOException $e) {
                // Older installations may not have run the seat-plan migration.
                // Seat locations are optional and must not block admit-card printing.
                if (!in_array((string) $e->getCode(), ['42S02', '42S22'], true)) {
                    throw $e;
                }
                $assignments = [];
            }
            if ($assignments === []) {
                continue;
            }

            // Capacity allocation must rank students for the whole class, even
            // when the caller is printing only one institute or one student.
            $roster = Database::select(
                'SELECT `id`, `roll` FROM `students` WHERE `session` = ? AND `center_id` = ? AND `class` = ? '
                . 'AND `active` = 1 AND `roll` IS NOT NULL ORDER BY `roll` ASC, `id` ASC',
                [$group['session'], $group['center_id'], $group['class']]
            );
            foreach ($assignments as $assignment) {
                if ($assignment['roll_start'] === null || $assignment['roll_end'] === null) {
                    continue;
                }
                $seat = 0;
                foreach ($roster as $student) {
                    $roll = (int) $student['roll'];
                    if ($roll < (int) $assignment['roll_start'] || $roll > (int) $assignment['roll_end']) {
                        continue;
                    }
                    $seat++;
                    $locationByStudent[(int) $student['id']] = self::location($assignment, $seat);
                }
            }

            $remaining = array_values(array_filter($roster, static fn(array $student): bool =>
                !isset($locationByStudent[(int) $student['id']])
            ));

            foreach ($assignments as $assignment) {
                if ($assignment['room_capacity'] === null) {
                    continue;
                }
                $capacity = (int) $assignment['room_capacity'];
                for ($seat = 1; $seat <= $capacity && $remaining !== []; $seat++) {
                    $student = array_shift($remaining);
                    $locationByStudent[(int) $student['id']] = self::location($assignment, $seat);
                }
            }
        }

        foreach ($students as &$student) {
            $student['seat_assignment'] = $locationByStudent[(int) ($student['id'] ?? 0)] ?? null;
        }
        unset($student);

        return $students;
    }

    /** @return array<string,mixed> */
    private static function location(array $assignment, int $seat): array
    {
        return [
            'building_name' => $assignment['building_name'],
            'floor_number' => $assignment['floor_number'],
            'room_number' => $assignment['room_number'],
            'seat_number' => $seat,
            'room_label' => 'Building ' . $assignment['building_name']
                . ' · Floor ' . $assignment['floor_number']
                . ' · Room ' . $assignment['room_number'],
        ];
    }
}
