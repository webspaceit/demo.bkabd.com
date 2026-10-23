<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Core\Query;
use App\Core\RateLimiter;
use App\Core\Session;
use App\Core\Database;
use App\Models\Student;
use App\Models\Institute;
use App\Models\Center;
use App\Models\Division;
use App\Models\District;
use App\Models\Upazila;
use App\Models\Union;
use App\Models\ExamSession;
use App\Models\User;
use App\Core\View;
use App\Services\StudentService;

/**
 * Student CRUD controller — ported from original StudentController (562 lines).
 *
 * Endpoint contracts (URL + JSON shape) identical to original routes/api.php.
 * Centre-user scoping: center users see only their center's students.
 * Registration number generation per original algorithm.
 * Marks live on the student row (bangla/english/math/total/percentage/grade/ml).
 * SMS via SmsService (key from .env).
 * Validation: 422 JSON for Ajax, redirect-back-with-errors for plain posts.
 * Dead code / dd() / N+1 avoided.
 */
final class StudentController extends Controller
{
    /** Student photo: ceiling for the stored image (30 KB) and its longest side. */
    private const PHOTO_MAX_BYTES = 30 * 1024;
    private const PHOTO_MAX_DIM   = 600;

    /** List students with filters. GET /api/students */
    public function index(Request $request): array
    {
        if (!Auth::check()) {
            Response::json(['status' => 'errors'], 401);
        }

        // Centre-user scoping: students where centers.user_id = Auth::id()
        $centerId = Auth::centerId();
        $q = (new Query('students'))->select(
            'students.*',
            'users.name as center_name',
            'institutes.name as institute_name',
            'divisions.name as division_name',
            'districts.name as district_name',
            'upozilas.name as upazila_name',
            'unions.name as union_name'
        );

        // Join all eager relations in one query (avoid N+1); LEFT joins keep
        // rows even when a geo reference is missing.
        $q->leftJoin('centers', 'centers.id', '=', 'students.center_id')
          ->leftJoin('users', 'users.id', '=', 'centers.user_id')
          ->leftJoin('institutes', 'institutes.id', '=', 'students.institute_id')
          ->leftJoin('divisions', 'divisions.id', '=', 'students.division_id')
          ->leftJoin('districts', 'districts.id', '=', 'students.district_id')
          ->leftJoin('upozilas', 'upozilas.id', '=', 'students.upazila_id')
          ->leftJoin('unions', 'unions.id', '=', 'students.union_id');

        if ($centerId !== null && !Auth::isAdmin()) {
            $q->where('students.center_id', $centerId);
        }

        // Filters from original: session, center_id, class, roll, registration, institute_id
        // NOTE: the list page always sends every param (empty when unset), so an
        // empty string must be treated as "no filter" — otherwise (int) '' === 0
        // would match nothing.
        $session = $request->input('session');
        if ($session !== null && $session !== '') {
            $q->where('session', (int) $session);
        }
        $class = $request->input('class');
        if ($class !== null && $class !== '') {
            $q->where('class', (int) $class);
        }
        $status = $request->input('status');
        if ($status === 'active') {
            $q->whereRaw('`students`.`active` = 1');
        } elseif ($status === 'pending') {
            $q->whereRaw('(`students`.`active` = 0 OR `students`.`active` IS NULL)');
        }
        $instituteId = $request->input('institute_id');
        if ($instituteId !== null && $instituteId !== '') {
            $q->where('institute_id', (int) $instituteId);
        }
        $roll = $request->input('roll');
        if ($roll !== null && $roll !== '') {
            $q->where('roll', (int) $roll);
        }
        $registration = $request->input('registration');
        if ($registration !== null && $registration !== '') {
            $q->where('registration', $registration);
        }

        $centerFilter = $request->input('center_id');
        if ($centerFilter !== null && $centerFilter !== '') {
            // Query builder wraps dotted names in one backtick pair, so use
            // whereRaw with explicit `table`.`column` quoting here (center_id
            // also exists on institutes — unqualified would be ambiguous).
            $q->whereRaw('`students`.`center_id` = ?', [(int) $centerFilter]);
        }

        // Free-text search: matches name, registration, roll, class words
        // (kg/one/two/...), center-user name and institute name — ported from
        // the original index(); class words matched case-insensitively.
        $search = $request->input('search');
        $this->applyStudentSearch($q, $search);

        // Most list and numeric searches only filter students columns. Count
        // those rows without the seven display joins; the joins are needed
        // only for the returned page (or for text search against related names).
        $searchTerm = trim((string) $search);
        $classTerms = ['kg', 'one', 'two', 'three', 'four', 'five'];
        $countQuery = ($searchTerm === '' || ctype_digit($searchTerm) || in_array(strtolower($searchTerm), $classTerms, true))
            ? $q->withoutJoins()
            : null;

        // Show the most recently registered students first. Use id as a stable
        // tie-breaker when several registrations share the same timestamp.
        $q->orderBy('students.created_at', 'desc')
          ->orderBy('students.id', 'desc');

        // Pagination matching Laravel shape for frontend 1:1
        return $this->sendResponse(
            $q->paginate((int) ($request->input('per_page', 20)), null, '/api/students', $countQuery),
            'Student list retrieved.'
        );
    }

    // ---------------------------------------------------------------
    // Pending registrations (approval queue)
    // ---------------------------------------------------------------

