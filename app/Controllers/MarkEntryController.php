<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\ValidationException;
use App\Core\View;
use App\Models\Student;
use App\Services\ReportService;

/**
 * Mark entry — ported from original StudentController::markentrystudent(),
 * markentrystudenthighestmark() and marksubmit() (routes/api.php).
 *
 * URL + JSON contracts:
 *   GET  /api/markentrystudent/{id}/{class}             -> {students:[{id,name,registration,roll,bangla,english,math,total,percentage,ml,grade}]}
 *   GET  /api/markentrystudenthighestmark/{id}/{class}  -> same shape, ordered by total desc (used by /gradeassign)
 *   POST /api/markentrystudent/updateTotalNumbers       -> {success,data:{students:[...]},message:'Updated successfully'}
 *   Pages /markentry, /gradeassign (page lives in GradeAssignController too)
 *
 * The original marksubmit() echoed back only {message}; the clone reports
 * module already registered a non-persisting version of this endpoint
 * ({success,data.students,...}). routes/marks.php loads before
 * routes/reports.php, so this persisting implementation wins while keeping
 * both response shapes satisfied (message + data.students).
 */
final class MarkEntryController extends Controller
{
    /** GET /api/markentrystudent/{id}/{class} — students of an institute + class for the active session. */
    public function students(Request $request): array
    {
        return ['students' => self::fetchStudents(
            (int) $request->params['id'],
            (int) $request->params['class'],
            false
        )];
    }

    /**
     * POST /api/markentrystudent/updateTotalNumbers — persist the edited rows.
     * Original: StudentController::marksubmit() (client-computed totals saved verbatim).
     */
    public function submit(Request $request): array
    {
        $rows = $this->validatedStudents($request);

        foreach ($rows as $row) {
            // Original: `Student::find(...)` then update — missing ids are skipped.
            if (Student::find($row['id']) === null) {
                continue;
            }
            Student::updateRow($row['id'], [
                'bangla' => $row['bangla'],
                'english' => $row['english'],
                'math' => $row['math'],
                'total' => $row['total'],
                'percentage' => $row['percentage'],
                'grade' => $row['grade'],
                'ml' => $row['ml'],
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return $this->sendResponse(ReportService::markEntryPayload($rows), 'Updated successfully');
    }

    // ---------------------------------------------------------------
    // Pages
    // ---------------------------------------------------------------

    /** GET /markentry — mark entry page (original Vue router path: /markentry). */
    public function pageIndex(Request $request): string
    {
        return View::render('markentry.index', [
            'title' => "Mark's Entry",
            'activeSession' => Auth::activeSession(),
        ], 'layouts.dashboard');
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Shared fetch for /api/markentrystudent[/highestmark]/{id}/{class}.
     * Column list matches the original select() exactly.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function fetchStudents(int $instituteId, int $class, bool $highestFirst): array
    {
        $q = \App\Models\Student::query()
            ->select('id', 'name', 'registration', 'roll', 'bangla', 'english', 'math', 'total', 'percentage', 'ml', 'grade')
            ->where('institute_id', $instituteId)
            ->where('class', $class)
            ->where('session', Auth::activeSession());

        if ($highestFirst) {
            $q->orderBy('total', 'desc');
        }

        return $q->get();
    }

    /**
     * Validate the {students:[...]} body and normalise the marks columns.
     * Empty inputs become 0 (the Vue inputs post '' when cleared) so strict
     * MySQL never sees a non-numeric value — the original relied on MySQL
     * coercion and could 500 here.
     *
     * @return array<int,array{id:int,bangla:int,english:int,math:int,total:int,percentage:float,grade:int,ml:int}>
     */
    private function validatedStudents(Request $request): array
    {
        $students = $request->input('students');
        if (!is_array($students) || $students === []) {
            throw new ValidationException([
                'students' => ['The students field is required.'],
            ]);
        }

        $errors = [];
        $rows = [];
        foreach ($students as $i => $student) {
            if (!is_array($student) || filter_var($student['id'] ?? null, FILTER_VALIDATE_INT) === false) {
                $errors['students'][] = 'Invalid student payload at index ' . $i . '.';
                continue;
            }

            $row = ['id' => (int) $student['id']];
            foreach (['bangla', 'english', 'math', 'total', 'grade', 'ml'] as $col) {
                $row[$col] = (int) $this->numericOrZero($student[$col] ?? null, $errors, $i, $col);
            }
            $row['percentage'] = (float) $this->numericOrZero($student['percentage'] ?? null, $errors, $i, 'percentage');
            $rows[] = $row;
        }

        if ($errors !== []) {
            throw new ValidationException(['students' => $errors]);
        }

        return $rows;
    }

    /** ''|null → 0, numeric → as-is (int|float), anything else → records an error. */
    private function numericOrZero(mixed $value, array &$errors, int $index, string $col): int|float
    {
        if ($value === null || $value === '') {
            return 0;
        }
        if (is_numeric($value)) {
            return $value + 0;
        }
        $errors[] = "The $col at index $index must be a number.";

        return 0;
    }
}
