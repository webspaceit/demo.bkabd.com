<?php

use App\Controllers\StudentController;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Query;
use App\Core\Request;
use App\Core\Router;
use App\Core\View;
use App\Models\ExamSession;

/** Students module routes — URL contracts identical to original routes/api.php. */

// List students (filters: session, center_id, class, roll, registration, institute_id)
// Original: GET /api/students  ->  index
Router::get('/api/students', [StudentController::class, 'index'], 'auth|permission:student-list');

// Register form
// Original: GET /student-registration  ->  create (page)
// Note: original had this as a page route under web.php; keeping as auth-gated page.
Router::get('/student-registration', [StudentController::class, 'create'], 'auth|permission:student-registration');

// Register student
// Original: POST /api/students  ->  store
Router::post('/api/students', [StudentController::class, 'store'], 'auth|permission:student-registration');

// View student
// Original: GET /api/students/{id}  ->  show
Router::get('/api/students/{id}', [StudentController::class, 'show'], 'auth');

// Total students in the active session (original: GET /api/totalStudent)
Router::get('/api/totalStudent', [StudentController::class, 'totalStudent'], 'auth');

// Printable registration card by registration number
// Original: GET /api/registrationpdf/{registration} (public + dompdf stream).
// Public on purpose (like the institute card): the card's own QR encodes this
// URL so a student can open it without an account. The controller applies a
// per-IP throttle so registration numbers cannot be enumerated cheaply.
Router::get('/api/registrationpdf/{id}', [StudentController::class, 'registrationpdf']);

// Edit student
// Original: GET /edit-student/{id}  ->  edit (page)
// Original also had POST /api/students/{id} for update via different path — we'll use the same update method
Router::get('/edit-student/{id}', [StudentController::class, 'edit'], 'auth|permission:student-edit');

// Update student
// Original: POST /api/students/{id} (or PUT) -> update
// Blueprint hard rule: convert destructive GET to DELETE/POST; we keep POST per original convention
Router::post('/api/students/{id}', [StudentController::class, 'update'], 'auth|permission:student-edit');

// Delete student
// Original: had GET /api/students/{id} delete route (bug) — we make it DELETE
Router::delete('/api/students/{id}', [StudentController::class, 'destroy'], 'auth|permission:student-delete');

// Delete student photo only
Router::post('/api/students/{id}/delete-photo', [StudentController::class, 'deletePhoto'], 'auth|permission:student-edit');

// Photo upload
// Original: POST /api/uploadFile (was unauthenticated + had dd()) — requires
// auth + one of the student write permissions; rate-limited in the controller.
Router::post('/api/uploadFile', [StudentController::class, 'uploadFile'], ['auth', 'permission:student-registration|student-edit']);

// Students list page (server-side pagination + filters; inline page script in the view)
// Original: GET /students -> students/index.vue
Router::get('/students', function (Request $request) {
    return View::render('students.index', [
        'title'        => 'Students',
        'sessions'     => ExamSession::query()->orderBy('id', 'desc')->get(),
        'classes'      => [1, 2, 3, 4, 5, 6],
        // Center dropdown shows the center USER's name (centers table has no
        // `name` column) — same shape as ReportService::centersCache().
        'centers'      => Database::select(
            'SELECT c.`id`, u.`name` FROM `centers` c
               JOIN `users` u ON u.`id` = c.`user_id`
              WHERE c.`active` = 1
              ORDER BY u.`name`'
        ),
        'institutes'   => (new Query('institutes'))->where('active', 1)->orderBy('name', 'asc')->get(),
        'activeSession' => Auth::activeSession(),
        'filters'      => [
            'session'      => $request->query('session'),
            'class'        => $request->query('class'),
            'center_id'    => $request->query('center_id'),
            'institute_id' => $request->query('institute_id'),
            'search'       => $request->query('search'),
        ],
    ], 'layouts.dashboard');
}, 'auth|permission:student-list');

// ---------------------------------------------------------------
// Pending registrations (approval queue) — new in this port.
// store() creates rows as PENDING (active = 0); the registration only
// becomes Active when it is approved here. Until then the student is
// excluded from the exam documents (mark entry, admit card, seat plan,
// certificates). Approval/rejection is gated by student-edit — there is
// no dedicated permission row (roles UI stays unchanged).
// ---------------------------------------------------------------
Router::get('/pending-students', [StudentController::class, 'pagePending'], 'auth|permission:student-list');
Router::get('/api/pending-students', [StudentController::class, 'pending'], 'auth|permission:student-list');
Router::post('/api/pending-students/{id}/approve', [StudentController::class, 'approve'], 'auth|permission:student-edit');
Router::post('/api/pending-students/{id}/reject', [StudentController::class, 'reject'], 'auth|permission:student-edit');