    /**
     * Pending registrations page. GET /pending-students
     * Same server-rendered filter data as the /students list page.
     */
    public function pagePending(Request $request): string
    {
        if (!Auth::check()) {
            return Response::redirect('/login');
        }

        return View::render('students.pending', [
            'title'        => 'Pending Registration',
            'sessions'     => ExamSession::query()->orderBy('id', 'desc')->get(),
            'classes'      => [1, 2, 3, 4, 5, 6],
            // Center dropdown shows the center USER's name (centers table has
            // no `name` column) — same shape as the /students list page.
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
    }

    /**
     * Pending registration list (approval queue). GET /api/pending-students
     * Students whose registration has not been approved yet (active = 0/NULL).
     */
    public function pending(Request $request): array
    {
        if (!Auth::check()) {
            Response::json(['status' => 'errors'], 401);
        }

        $centerId = Auth::centerId();
        $q = (new Query('students'))->select(
            'students.*',
            'users.name as center_name',
            'institutes.name as institute_name',
            'divisions.name as division_name',
            'districts.name as district_name',
            'upozilas.name as upazila_name',
            'unions.name as union_name'
        );

        $q->leftJoin('centers', 'centers.id', '=', 'students.center_id')
          ->leftJoin('users', 'users.id', '=', 'centers.user_id')
          ->leftJoin('institutes', 'institutes.id', '=', 'students.institute_id')
          ->leftJoin('divisions', 'divisions.id', '=', 'students.division_id')
          ->leftJoin('districts', 'districts.id', '=', 'students.district_id')
          ->leftJoin('upozilas', 'upozilas.id', '=', 'students.upazila_id')
          ->leftJoin('unions', 'unions.id', '=', 'students.union_id');

        // The queue itself: not yet approved. Explicit `table`.`column`
        // quoting (active also exists on centers/institutes).
        $q->whereRaw('(`students`.`active` = 0 OR `students`.`active` IS NULL)');

        if ($centerId !== null && !Auth::isAdmin()) {
            $q->where('students.center_id', $centerId);
        }

        // Same filters as the /students list (empty string = no filter).
        $session = $request->input('session');
        if ($session !== null && $session !== '') {
            $q->where('session', (int) $session);
        }
        $class = $request->input('class');
        if ($class !== null && $class !== '') {
            $q->where('class', (int) $class);
        }
        $instituteId = $request->input('institute_id');
        if ($instituteId !== null && $instituteId !== '') {
            $q->where('institute_id', (int) $instituteId);
        }
        $centerFilter = $request->input('center_id');
        if ($centerFilter !== null && $centerFilter !== '') {
            $q->whereRaw('`students`.`center_id` = ?', [(int) $centerFilter]);
        }

        $this->applyStudentSearch($q, $request->input('search'));

        // FIFO queue: oldest registrations first.
        $q->orderBy('created_at', 'asc')->orderBy('id', 'asc');

        return $this->sendResponse(
            $q->paginate((int) ($request->input('per_page', 20)), null, '/api/pending-students'),
            'Pending registration list retrieved.'
        );
    }

    /**
     * Approve a pending registration. POST /api/pending-students/{id}/approve
     * Idempotent: approving an already-approved registration still succeeds.
     */
    public function approve(Request $request): array
    {
        if (!Auth::check()) {
            Response::json(['status' => 'errors'], 401);
        }

        $id = (int) ($request->params['id'] ?? 0);
        $student = Student::find($id);
        if ($student === null) {
            throw new \App\Core\HttpException(404, 'Student not found');
        }

        // RLS/BOLA: only the owner center user (or an admin) may approve.
        Auth::assertStudentAccess($id);

        if ((int) ($student['active'] ?? 0) !== 1) {
            Student::updateRow($id, ['active' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
        }

        return $this->sendResponse(
            ['id' => $id, 'registration' => $student['registration'], 'active' => 1],
            'Registration approved successfully.'
        );
    }

    /** Move an active student back to the pending approval queue. */
    public function pend(Request $request): array
    {
        if (!Auth::check()) {
            Response::json(['status' => 'errors'], 401);
        }

        $id = (int) ($request->params['id'] ?? 0);
        $student = Student::find($id);
        if ($student === null) {
            throw new \App\Core\HttpException(404, 'Student not found');
        }

        // Restrict center users to their own students; admins may pend any student.
        Auth::assertStudentAccess($id);

        if ((int) ($student['active'] ?? 0) !== 1) {
            throw new \App\Core\HttpException(422, 'Only active students can be moved to pending.');
        }

        Student::updateRow($id, ['active' => 0, 'updated_at' => date('Y-m-d H:i:s')]);

        return $this->sendResponse(
            ['id' => $id, 'registration' => $student['registration'], 'active' => 0],
            'Student moved to pending approval.'
        );
    }

    /** Approve multiple pending registrations in one request. */
    public function bulkApprove(Request $request): array
    {
        if (!Auth::check()) {
            Response::json(['status' => 'errors'], 401);
        }

        $rawIds = $request->input('ids', []);
        if (!is_array($rawIds) || $rawIds === [] || count($rawIds) > 500) {
            throw new \App\Core\HttpException(422, 'Select between 1 and 500 pending registrations.');
        }
        $ids = [];
        foreach ($rawIds as $rawId) {
            $id = filter_var($rawId, FILTER_VALIDATE_INT);
            if ($id === false || $id <= 0) {
                throw new \App\Core\HttpException(422, 'The selected registration list is invalid.');
            }
            $ids[] = $id;
        }
        $ids = array_values(array_unique($ids));
        if (count($ids) !== count($rawIds)) {
            throw new \App\Core\HttpException(422, 'The selected registration list contains duplicates.');
        }

        // Apply the same center ownership check as single-student approval.
        Auth::assertStudentAccess($ids);

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $students = Database::select(
            'SELECT `id` FROM `students` WHERE `id` IN (' . $placeholders . ')',
            $ids
        );
        if (count($students) !== count($ids)) {
            throw new \App\Core\HttpException(404, 'One or more selected students were not found.');
        }

        $updatedAt = date('Y-m-d H:i:s');
        $updated = Database::statement(
            'UPDATE `students` SET `active` = 1, `updated_at` = ? WHERE `id` IN (' . $placeholders . ') '
            . 'AND (`active` = 0 OR `active` IS NULL)',
            array_merge([$updatedAt], $ids)
        );

        return $this->sendResponse(
            ['approved' => $updated->rowCount(), 'selected' => count($ids)],
            'Selected registrations approved successfully.'
        );
    }

    /**
     * Reject a pending registration. POST /api/pending-students/{id}/reject
     * The app has no soft-delete/audit trail for students, so rejecting removes
     * the registration entirely (same as the list's Delete) after a UI confirm.
     * Only PENDING rows may be rejected — deleting approved students stays on
     * the regular delete endpoint (student-delete).
     */
    public function reject(Request $request): array
    {
        if (!Auth::check()) {
            Response::json(['status' => 'errors'], 401);
        }

        $id = (int) ($request->params['id'] ?? 0);
        $student = Student::find($id);
        if ($student === null) {
            throw new \App\Core\HttpException(404, 'Student not found');
        }

        // RLS/BOLA: only the owner center user (or an admin) may reject.
        Auth::assertStudentAccess($id);

        if ((int) ($student['active'] ?? 0) === 1) {
            throw new \App\Core\HttpException(422, 'Only pending registrations can be rejected.');
        }

        if (!empty($student['photo'])) {
            @unlink(BASE_PATH . '/public/' . $student['photo']);
        }
        Student::deleteRow($id);

        return $this->sendResponse(
            ['deleted' => $id],
            'Registration rejected and removed.'
        );
    }

    /** Shared free-text search for the student list queries (see index()). */
    private function applyStudentSearch(Query $q, ?string $search): void
    {
        if ($search === null || trim($search) === '') {
            return;
        }
        $term = strtolower(trim($search));
        $classWord = match ($term) {
            'kg' => 6, 'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5,
            default => null,
        };
        if ($classWord !== null) {
            $q->where('students.class', $classWord);
            return;
        }

        // Registration numbers are numeric and indexed. Avoid the broad
        // multi-column contains search (which scans the whole session) for
        // numeric input; prefix matching keeps partial registration searches
        // useful while allowing MySQL to use the registration index. Short
        // numeric values can also be searched as an exact roll number.
        $numericSearch = trim($search);
        if ($numericSearch !== '' && ctype_digit($numericSearch)) {
            $q->whereRaw(
                '(`students`.`registration` LIKE ? OR `students`.`roll` = ?)',
                [$numericSearch . '%', $numericSearch]
            );
            return;
        }

        $like = '%' . $search . '%';
        // Search group; dotted columns quoted explicitly (see index()).
        $q->whereRaw(
            '(`students`.`name` LIKE ? OR `registration` LIKE ? OR `roll` LIKE ?'
            . ' OR `users`.`name` LIKE ? OR `institutes`.`name` LIKE ?)',
            [$like, $like, $like, $like, $like]
        );
    }

    /** Show registration form. GET /student-registration */
    public function create(): string
    {
        if (!Auth::check()) {
            return Response::redirect('/login');
        }
        $divisions  = (new Query('divisions'))->orderBy('name', 'asc')->get();
        $centers    = $this->centerOptions();
        $sessions   = (new Query('sessions'))->orderBy('id', 'desc')->get();
        $oldDivId   = (int) (Session::get('old', [])['division_id'] ?? 0);
        $oldDistId  = (int) (Session::get('old', [])['district_id'] ?? 0);
        $oldUpaId   = (int) (Session::get('old', [])['upazila_id'] ?? 0);
        $districts  = $oldDivId  ? (new Query('districts'))->where('division_id', $oldDivId)->orderBy('name','asc')->get() : [];
        $upazilas   = $oldDistId ? (new Query('upozilas'))->where('district_id', $oldDistId)->orderBy('name','asc')->get() : [];
        $unions     = $oldUpaId  ? (new Query('unions'))->where('upozila_id', $oldUpaId)->orderBy('name','asc')->get() : [];
        $oldCenterId = (int) (Session::get('old', [])['center_id'] ?? 0);
        $institutes = $oldCenterId ? (new Query('institutes'))->where('center_id', $oldCenterId)->where('active', 1)->orderBy('name','asc')->get() : [];
        return View::render('students.create', [
            'title'        => 'Student Registration',
            'divisions'    => $divisions,
            'centers'      => $centers,
            'sessions'     => $sessions,
            'districts'    => $districts,
            'upazilas'     => $upazilas,
            'unions'       => $unions,
            'institutes'   => $institutes,
            'classes'      => [1,2,3,4,5,6],
            'activeSession' => Auth::activeSession(),
            'pageStyles'   => ['/assets/css/dobpicker.css'],
            'pageScripts'  => ['/assets/js/dobpicker.js'],
        ], 'layouts.dashboard');
    }

    /**
     * Center <option> rows for the registration / edit selects: id + display
     * name. The `centers` table has no `name` column — the label is the centre
     * account user's name (`centers.user_id` -> `users.name`). RLS: centre users
     * get only their own centre.
     */
    private function centerOptions(): array
    {
        $q = (new Query('centers'))
            ->select('centers.id', 'users.name as name')
            ->join('users', 'users.id', '=', 'centers.user_id')
            ->where('centers.active', 1);
        if (!Auth::isAdmin() && Auth::centerId() !== null) {
            $q->where('centers.id', Auth::centerId());
        }
        return $q->orderBy('users.name', 'asc')->get();
    }

    /** Check for an existing registration identity before form submission. */
    public function duplicateCheck(Request $request): array
    {
        $data = [
            'name' => trim((string) $request->query('name', '')),
            'father_name' => trim((string) $request->query('father_name', '')),
            'mother_name' => trim((string) $request->query('mother_name', '')),
            'phone' => trim((string) $request->query('phone', '')),
            'session' => (int) $request->query('session', 0),
            'class' => (int) $request->query('class', 0),
            'center_id' => (int) $request->query('center_id', 0),
            'institute_id' => (int) $request->query('institute_id', 0),
        ];

        $ready = $data['name'] !== '' && $data['father_name'] !== '' && $data['phone'] !== ''
            && $data['session'] > 0 && $data['class'] > 0 && $data['center_id'] > 0 && $data['institute_id'] > 0;
        if (!$ready) {
            return $this->sendResponse(['ready' => false, 'duplicate' => false], 'Complete the identity fields to check for duplicates.');
        }

        Auth::assertCenterAccess($data['center_id']);
        Auth::assertInstituteAccess($data['institute_id']);

        return $this->sendResponse(
            ['ready' => true, 'duplicate' => StudentService::isDuplicate($data)],
            'Duplicate registration check completed.'
        );
    }

    /** Register student. POST /api/students */
    public function store(Request $request): array
    {
        if (!Auth::check()) {
            Response::json(['status' => 'errors'], 401);
        }

        // Validation rules — ported 1:1 from original
        $rules = [
            'name'                 => 'required',
            'father_name'          => 'required',
            'mother_name'          => 'nullable',
            'phone'                => 'required|string|max:11',
            'email'                => 'nullable|email',
            'dob'                  => 'nullable|date_format:Y-m-d',
            'gender'               => 'required|in:1,2,3',        // 1=Male 2=Female 3=Others
            'religion'             => 'nullable',
            'present_address'      => 'required|string|max:255',
            'permanent_address'    => 'nullable|string|max:255',
            'education_type'       => 'nullable|in:1,2,3',      // 1=Bangla 2=English 3=Arabic
            'class'                => 'required|in:1,2,3,4,5,6',
            'session'              => 'required|integer',
            'center_id'            => 'required|integer',
            'institute_id'         => 'required|integer',
            'division_id'          => 'required|integer',
            'district_id'          => 'nullable|integer',
            'upazila_id'           => 'nullable|integer',
            'union_id'             => 'nullable|integer',
            'photo'                => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'principal_name'       => 'nullable',
            'note'                 => 'nullable',
        ];

        try {
            $data = Validator::validate($request, $rules, [
                'present_address.required' => 'The present address field is required.',
            ]);
        } catch (\App\Core\ValidationException $e) {
            Response::validationError($e->errors, $e->getMessage());
        }

        // RLS/BOLA: a center user may only register into a center they own and
        // an institute that belongs to that center (admins pass through).
        Auth::assertCenterAccess((int) $data['center_id']);
        Auth::assertInstituteAccess((int) $data['institute_id']);

        // --- Duplicate check (phone+father_name+name+session+class+center+institute) ---
        if (StudentService::isDuplicate($data)) {
            // Original duplicate-response shape — keep key names identical
            $message = 'A student with the same name, father name, mother name, phone, session, class, center, and institute already exists.';
            Response::validationError(['name' => $message], $message);
        }

        // --- Registration number generation ---
        // Format: substr(session,-2) + sprintf('%03u',center_id) + sprintf('%04u',institute_id) + class + '001'
        // Then: if prior student exists in same center+institute+class+session (order by registration desc),
        //        increment the numeric tail: (int)lastStudent's registration + 1
        $sessionSuffix = substr((string) $data['session'], -2);
        $regBase = sprintf('%s%03d%04d%s001', $sessionSuffix, (int) $data['center_id'], (int) $data['institute_id'], (int) $data['class']);

        // Check for existing students in this center+institute+class+session to increment
        $lastStudent = Database::selectOne(
            "SELECT registration FROM `students`
               WHERE `center_id` = ? AND `institute_id` = ? AND `class` = ?
                 AND `session` = ? AND `registration` != ''
               ORDER BY `registration` DESC LIMIT 1",
            [$data['center_id'], $data['institute_id'], (int) $data['class'], (int) $data['session']]
        );

        $registration = $regBase;
        if ($lastStudent && isset($lastStudent['registration'])) {
            // Increment the numeric tail: '001' -> '002', '010' -> '011', etc.
            $tail = (int) substr($lastStudent['registration'], -3);      // last 3 digits
            $newTail = $tail + 1;
            $registration = substr($lastStudent['registration'], 0, -3) . sprintf('%03d', $newTail);
        }

        // --- Photo upload (optional) ---
        $photoPath = $request->hasFile('photo')
            ? $this->savePhoto($request->file('photo'), 'photo', self::PHOTO_MAX_BYTES, self::PHOTO_MAX_DIM)
            : null;

        // --- Insert student ---
        // `registration` (generated above), `active` and `paid` are part of the
        // original Student::firstOrCreate() payload; the list, the registration
        // card and the QR result lookups all read them, so a row inserted
        // without them shows a blank registration number and "Inactive".
        // Filter on null only — array_filter() without a callback would also
        // drop the legitimate 0 for `paid`.
        // Approval workflow: new registrations are created PENDING
        // (active = 0) and appear in the Pending Registration queue until an
        // admin approves them (POST /api/pending-students/{id}/approve).
        $studentData = array_filter([
            'name'               => $data['name'],
            'father_name'        => $data['father_name'],
            'mother_name'        => $data['mother_name'] ?? '',
            'phone'              => $data['phone'],
            'email'              => $data['email'] ?? '',
            'dob'                => $data['dob'] ?? null,
            'gender'             => (int) $data['gender'],
            'religion'           => $data['religion'] ?? '',
            'present_address'    => $data['present_address'] ?? '',
            'permanent_address'  => $data['permanent_address'] ?? '',
            'education_type'     => $data['education_type'] ?? '',
            'class'              => (int) $data['class'],
            'session'            => (int) $data['session'],
            'center_id'          => (int) $data['center_id'],
            'institute_id'       => (int) $data['institute_id'],
            'division_id'        => (int) $data['division_id'],
            // Geo dropdowns are optional, so '' (unselected) can arrive here as
            // NULL after validation. district_id/upazila_id are NOT NULL ints
            // (the original app relied on non-strict MySQL coercing NULL -> 0),
            // so an unselected optional dropdown stores 0; union_id is
            // nullable and stores NULL (the original's convention).
            'district_id'        => (int) ($data['district_id'] ?? 0),
            'upazila_id'         => (int) ($data['upazila_id'] ?? 0),
            'union_id'           => $data['union_id'] !== null ? (int) $data['union_id'] : null,
            'photo'              => $photoPath,
            'principal_name'     => $data['principal_name'] ?? '',
            'note'               => $data['note'] ?? '',
            'registration'       => $registration,
            'active'             => 0,
            'paid'               => 0,
            'user_id'            => Auth::id(),
            'created_at'         => date('Y-m-d H:i:s'),
            'updated_at'         => date('Y-m-d H:i:s'),
        ], static fn ($value): bool => $value !== null);

        // Claim the normalized identity key and insert the student in one
        // transaction. The unique key table closes the race where two
        // identical requests pass the SELECT duplicate check simultaneously.
        $duplicateFingerprint = StudentService::duplicateFingerprint($studentData);
        try {
            $studentId = Database::transaction(function () use ($duplicateFingerprint, $studentData): int {
                Database::insert('student_duplicate_keys', ['fingerprint' => $duplicateFingerprint]);
                return Student::create($studentData);
            });
        } catch (\PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                if ($photoPath !== null && is_file(BASE_PATH . '/public/' . $photoPath)) {
                    @unlink(BASE_PATH . '/public/' . $photoPath);
                }
                $message = 'A student with the same name, father name, mother name, phone, session, class, center, and institute already exists.';
                Response::validationError(['name' => $message], $message);
            }
            throw $e;
        }

        // --- SMS notification (optional dry-run) ---
        if (Config::getBool('SMS_DRY_RUN', false) !== true) {
            $msg = sprintf(
                'Student registered: %s (Roll: %s, Reg: %s)',
                $data['name'], $regBase, $registration
            );
            send_sms_mask($data['phone'], $msg);
        }

        return $this->sendResponse(
            ['id' => $studentId, 'registration' => $registration, 'regBase' => $regBase, 'active' => 0],
            'Student registered successfully. Awaiting approval.'
        );
    }

    /** View student details. GET /api/students/{id} */
    public function show(Request $request): array
    {
        if (!Auth::check()) {
            Response::json(['status' => 'errors'], 401);
        }

        $id = (int) $request->params['id'];
        $student = Student::find($id);
        if ($student === null) {
            throw new \App\Core\HttpException(404, 'Student not found');
        }

        // RLS/BOLA: the student must belong to the caller's center.
        Auth::assertStudentAccess($id);

        // Load related names in one query to avoid N+1
        $institute = Institute::find($student['institute_id'] ?? 0);
        $center = Center::find($student['center_id'] ?? 0);
        $division = Division::find($student['division_id'] ?? 0);
        $district = $student['district_id'] ? District::find($student['district_id']) : null;
        $upazila = $student['upazila_id'] ? Upazila::find($student['upazila_id']) : null;
        $union = $student['union_id'] ? Union::find($student['union_id']) : null;

        // Original detail-modal "Center" = the center's USER name
        // (User::join('centers'...)->name); the centers table itself has no
        // `name` column, so fall back to its institute_name.
        $centerName = '';
        if ($center) {
            $centerUser = Database::selectOne(
                'SELECT `name` FROM `users` WHERE `id` = ? LIMIT 1',
                [(int) $center['user_id']]
            );
            $centerName = $centerUser['name'] ?? ($center['institute_name'] ?? '');
        }

        $result = array_merge($student, [
            'center_name' => $centerName,
            'institute_name' => $institute ? $institute['name'] : '',
            'division_name' => $division ? $division['name'] : '',
            'district_name' => $district ? $district['name'] : '',
            'upazila_name' => $upazila ? $upazila['name'] : '',
            'union_name' => $union ? $union['name'] : '',
        ]);

        return $this->sendResponse($result, 'Student details retrieved.');
    }

    /** Edit form. GET /edit-student/{id} */
    public function edit(Request $request): string
    {
        if (!Auth::check()) {
            return Response::redirect('/login');
        }

        $id = (int) $request->params['id'];
        $student = Student::find($id);
        if ($student === null) {
            throw new \App\Core\HttpException(404, 'Student not found');
        }

        // RLS/BOLA: the student must belong to the caller's center.
        Auth::assertStudentAccess($id);

        $divisions = (new Query('divisions'))->orderBy('name', 'asc')->get();
        $centers = $this->centerOptions();
        $sessions = (new Query('sessions'))->orderBy('id', 'desc')->get();

        // Child lists filtered by the student's current values (mirrors
        // create(): options render server-side, no client-side cascade).
        $institutes = [];
        if ($student['center_id']) {
            $institutes = (new Query('institutes'))->where('center_id', $student['center_id'])
                ->where('active', 1)->orderBy('name', 'asc')->get();
        }
        $districts = $student['division_id'] ? (new Query('districts'))->where('division_id', $student['division_id'])->orderBy('name', 'asc')->get() : [];
        $upazilas  = $student['district_id'] ? (new Query('upozilas'))->where('district_id', $student['district_id'])->orderBy('name', 'asc')->get() : [];
        $unions    = $student['upazila_id'] ? (new Query('unions'))->where('upozila_id', $student['upazila_id'])->orderBy('name', 'asc')->get() : [];

        return View::render('students.edit', [
            'title' => 'Edit Student',
            'pageStyles' => ['/assets/css/flatpickr.min.css', '/assets/css/datepicker.css'],
            'pageScripts' => ['/assets/js/vendor/flatpickr.min.js', '/assets/js/datepicker.js'],
            'student' => $student,
            'divisions' => $divisions,
            'centers' => $centers,
            'sessions' => $sessions,
            'districts' => $districts,
            'upazilas' => $upazilas,
            'unions' => $unions,
            'institutes' => $institutes,
            'classes' => [1,2,3,4,5,6],
            'activeSession' => Auth::activeSession(),
            'genderOptions' => ['1' => 'Male', '2' => 'Female', '3' => 'Others'],
            'educationOptions' => ['1' => 'Bangla Medium', '2' => 'English Version', '3' => 'Arabic Version'],
        ], 'layouts.dashboard');
    }

    /** Update student. POST /api/students/{id} */
    public function update(Request $request): array
    {
        if (!Auth::check()) {
            Response::json(['status' => 'errors'], 401);
        }

        $id = (int) $request->params['id'];
        $student = Student::find($id);
        if ($student === null) {
            throw new \App\Core\HttpException(404, 'Student not found');
        }

        // RLS/BOLA: only the owner center user (or an admin) may update;
        // a moved center/institute must also stay inside the caller's scope.
        Auth::assertStudentAccess($id);

        // Re-validate only the fields being sent; keep existing values for omitted fields
        $rules = [
            'name'                 => 'nullable|string',
            'father_name'          => 'nullable|string',
            'mother_name'          => 'nullable|string',
            'phone'                => 'nullable|string|max:11',
            'email'                => 'nullable|email',
            'dob'                  => 'nullable|date_format:Y-m-d',
            'gender'               => 'nullable|in:1,2,3',
            'religion'             => 'nullable',
            'present_address'      => 'nullable|string|max:255',
            'permanent_address'    => 'nullable|string|max:255',
            'education_type'       => 'nullable|in:1,2,3',
            'class'                => 'nullable|in:1,2,3,4,5,6',
            'session'              => 'nullable|integer',
            'center_id'            => 'nullable|integer',
            'institute_id'         => 'nullable|integer',
            'division_id'          => 'nullable|integer',
            'district_id'          => 'nullable|integer',
            'upazila_id'           => 'nullable|integer',
            'union_id'             => 'nullable|integer',
            'photo'                => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'principal_name'       => 'nullable',
            'note'                 => 'nullable',
        ];

        try {
            $data = Validator::validate($request, $rules);
        } catch (\App\Core\ValidationException $e) {
            Response::validationError($e->errors, $e->getMessage());
        }

        // RLS/BOLA: a center user must not move a student into another center
        // or an institute outside their own center.
        if (isset($data['center_id']) && $data['center_id'] !== null && $data['center_id'] !== '') {
            Auth::assertCenterAccess((int) $data['center_id']);
        }
        if (isset($data['institute_id']) && $data['institute_id'] !== null && $data['institute_id'] !== '') {
            Auth::assertInstituteAccess((int) $data['institute_id']);
        }

        // Preserve existing values for fields not sent
        $merge = [
            'name'               => $data['name'] ?? $student['name'],
            'father_name'        => $data['father_name'] ?? $student['father_name'],
            'mother_name'        => $data['mother_name'] ?? $student['mother_name'],
            'phone'              => $data['phone'] ?? $student['phone'],
            'email'              => $data['email'] ?? $student['email'],
            'dob'                => $data['dob'] ?? $student['dob'],
            'gender'             => $data['gender'] !== null ? (int) $data['gender'] : (int) $student['gender'],
            'religion'           => $data['religion'] ?? $student['religion'],
            'present_address'    => $data['present_address'] ?? $student['present_address'],
            'permanent_address'  => $data['permanent_address'] ?? $student['permanent_address'],
            'education_type'     => $data['education_type'] ?? $student['education_type'],
            'class'              => $data['class'] !== null ? (int) $data['class'] : (int) $student['class'],
            'session'            => $data['session'] !== null ? (int) $data['session'] : (int) $student['session'],
            'center_id'          => $data['center_id'] ?? $student['center_id'],
            'institute_id'       => $data['institute_id'] ?? $student['institute_id'],
            'division_id'        => $data['division_id'] !== null ? (int) $data['division_id'] : (int) $student['division_id'],
            // Same geo-id rules as store(): unselected ('' -> NULL after
            // validation) keeps the student's current value, never '' into the
            // NOT NULL int columns (strict-MySQL 1366).
            'district_id'        => $data['district_id'] !== null ? (int) $data['district_id'] : (int) $student['district_id'],
            'upazila_id'         => $data['upazila_id'] !== null ? (int) $data['upazila_id'] : (int) $student['upazila_id'],
            'union_id'           => $data['union_id'] !== null ? (int) $data['union_id'] : $student['union_id'],
            'principal_name'     => $data['principal_name'] ?? $student['principal_name'],
            'note'               => $data['note'] ?? $student['note'],
            'updated_at'         => date('Y-m-d H:i:s'),
        ];

        // --- If center/institute/class changed, re-generate registration ---
        // Validated data carries every rule key (null when absent/''), so the
        // guards must match on null exactly like the $merge above — isset()
        // alone would treat a null (field not sent) as a change to (int) null
        // and silently move the student to center/institute 0.
        $regChanged = false;
        $newReg = $student['registration'];
        if ($data['center_id'] !== null && (int) $data['center_id'] !== (int) $student['center_id']) {
            $merge['center_id'] = (int) $data['center_id'];
            $regChanged = true;
        }
        if ($data['institute_id'] !== null && (int) $data['institute_id'] !== (int) $student['institute_id']) {
            $merge['institute_id'] = (int) $data['institute_id'];
            $regChanged = true;
        }
        if ($data['class'] !== null && (int) $data['class'] !== (int) $student['class']) {
            $merge['class'] = (int) $data['class'];
            $regChanged = true;
        }
        if ($regChanged) {
            // Re-apply registration algorithm (same as store)
            $sessionSuffix = substr((string) ($data['session'] ?? $student['session']), -2);
            $regBase = sprintf('%s%03d%04d%s001',
                $sessionSuffix,
                (int) ($data['center_id'] ?? $student['center_id']),
                (int) ($data['institute_id'] ?? $student['institute_id']),
                (int) ($data['class'] ?? $student['class']));

            $lastStudent = Database::selectOne(
                "SELECT registration FROM `students`
                   WHERE `center_id` = ? AND `institute_id` = ? AND `class` = ?
                     AND `session` = ? AND `registration` != ''
                   ORDER BY `registration` DESC LIMIT 1",
                [$merge['center_id'], $merge['institute_id'], (int) ($data['class'] ?? $student['class']), (int) ($data['session'] ?? $student['session'])]
            );

            $newReg = $regBase;
            if ($lastStudent && isset($lastStudent['registration'])) {
                $tail = (int) substr($lastStudent['registration'], -3);
                $newTail = $tail + 1;
                $newReg = substr($lastStudent['registration'], 0, -3) . sprintf('%03d', $newTail);
            }
            $merge['registration'] = $newReg;
        }

        // --- Photo upload (replace if new file posted) ---
        if ($request->hasFile('photo')) {
            // Delete old photo if exists
            if ($student['photo']) {
                @unlink(BASE_PATH . '/public/' . $student['photo']);
            }
            $photoPath = $this->savePhoto($request->file('photo'), 'photo', self::PHOTO_MAX_BYTES, self::PHOTO_MAX_DIM);
            if ($photoPath !== null) {
                $merge['photo'] = $photoPath;
            }
        }

        // --- Update student row and keep the duplicate-key registry in sync ---
        $oldDuplicateFingerprint = StudentService::duplicateFingerprint($student);
        $newDuplicateFingerprint = StudentService::duplicateFingerprint($merge);
        if ($newDuplicateFingerprint !== $oldDuplicateFingerprint && StudentService::isDuplicate($merge, $id)) {
            $message = 'A student with the same name, father name, mother name, phone, session, class, center, and institute already exists.';
            Response::validationError(['name' => $message], $message);
        }

        try {
            $updated = Database::transaction(function () use ($id, $merge, $oldDuplicateFingerprint, $newDuplicateFingerprint, $student): int {
                if ($newDuplicateFingerprint !== $oldDuplicateFingerprint) {
                    Database::insert('student_duplicate_keys', ['fingerprint' => $newDuplicateFingerprint]);
                }

                $updated = Student::updateRow($id, array_filter($merge));
                if ($newDuplicateFingerprint !== $oldDuplicateFingerprint && !StudentService::isDuplicate($student)) {
                    Database::delete('student_duplicate_keys', '`fingerprint` = ?', [$oldDuplicateFingerprint]);
                }
                return $updated;
            });
        } catch (\PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                $message = 'A student with the same name, father name, mother name, phone, session, class, center, and institute already exists.';
                Response::validationError(['name' => $message], $message);
            }
            throw $e;
        }

        // --- SMS on significant change ---
        if ($updated && isset($merge['registration']) && isset($merge['class']) && isset($merge['center_id']) && isset($merge['institute_id'])) {
            $msg = sprintf(
                'Student updated: %s | Reg: %s | Class: %s | Center: %s',
                $merge['name'] ?? $student['name'],
                $merge['registration'] ?? $student['registration'],
                $merge['class'] ?? $student['class'],
                $merge['center_id'] ?? $student['center_id']
            );
            send_sms_mask($student['phone'] ?? '', $msg);
        }

        return $this->sendResponse(
            ['id' => $id, 'registration' => $newReg ?? $student['registration']],
            'Student updated successfully.'
        );
    }

    /** Delete student. DELETE /api/students/{id} */
    public function destroy(Request $request): array
    {
        if (!Auth::check()) {
            Response::json(['status' => 'errors'], 401);
        }

        $id = (int) $request->params['id'];
        $student = Student::find($id);
        if ($student === null) {
            throw new \App\Core\HttpException(404, 'Student not found');
        }

        // RLS/BOLA: only the owner center user (or an admin) may delete.
        Auth::assertStudentAccess($id);

        // Delete photo file if exists
        if ($student['photo']) {
            @unlink(BASE_PATH . '/public/' . $student['photo']);
        }

        // Delete marks row if exists (marks live on students row in original, but there's also a marks table)
        // In original: marks config per session is separate; student marks are on the student row.
        // We'll just delete the student.
        $duplicateFingerprint = StudentService::duplicateFingerprint($student);
        $deleted = Database::transaction(function () use ($id, $student, $duplicateFingerprint): int {
            $deleted = Student::deleteRow($id);
            if ($deleted > 0 && !StudentService::isDuplicate($student)) {
                Database::delete('student_duplicate_keys', '`fingerprint` = ?', [$duplicateFingerprint]);
            }
            return $deleted;
        });

        return $this->sendResponse(
            ['deleted' => $deleted],
            'Student deleted successfully.'
        );
    }

    /** Delete student photo only. POST /api/students/{id}/delete-photo */
    public function deletePhoto(Request $request): array
    {
        if (!Auth::check()) {
            Response::json(['status' => 'errors'], 401);
        }

        $id = (int) ($request->params['id'] ?? 0);
        $student = Student::find($id);
        if ($student === null) {
            throw new \App\Core\HttpException(404, 'Student not found');
        }

        Auth::assertStudentAccess($id);

        if (empty($student['photo'])) {
            return $this->sendError('No photo to delete.', [], 422);
        }

        @unlink(BASE_PATH . '/public/' . $student['photo']);
        Student::updateRow($id, ['photo' => null]);

        return $this->sendResponse(['deleted' => true], 'Photo deleted successfully.');
    }

    /** Photo upload. POST /api/uploadFile */
    public function uploadFile(Request $request): array
    {
        if (!Auth::check()) {
            Response::json(['status' => 'errors'], 401);
        }

        // Rate limiting: transient storage abuse per IP (photo uploads).
        RateLimiter::hit('upload-file', 30, 60);

        if (!$request->hasFile('file') || $request->file('file')['error'] !== UPLOAD_ERR_OK) {
            return Response::validationError(['file' => ['No file uploaded']]);
        }

        // Hardened: real-image check (getimagesize) + detected-type extension
        // + random name, via the shared savePhoto() helper. Rejects scripts,
        // polyglots and oversized payloads with a 422.
        $photoPath = $this->savePhoto($request->file('file'), 'file');
        if ($photoPath === null) {
            return Response::validationError(['file' => ['No file uploaded']]);
        }

        return ['status' => 'success', 'file' => $photoPath, 'url' => '/' . $photoPath];
    }

    /**
     * Validate + store an uploaded image (photo field) into public/uploads/students.
     *
     * Hardened: the file must actually be a JPEG/PNG/GIF (getimagesize on the
     * uploaded bytes — never trust the client extension), the stored extension
     * is derived from the detected image type, and the name is a random hex
     * string, so a crafted polyglot is stored as .jpg/.png/.gif and served as
     * a static image — never executed by the PHP handler.
     *
     * @throws \App\Core\ValidationException 422 (upload/size/type failures)
     * @throws \App\Core\HttpException       500 (storage failures)
     * @return string|null relative path like uploads/students/std_ab12….jpg
     */
    private function savePhoto(?array $file, string $field = 'photo', int $maxBytes = 0, int $maxDim = 0): ?string
    {
        if ($file === null || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        $label = str_replace('_', ' ', $field);
        if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new \App\Core\ValidationException([$field => ["The $label failed to upload."]]);
        }
        if ((int) ($file['size'] ?? 0) > 2 * 1024 * 1024) {
            throw new \App\Core\ValidationException([$field => ["The $label may not be greater than 2048 kilobytes."]]);
        }
        $info = @getimagesize((string) ($file['tmp_name'] ?? ''));
        $allowed = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF];
        if ($info === false || !in_array((int) $info[2], $allowed, true)) {
            throw new \App\Core\ValidationException([$field => ["The $label must be an image."]]);
        }
        $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif'][(int) $info[2]];
        $dir = BASE_PATH . '/public/uploads/students/';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \App\Core\HttpException(500, 'Unable to create the upload directory.');
        }

        // Prefer GD: decode + re-encode so the stored bytes are a pure decoded
        // image and any PHP/HTML payload in a polyglot file is dropped.
        if (function_exists('imagecreatefromstring')) {
            $bin = @file_get_contents((string) ($file['tmp_name'] ?? ''));
            $img = is_string($bin) ? @imagecreatefromstring($bin) : false;
            if ($img === false) {
                throw new \App\Core\ValidationException([$field => ["The $label must be an image."]]);
            }
            if ($maxBytes > 0) {
                // Photos are downscaled and re-encoded as JPEG until they fit
                // the byte budget (e.g. <= 30 KB), keeping the stored file small.
                $out = $this->encodeJpegWithin($img, $maxBytes, $maxDim);
                $ext = 'jpg';
            } else {
                $encoder = [IMAGETYPE_JPEG => 'imagejpeg', IMAGETYPE_PNG => 'imagepng', IMAGETYPE_GIF => 'imagegif'][(int) $info[2]];
                ob_start();
                $encoded = $encoder($img);
                $out = (string) ob_get_clean();
                if (!$encoded || $out === '') {
                    $out = null;
                }
            }
            if ($out === null || $out === '') {
                throw new \App\Core\HttpException(500, 'Unable to process the uploaded image.');
            }
            $name = 'std_' . bin2hex(random_bytes(12)) . '.' . $ext;
            $ok = @file_put_contents($dir . $name, $out) !== false;
        } else {
            // GD unavailable — store the validated upload byte-for-byte with the
            // detected-type extension (never the client extension). The upload
            // dir .htaccess / nginx guards still block script execution.
            $name = 'std_' . bin2hex(random_bytes(12)) . '.' . $ext;
            $ok = @move_uploaded_file((string) ($file['tmp_name'] ?? ''), $dir . $name);
        }
        if (!$ok) {
            throw new \App\Core\HttpException(500, 'Unable to store the uploaded file.');
        }
        return 'uploads/students/' . $name;
    }

