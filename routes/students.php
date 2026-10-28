<?php

use App\Controllers\StudentController;
use App\Core\Auth;
use App\Core\Cache;
use App\Core\Database;
use App\Core\Request;
use App\Core\Router;
use App\Core\View;
use App\Models\ExamSession;

/** Students module routes — URL contracts identical to original routes/api.php. */

// List students (filters: session, center_id, class, roll, registration, institute_id)
// Original: GET /api/students  ->  index
Router::get('/api/students', [StudentController::class, 'index'], 'auth|permission:student-list');
Router::get('/api/student-filter-institutes', [StudentController::class, 'filterInstitutes'], 'auth|permission:student-list');
Router::get('/api/student-filter-counts', [StudentController::class, 'filterStudentCounts'], 'auth|permission:student-list|admit-card');
Router::get('/api/students/duplicate-check', [StudentController::class, 'duplicateCheck'], 'auth|permission:student-registration');

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
// Staff-only: the card is an internal document the office prints, so it sits
// behind auth (center users are scoped to their own students in the
// controller). The card's QR no longer links back here — it carries the
// association's address/contact text instead (see registration_card.php).
Router::get('/api/registrationpdf/{id}', [StudentController::class, 'registrationpdf'], 'auth');

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

// Students list page (server-side pagination + lazily loaded institute filters)
// Original: GET /students -> students/index.vue
Router::get('/students', function (Request $request) {
    $hasCenterDisplayName = Cache::remember('schema:centers.display_name', 300, static fn(): bool => (int) Database::scalar(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        ['centers', 'display_name']
    ) > 0);
    $centerLabel = $hasCenterDisplayName
        ? "COALESCE(NULLIF(c.`display_name`, ''), NULLIF(u.`name`, ''))"
        : "NULLIF(u.`name`, '')";
    return View::render('students.index', [
        'title'        => 'Students',
        'sessions'     => ExamSession::query()->orderBy('id', 'desc')->get(),
        'classes'      => [1, 2, 3, 4, 5, 6],
        // Include historical centers whose linked user account is missing by
        // using the synchronized center display name when available.
        'centers'      => Cache::remember('filter:active_centers_named_v2', 120, static fn(): array => Database::select(
            'SELECT c.`id`, ' . $centerLabel . ' AS `name` FROM `centers` c
               LEFT JOIN `users` u ON u.`id` = c.`user_id`
              WHERE c.`active` = 1 AND ' . $centerLabel . ' IS NOT NULL AND TRIM(' . $centerLabel . ') <> \'\'
                AND LOWER(TRIM(' . $centerLabel . ')) <> \'select center\'
              ORDER BY `name`'
        )),
        'instituteCount' => (int) Database::scalar('SELECT COUNT(*) FROM `institutes` WHERE `active` = 1'),
        'activeSession' => Auth::activeSession(),
        'filters'      => [
            'session'      => $request->query('session'),
            'class'        => $request->query('class'),
            'status'       => $request->query('status'),
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
// certificates). Viewing the queue needs student-list or student-approve;
// approving/rejecting needs the dedicated student-approve permission
// (database/student_approve_permission.sql grants it to roles that already
// hold student-edit, so existing admins keep working).
// ---------------------------------------------------------------
Router::get('/pending-students', [StudentController::class, 'pagePending'], ['auth', 'permission:student-list|student-approve']);
Router::get('/api/pending-students', [StudentController::class, 'pending'], ['auth', 'permission:student-list|student-approve']);
Router::post('/api/pending-students/approve-bulk', [StudentController::class, 'bulkApprove'], 'auth|permission:student-approve');
Router::post('/api/pending-students/{id}/approve', [StudentController::class, 'approve'], 'auth|permission:student-approve');
Router::post('/api/pending-students/{id}/reject', [StudentController::class, 'reject'], 'auth|permission:student-approve');
Router::post('/api/students/{id}/pend', [StudentController::class, 'pend'], 'auth|permission:student-approve');
