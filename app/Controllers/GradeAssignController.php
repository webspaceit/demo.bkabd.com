<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\View;

/**
 * Grade assign — ported from the original gradeassign flow
 * (StudentController::markentrystudenthighestmark() + the same submit
 * endpoint as mark entry; original Vue page: components/gradeassign/index.vue).
 *
 * URL + JSON contracts:
 *   GET  /api/markentrystudenthighestmark/{id}/{class} -> {students:[...]} (total desc)
 *   GET  /api/markentrystudent/{id}/{class}           -> {students:[...]} (used by /markentry)
 *   POST /api/markentrystudent/updateTotalNumbers     -> handled by MarkEntryController::submit()
 *   Page /gradeassign
 */
final class GradeAssignController extends Controller
{
    /** GET /api/markentrystudenthighestmark/{id}/{class} — same rows as mark entry, highest total first. */
    public function students(Request $request): array
    {
        return [
            'students' => MarkEntryController::fetchStudents(
                (int) $request->params['id'],
                (int) $request->params['class'],
                true
            ),
        ];
    }

    /** GET /gradeassign — grade assign page (original Vue router path: /gradeassign). */
    public function pageIndex(Request $request): string
    {
        return View::render('gradeassign.index', [
            'title' => 'Grade Assign',
            'activeSession' => Auth::activeSession(),
        ], 'layouts.dashboard');
    }
}