    /**
     * Downscale + re-encode an image as JPEG until it fits $maxBytes.
     *
     * Transparency is flattened onto white first, so converting a PNG/GIF
     * photo never produces black areas. (imagedestroy() is deprecated in
     * PHP 8.5 — GdImage objects free on reassignment, so the holders are
     * simply overwritten instead.)
     *
     * @param  \GdImage $img
     * @return string|null encoded JPEG bytes, or null when encoding fails
     */
    private function encodeJpegWithin($img, int $maxBytes, int $maxDim): ?string
    {
        $srcW = imagesx($img);
        $srcH = imagesy($img);
        $scale = ($maxDim > 0 && max($srcW, $srcH) > $maxDim) ? $maxDim / max($srcW, $srcH) : 1.0;
        $w = max(1, (int) round($srcW * $scale));
        $h = max(1, (int) round($srcH * $scale));

        $canvas = imagecreatetruecolor($w, $h);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $img, 0, 0, 0, 0, $w, $h, $srcW, $srcH);
        $img = $canvas;

        $quality = 82;
        $out = null;
        for ($i = 0; $i < 12; $i++) {
            ob_start();
            $ok = imagejpeg($img, null, $quality);
            $out = (string) ob_get_clean();
            if ($ok && $out !== '' && strlen($out) <= $maxBytes) {
                break;
            }
            if ($quality > 45) {
                $quality -= 7;
                continue;
            }
            // Still too large at low quality — shrink and retry.
            $w = max(60, (int) round($w * 0.85));
            $h = max(60, (int) round($h * 0.85));
            $next = imagecreatetruecolor($w, $h);
            imagefill($next, 0, 0, imagecolorallocate($next, 255, 255, 255));
            imagecopyresampled($next, $img, 0, 0, 0, 0, $w, $h, imagesx($img), imagesy($img));
            $img = $next;
            $quality = 75;
        }

