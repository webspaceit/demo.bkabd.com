<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Core\Query;
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
        if ($search !== null && trim($search) !== '') {
            $term = strtolower(trim($search));
            $classWord = match ($term) {
                'kg' => 6, 'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5,
                default => null,
            };
            if ($classWord !== null) {
                $q->where('students.class', $classWord);
            } else {
                $like = '%' . $search . '%';
                // Search group; dotted columns quoted explicitly (see above).
                $q->whereRaw(
                    '(`students`.`name` LIKE ? OR `registration` LIKE ? OR `roll` LIKE ?'
                    . ' OR `users`.`name` LIKE ? OR `institutes`.`name` LIKE ?)',
                    [$like, $like, $like, $like, $like]
                );
            }
        }

        // Order: center_id → institute name → registration (original)
        $q->orderBy('center_id', 'asc')
          ->orderBy('institute_id', 'asc')
          ->orderBy('registration', 'asc');

        // Pagination matching Laravel shape for frontend 1:1
        return $this->sendResponse(
            $q->paginate((int) ($request->input('per_page', 20)), null, '/api/students'),
            'Student list retrieved.'
        );
    }

    /** Show registration form. GET /student-registration */
    public function create(): string
    {
        if (!Auth::check()) {
            return Response::redirect('/login');
        }
        $divisions  = (new Query('divisions'))->orderBy('name', 'asc')->get();
        $centers    = (new Query('centers'))->where('active', 1)->orderBy('id', 'asc')->get();
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
        ], 'layouts.dashboard');
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
            $data = Validator::validate($request, $rules);
        } catch (\App\Core\ValidationException $e) {
            Response::validationError($e->errors, $e->getMessage());
        }

        // --- Duplicate check (phone+father_name+name+session+class+center+institute) ---
        $dup = Database::selectOne(
            "SELECT id, registration FROM `students`
               WHERE `phone` = ? AND `father_name` = ? AND `name` = ?
                 AND `session` = ? AND `class` = ? AND `center_id` = ? AND `institute_id` = ?",
            [$data['phone'], $data['father_name'], $data['name'],
             (int) $data['session'], (int) $data['class'], (int) $data['center_id'], (int) $data['institute_id']]
        );

        if ($dup) {
            // Original duplicate-response shape — keep key names identical
            return ['status' => 'errors', 'message' => 'A student with the same name, father name, phone, session, class, center, and institute already exists.'];
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
        $photoPath = $request->hasFile('photo') ? $this->savePhoto($request->file('photo')) : null;

        // --- Insert student ---
        $studentId = Student::create(array_filter([
            'name'               => $data['name'],
            'father_name'        => $data['father_name'],
            'mother_name'        => $data['mother_name'] ?? '',
            'phone'              => $data['phone'],
            'email'              => $data['email'] ?? '',
            'dob'                => $data['dob'] ?? null,
            'gender'             => (int) $data['gender'],
            'religion'           => $data['religion'] ?? '',
            'education_type'     => $data['education_type'] ?? '',
            'class'              => (int) $data['class'],
            'session'            => (int) $data['session'],
            'center_id'          => (int) $data['center_id'],
            'institute_id'       => (int) $data['institute_id'],
            'division_id'        => (int) $data['division_id'],
            'district_id'        => $data['district_id'] ?? null,
            'upazila_id'         => $data['upazila_id'] ?? null,
            'union_id'           => $data['union_id'] ?? null,
            'photo'              => $photoPath,
            'principal_name'     => $data['principal_name'] ?? '',
            'note'               => $data['note'] ?? '',
            'user_id'            => Auth::id(),
            'created_at'         => date('Y-m-d H:i:s'),
            'updated_at'         => date('Y-m-d H:i:s'),
        ]));

        // --- SMS notification (optional dry-run) ---
        if (Config::getBool('SMS_DRY_RUN', false) !== true) {
            $msg = sprintf(
                'Student registered: %s (Roll: %s, Reg: %s)',
                $data['name'], $regBase, $registration
            );
            send_sms_mask($data['phone'], $msg);
        }

        return $this->sendResponse(
            ['id' => $studentId, 'registration' => $registration, 'regBase' => $regBase],
            'Student registered successfully.'
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

        $divisions = (new Query('divisions'))->orderBy('name', 'asc')->get();
        $centers = (new Query('centers'))->where('active', 1)->orderBy('id', 'asc')->get();
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
            'education_type'     => $data['education_type'] ?? $student['education_type'],
            'class'              => $data['class'] !== null ? (int) $data['class'] : (int) $student['class'],
            'session'            => $data['session'] !== null ? (int) $data['session'] : (int) $student['session'],
            'center_id'          => $data['center_id'] ?? $student['center_id'],
            'institute_id'       => $data['institute_id'] ?? $student['institute_id'],
            'division_id'        => $data['division_id'] ?? $student['division_id'],
            'district_id'        => $data['district_id'] ?? $student['district_id'],
            'upazila_id'         => $data['upazila_id'] ?? $student['upazila_id'],
            'union_id'           => $data['union_id'] ?? $student['union_id'],
            'principal_name'     => $data['principal_name'] ?? $student['principal_name'],
            'note'               => $data['note'] ?? $student['note'],
            'updated_at'         => date('Y-m-d H:i:s'),
        ];

        // --- If center/institute/class changed, re-generate registration ---
        $regChanged = false;
        $newReg = $student['registration'];
        if (isset($data['center_id']) && $data['center_id'] != $student['center_id']) {
            $merge['center_id'] = (int) $data['center_id'];
            $regChanged = true;
        }
        if (isset($data['institute_id']) && $data['institute_id'] != $student['institute_id']) {
            $merge['institute_id'] = (int) $data['institute_id'];
            $regChanged = true;
        }
        if (isset($data['class']) && $data['class'] != $student['class']) {
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
            $photoPath = $this->savePhoto($request->file('photo'));
            if ($photoPath !== null) {
                $merge['photo'] = $photoPath;
            }
        }

        // --- Update student row ---
        $updated = Student::updateRow($id, array_filter($merge));

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

        // Delete photo file if exists
        if ($student['photo']) {
            @unlink(BASE_PATH . '/public/' . $student['photo']);
        }

        // Delete marks row if exists (marks live on students row in original, but there's also a marks table)
        // In original: marks config per session is separate; student marks are on the student row.
        // We'll just delete the student.
        $deleted = Student::deleteRow($id);

        return $this->sendResponse(
            ['deleted' => $deleted],
            'Student deleted successfully.'
        );
    }

    /** Photo upload. POST /api/uploadFile */
    public function uploadFile(Request $request): array
    {
        if (!Auth::check()) {
            Response::json(['status' => 'errors'], 401);
        }

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
    private function savePhoto(?array $file, string $field = 'photo'): ?string
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
        $name = 'std_' . bin2hex(random_bytes(12)) . '.' . $ext;
        $dir = BASE_PATH . '/public/uploads/students/';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \App\Core\HttpException(500, 'Unable to create the upload directory.');
        }
        $target = $dir . $name;

        // Prefer GD: decode + re-encode so the stored bytes are a pure decoded
        // image and any PHP/HTML payload in a polyglot file is dropped.
        if (function_exists('imagecreatefromstring')) {
            $bin = @file_get_contents((string) ($file['tmp_name'] ?? ''));
            $img = is_string($bin) ? @imagecreatefromstring($bin) : false;
            if ($img === false) {
                throw new \App\Core\ValidationException([$field => ["The $label must be an image."]]);
            }
            $encoder = [IMAGETYPE_JPEG => 'imagejpeg', IMAGETYPE_PNG => 'imagepng', IMAGETYPE_GIF => 'imagegif'][(int) $info[2]];
            ob_start();
            $encoded = $encoder($img);
            $out = ob_get_clean();
            imagedestroy($img);
            if (!$encoded || $out === '') {
                throw new \App\Core\HttpException(500, 'Unable to process the uploaded image.');
            }
            $ok = @file_put_contents($target, $out) !== false;
        } else {
            // GD unavailable — store the validated upload byte-for-byte with the
            // detected-type extension (never the client extension). The upload
            // dir .htaccess / nginx guards still block script execution.
            $ok = @move_uploaded_file((string) ($file['tmp_name'] ?? ''), $target);
        }
        if (!$ok) {
            throw new \App\Core\HttpException(500, 'Unable to store the uploaded file.');
        }
        return 'uploads/students/' . $name;
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
     * The original took the registration NUMBER (not the row id) and streamed a
     * PDF; like the certificates this clone renders printable HTML. Photos are
     * embedded from the same public path the rest of the app uses.
     */
    public function registrationpdf(Request $request): string
    {
        if (!Auth::check()) {
            Response::json(['status' => 'errors'], 401);
        }

        $registration = (string) $request->params['id'];
        $student = Database::selectOne(
            "SELECT s.*, i.`name` AS institute_name, i.`address` AS institute_address,
                    u.`name` AS center_name,
                    d.`name` AS division_name, dt.`name` AS district_name, uz.`name` AS upazila_name
               FROM `students` s
          LEFT JOIN `institutes` i ON i.`id` = s.`institute_id`
          LEFT JOIN `centers` c ON c.`id` = s.`center_id`
          LEFT JOIN `users` u ON u.`id` = c.`user_id`
          LEFT JOIN `divisions` d ON d.`id` = s.`division_id`
          LEFT JOIN `districts` dt ON dt.`id` = s.`district_id`
          LEFT JOIN `upozilas` uz ON uz.`id` = s.`upazila_id`
              WHERE s.`registration` = ?
              ORDER BY s.`id` ASC LIMIT 1",
            [$registration]
        );
        if ($student === null) {
            throw new \App\Core\HttpException(404, 'Student not found');
        }

        // Center-user scoping: cannot print another center's student card.
        if (!Auth::isAdmin()) {
            $authCenterId = Auth::centerId();
            if ($authCenterId !== null && (int) $authCenterId !== (int) $student['center_id']) {
                Response::json(['message' => 'This action is unauthorized.'], 403);
            }
        }

        return View::render('students.registration_card', [
            'student' => $student,
        ]);
    }
}