        return ($out === null || $out === '') ? null : $out;
    }

    /**
     * Total students in the active session. GET /api/totalStudent
     * Center users (type 4) are scoped to their own centers (original behaviour).
     */
    public function totalStudent(): array
    {
        $session = Auth::activeSession();
        $centerId = Auth::centerId();

        if (!Auth::isAdmin() && $centerId !== null) {
            $total = (new Query('students'))
                ->join('centers', 'centers.id', '=', 'students.center_id')
                ->where('students.session', $session)
                ->where('centers.user_id', Auth::id())
                ->count();
        } else {
            $total = (new Query('students'))->where('session', $session)->count();
        }

        return ['totalstudents' => $total];
    }

    /**
     * Printable student registration card.
     * GET /api/registrationpdf/{registration}
     *
     * Staff-only (route middleware): the authority prints the card, and the
     * card's QR carries the association's contact details rather than a link
     * back here. The original took the registration NUMBER (not the row id)
     * and streamed a PDF; like the certificates this clone renders printable
     * HTML. Photos are embedded from the same public path the rest of the app
     * uses.
     *
     * The route segment is named {id}, so a row id is accepted as a fallback
     * (registration wins). Any other app link must pass the registration number.
     */
    public function registrationpdf(Request $request): string
    {
        $key = (string) $request->params['id'];
        $select = "SELECT s.*, i.`name` AS institute_name, i.`address` AS institute_address,
                    u.`name` AS center_name,
                    d.`name` AS division_name, dt.`name` AS district_name, uz.`name` AS upazila_name
               FROM `students` s
          LEFT JOIN `institutes` i ON i.`id` = s.`institute_id`
          LEFT JOIN `centers` c ON c.`id` = s.`center_id`
          LEFT JOIN `users` u ON u.`id` = c.`user_id`
          LEFT JOIN `divisions` d ON d.`id` = s.`division_id`
          LEFT JOIN `districts` dt ON dt.`id` = s.`district_id`
          LEFT JOIN `upozilas` uz ON uz.`id` = s.`upazila_id`";

        $student = Database::selectOne(
            $select . " WHERE s.`registration` = ? ORDER BY s.`id` ASC LIMIT 1",
            [$key]
        );
        if ($student === null && ctype_digit($key)) {
            $student = Database::selectOne(
                $select . " WHERE s.`id` = ? ORDER BY s.`id` ASC LIMIT 1",
                [(int) $key]
            );
        }
        if ($student === null) {
            throw new \App\Core\HttpException(404, 'Student not found');
        }

        // Center-user scoping: a center user cannot print another center's
        // student card (the route already requires a signed-in user).
        if (!Auth::isAdmin()) {
            $authCenterId = Auth::centerId();
            if ($authCenterId !== null && (int) $authCenterId !== (int) $student['center_id']) {
                Response::json(['message' => 'This action is unauthorized.'], 403);
            }
        }

        return View::render('students.registration_card', [
            'title'   => 'Registration Card ' . ($student['registration'] ?? ''),
            'student' => $student,
        ], 'layouts.guest');
    }
}
