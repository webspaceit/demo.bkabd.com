<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\Student;
use App\Core\Query;
use App\Services\SeatAssignmentService;

final class AdmitCardController extends Controller
{
    /** Fast, compact list search for the admit-card filter bar. */
    public function searchList(Request $request): array
    {
        if (!Auth::check()) { Response::json(['status' => 'errors'], 401); }
        if (!Auth::can('admit-card')) { Response::json(['message' => 'This action is unauthorized.'], 403); }

        $q = (new Query('students'))->select(
            'students.id', 'students.registration', 'students.roll', 'students.name',
            'students.father_name', 'students.mother_name', 'students.class',
            'users.name as center_name', 'institutes.name as institute_name'
        );
        $q->leftJoin('centers', 'centers.id', '=', 'students.center_id')
          ->leftJoin('users', 'users.id', '=', 'centers.user_id')
          ->leftJoin('institutes', 'institutes.id', '=', 'students.institute_id')
          ->whereRaw('`students`.`active` = 1');

        $countQuery = $q->withoutJoins();
        $session = $request->query('session');
        if ($session !== null && $session !== '') { $q->where('students.session', (int) $session); $countQuery->where('students.session', (int) $session); }
        $centerId = $request->query('center_id');
        if ($centerId !== null && $centerId !== '') { $q->where('students.center_id', (int) $centerId); $countQuery->where('students.center_id', (int) $centerId); }
        $instituteId = $request->query('institute_id');
        if ($instituteId !== null && $instituteId !== '') { $q->where('students.institute_id', (int) $instituteId); $countQuery->where('students.institute_id', (int) $instituteId); }
        $class = $request->query('class');
        if ($class !== null && $class !== '') { $q->where('students.class', (int) $class); $countQuery->where('students.class', (int) $class); }
        if (!Auth::isAdmin() && Auth::centerId() !== null) {
            $ownCenterId = (int) Auth::centerId();
            $q->where('students.center_id', $ownCenterId);
            $countQuery->where('students.center_id', $ownCenterId);
        }

        $search = trim((string) $request->query('search', ''));
        $classWord = match (strtolower($search)) { 'kg' => 6, 'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, default => null };
        if ($classWord !== null) {
            $q->where('students.class', $classWord);
            $countQuery->where('students.class', $classWord);
        } elseif ($search !== '' && ctype_digit($search)) {
            $params = [$search . '%', $search];
            $q->whereRaw('(`students`.`registration` LIKE ? OR `students`.`roll` = ?)', $params);
            $countQuery->whereRaw('(`students`.`registration` LIKE ? OR `students`.`roll` = ?)', $params);
        } elseif ($search !== '') {
            $like = '%' . $search . '%';
            $q->whereRaw('(`students`.`name` LIKE ? OR `students`.`registration` LIKE ? OR `students`.`roll` LIKE ? OR `users`.`name` LIKE ? OR `institutes`.`name` LIKE ?)', [$like, $like, $like, $like, $like]);
            $countQuery->whereRaw(
                '(`students`.`name` LIKE ? OR `students`.`registration` LIKE ? OR `students`.`roll` LIKE ?'
                . ' OR `students`.`center_id` IN (SELECT c.`id` FROM `centers` c JOIN `users` u ON u.`id` = c.`user_id` WHERE u.`name` LIKE ?)'
                . ' OR `students`.`institute_id` IN (SELECT i.`id` FROM `institutes` i WHERE i.`name` LIKE ?))',
                [$like, $like, $like, $like, $like]
            );
        }

        $q->orderBy('students.created_at', 'desc')->orderBy('students.id', 'desc');
        return $this->sendResponse(
            $q->paginate((int) $request->query('per_page', 20), null, '/api/admitcards/search', $countQuery),
            'Admit-card student list retrieved.'
        );
    }

    /**
     * Fetch admit cards data for given institute/center and class
     * Original route: GET /admitcards/{id}/{class}
     */
    public function fetchAdmitCards(Request $request): mixed
    {
        if (!Auth::check()) {
            Response::json(['status' => 'errors'], 401);
        }
        if (!Auth::can('admit-card')) {
            Response::json(['message' => 'This action is unauthorized.'], 403);
        }

        $id = (int) $request->params['id'];
        $class = (int) $request->params['class'];

        $activeSession = $this->activeSession();

        // RLS/BOLA: admit cards are per-institute — the institute must be the
        // caller's own when the caller is a center user.
        Auth::assertInstituteAccess($id);

        // Determine if id is center or institute based on usage
        // In original, sometimes center_id is passed; check the seatplan usage - seatplan_print takes center_id/class
        // But admitcards in original takes institute_id/class based on the code we saw earlier
        $students = Student::query()
            ->where('institute_id', $id)
            ->where('session', $activeSession)
            ->where('class', $class)
            // Approval workflow: pending registrations get no admit card.
            ->where('active', 1)
            ->orderBy('roll', 'asc')
            ->get();

        $data = [
            'title' => '',
            'activeSession' => $activeSession,
            'date' => date('m/d/Y'),
            'students' => $students,
        ];

        // Return HTML view similar to original
        return $this->renderAdmitCards($students, $activeSession);
    }

    /**
     * Print admit cards - generates HTML for printing
     * Original: GET /admitcard_print/{id}/{class}
     */
    public function printAdmitCards(Request $request): mixed
    {
        if (!Auth::check()) {
            Response::json(['status' => 'errors'], 401);
        }
        if (!Auth::can('admit-card')) {
            Response::json(['message' => 'This action is unauthorized.'], 403);
        }

        $id = (int) $request->params['id'];
        $class = (int) $request->params['class'];
        $activeSession = $this->activeSession();

        // RLS/BOLA: same ownership rule for the printable admit cards.
        Auth::assertInstituteAccess($id);

        // The registration-card layout also needs the resolved center, institute,
        // and location labels, so load them with the students in one query.
        $students = Database::select(
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
              WHERE s.`institute_id` = ? AND s.`session` = ? AND s.`class` = ?
                AND s.`active` = 1
           ORDER BY s.`roll` ASC",
            [$id, $activeSession, $class]
        );

        $students = SeatAssignmentService::attachLocations($students);

        return View::render('students.registration_card', [
            'title' => 'Admit Cards - Class ' . $class,
            'students' => $students,
            'cardMode' => 'admit',
            'examRoutines' => $this->activeExamRoutines($activeSession),
        ], 'layouts.guest');
    }

    /** Print one selected student's admit card. GET /api/admitcard_student/{id}. */
    public function printStudentAdmitCard(Request $request): string
    {
        $studentId = (int) $request->params['id'];
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
              WHERE s.`id` = ? AND s.`active` = 1
              LIMIT 1",
            [$studentId]
        );

        if ($student === null) {
            throw new \App\Core\HttpException(404, 'Active student not found');
        }

        Auth::assertStudentAccess($studentId);

        $activeSession = $this->activeSession();

        $student = SeatAssignmentService::attachLocations([$student])[0];

        return View::render('students.registration_card', [
            'title'    => 'Admit Card - ' . ($student['registration'] ?? ''),
            'students' => [$student],
            'cardMode' => 'admit',
            'hideLoginLink' => true,
            'examRoutines' => $this->activeExamRoutines($student['session'] ?? $activeSession),
        ], 'layouts.guest');
    }

    /** Print every active admit card for an institute, optionally one class. */
    public function printByInstitute(Request $request): string
    {
        return $this->printInstituteBatch($request, null);
    }

    /** Print active admit cards for one institute class. */
    public function printByClass(Request $request): string
    {
        $class = (int) ($request->params['class'] ?? 0);
        if (!in_array($class, [1, 2, 3, 4, 5, 6], true)) {
            throw new \App\Core\HttpException(422, 'Select a valid class.');
        }
        return $this->printInstituteBatch($request, $class);
    }

    private function printInstituteBatch(Request $request, ?int $class): string
    {
        if (!Auth::check()) {
            Response::json(['status' => 'errors'], 401);
        }
        if (!Auth::can('admit-card')) {
            Response::json(['message' => 'This action is unauthorized.'], 403);
        }

        $instituteId = (int) ($request->params['id'] ?? 0);
        Auth::assertInstituteAccess($instituteId);
        $institute = Database::selectOne('SELECT `name` FROM `institutes` WHERE `id` = ? LIMIT 1', [$instituteId]);
        if ($institute === null) {
            throw new \App\Core\HttpException(404, 'Institute not found.');
        }

        $session = $request->query('session');
        if ($session === null) {
            $session = (string) $this->activeSession();
        } else {
            $session = trim((string) $session);
        }

        $where = ['s.`active` = 1', 's.`institute_id` = ?'];
        $params = [$instituteId];
        if ($session !== '') {
            $where[] = 's.`session` = ?';
            $params[] = $session;
        }
        if ($class !== null) {
            $where[] = 's.`class` = ?';
            $params[] = $class;
        }

        $students = Database::select(
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
              WHERE " . implode(' AND ', $where) . "
           ORDER BY FIELD(s.`class`,6,1,2,3,4,5), s.`roll` ASC",
            $params
        );

        $students = SeatAssignmentService::attachLocations($students);
        $sessionLabel = $session === '' ? 'All Sessions' : $session;
        $scopeLabel = $class === null ? 'All Classes' : 'Class ' . ClassName($class);
        $routines = $session === ''
            ? $this->activeExamRoutinesForAllSessions()
            : $this->activeExamRoutines($session);

        return View::render('admitcards.center_print', [
            'title' => 'Admit Cards — ' . $institute['name'] . ' — ' . $scopeLabel,
            'printTitle' => 'Admit Cards — ' . $institute['name'] . ' — ' . $scopeLabel,
            'centerName' => $institute['name'] . ' — ' . $scopeLabel,
            'activeSession' => $sessionLabel,
            'students' => $students,
            'cardMode' => 'admit',
            'twoPerPage' => true,
            'hideLoginLink' => true,
            'examRoutines' => $routines,
        ], 'layouts.guest');
    }

    /** Active routines for an all-session print, if the routines table exists. */
    private function activeExamRoutinesForAllSessions(): array
    {
        try {
            return Database::select(
                "SELECT `subject`, `date`, `day`, `time`, `session`, `class`
                   FROM `routines` WHERE `active` = 1
               ORDER BY COALESCE(`order`, 2147483647) ASC, `id` ASC"
            );
        } catch (\PDOException $e) {
            if (in_array((string) $e->getCode(), ['42S02', '42S22'], true)) {
                return [];
            }
            throw $e;
        }
    }

    /**
     * Print all admit cards for an entire center — 2 cards per A4 page.
     * GET /api/admitcard_center/{id}   where {id} = center_id (0 = all centers for admin)
     */
    public function printByCenter(Request $request): string
    {
        if (!Auth::check()) {
            Response::json(['status' => 'errors'], 401);
        }
        if (!Auth::can('admit-card')) {
            Response::json(['message' => 'This action is unauthorized.'], 403);
        }

        $centerId = (int) $request->params['id'];
        $session = $request->query('session');
        $session = $session === null ? (string) $this->activeSession() : trim((string) $session);

        // RLS: center users are pinned to their own center
        if (!Auth::isAdmin()) {
            $mine = (int) (Auth::centerId() ?? 0);
            if ($centerId === 0) {
                $centerId = $mine;
            } else {
                Auth::assertCenterAccess($centerId);
            }
        }

        $where = ['s.`active` = 1'];
        $params = [];
        if ($session !== '') {
            $where[] = 's.`session` = ?';
            $params[] = $session;
        }
        if ($centerId > 0) {
            $where[] = 's.`center_id` = ?';
            $params[] = $centerId;
        }

        $students = Database::select(
            "SELECT s.*, i.`name` AS institute_name, i.`address` AS institute_address,
                    u.`name` AS center_name,
                    d.`name` AS division_name, dt.`name` AS district_name, uz.`name` AS upazila_name
               FROM `students` s
          LEFT JOIN `institutes` i  ON i.`id`  = s.`institute_id`
          LEFT JOIN `centers` c     ON c.`id`  = s.`center_id`
          LEFT JOIN `users` u       ON u.`id`  = c.`user_id`
          LEFT JOIN `divisions` d   ON d.`id`  = s.`division_id`
          LEFT JOIN `districts` dt  ON dt.`id` = s.`district_id`
          LEFT JOIN `upozilas` uz   ON uz.`id` = s.`upazila_id`
              WHERE " . implode(' AND ', $where) . "
           ORDER BY u.`name` ASC, FIELD(s.`class`,6,1,2,3,4,5), s.`roll` ASC",
            $params
        );

        if (empty($students)) {
            return '<html><body style="font-family:sans-serif;padding:2rem">'
                . '<h2>No active students found for this center.</h2></body></html>';
        }

        $centerName = $centerId > 0
            ? (Database::selectOne(
                'SELECT u.`name` FROM `centers` c JOIN `users` u ON u.`id` = c.`user_id` WHERE c.`id` = ?',
                [$centerId]
              )['name'] ?? 'Center')
            : 'All Centers';

        $students = SeatAssignmentService::attachLocations($students);
        $sessionLabel = $session === '' ? 'All Sessions' : $session;
        $routines = $session === ''
            ? $this->activeExamRoutinesForAllSessions()
            : $this->activeExamRoutines($session);

        return View::render('admitcards.center_print', [
            'title'      => 'Admit Cards — ' . $centerName,
            'centerName' => $centerName,
            'activeSession' => $sessionLabel,
            'students'   => $students,
            'cardMode'   => 'admit',
            'twoPerPage' => true,
            'hideLoginLink' => true,
            'examRoutines' => $routines,
        ], 'layouts.guest');
    }

    /** Active routines assigned to the selected exam session. */
    private function activeExamRoutines(string|int $session): array
    {
        try {
            return Database::select(
                "SELECT `subject`, `date`, `day`, `time`, `session`, `class`
                   FROM `routines`
                  WHERE `active` = 1 AND `session` = ?
               ORDER BY COALESCE(`order`, 2147483647) ASC, `id` ASC",
                [$session]
            );
        } catch (\PDOException $e) {
            // Keep admit cards printable while an older database awaits the
            // routine-session migration; the schedule can be added after upgrade.
            if (in_array((string) $e->getCode(), ['42S02', '42S22'], true)) {
                return [];
            }
            throw $e;
        }
    }

    private function renderAdmitCards($students, $activeSession): string
    {
        $str = "<style media='print'>
                .noprint
                  {
                   display:none;
                  }
                  @media print {
                     body {-webkit-print-color-adjust: exact;font-family: Arial, Helvetica, sans-serif;}
                   }
                  .content{
                     border-style: solid;
                     page-break-after: always;
                    
                   }
                   .content1{
                     border-style: solid;
                     margin:10px;
                     padding:10px;
                     line-height: 1;
                     
                   }
                   
                   .date{
                     text-align: center;
                   }
                  
                   .logo img {
                     
                     border-radius: 50%;
                     width:75px;
                     height:75px;
                     }
                     .student img {
                     display: block;
                     width:120.5px;
                     height:146px;
                     padding:2px;
                     }
                     .student {
                     border: 1px solid;
                     padding:2px;
                     }
                 .second{
                     margin-top:15px;
                 }
                 .card3 {
                     text-align: center;
                     background-color: black;
                     color: white;
                     width: 60%;
                     padding-top: 7px;
                     padding-bottom: 7px;
                     border-radius: 15px;
                     line-height: 23px;
                     margin:0 auto;
                     font-size:30px;
                     margin-bottom:17px;
                     
                 }
                 .card2{
                     text-align: center;
                     font-size:21px;
                     
                 }
                 .head{
                     text-align: center;
                     margin-bottom:0px;
                     font-size:25px;
                     font-weight:bold;
                 }
                 .head2{
                     text-align: center;
                     margin-bottom: 2px;
                 }
                 .head3{
                     text-align: center;
                     font-size:14px;    
                     margin-bottom: 0px;
                     margin-top: 2px;
                     
                 }
                 .head4{
                     text-align: center;
                     font-size:12px;
                     line-height: 0.1;
                     margin-top: 5px;
                     
                 }
                 .left{
                     float:left;
                     
                    
                 }
                 .right{
                     float:right;
                     margin-right:10px
                    
                 }
                 .clear{
                     clear:both;
                 }
                 .sub table th{
                   border: 1px solid;
                   text-align: center;
                   line-height: 1.5;
                 }
                 .qr{
                     text-align: center;
                     margin-top:10px;
                 }
                 .sign img{
                     width: 70%;
                     height: 45px;
                     text-align: center;
                 }
                 .sign p{
                     margin-top:-10px;
                     
                 }
                 table{
                     font-size: 20px;
                     font-weight:normal;
                 }
                 th {
                   padding-top: 3px;
                   padding-bottom: 3px;
                 }
                 h2, .card2, h3, h4, h5{
                 line-height:15px;
                 margin-top:6px;
                 }
                 hr{border:3px solid #333;}
                 .nonbold{
                     font-weight:normal;
                 }
                 
                 .item-one {
                   width: 20%;
                   float: left;
                 }
                 .item-two {
                     width: 59%;
                     float: left;
                 }
                 .item-three {
                     width: 20%;
                     float: right;
                 }
               
               </style>";
        foreach ($students as $student) {
            $className = $this->getClassName((int)($student['class'] ?? 0));
            $str .= '
                  <div class="content">
                  <div class="content1">
                      <div>
                          <div class="logo left">
                              <img src="/logo2.png">
                          </div>
                          <div style="margin-top:5px;" class="right">
                              <h2 class="head">BANGLADESH KINDERGARTEN ASSOCIATION</h2>
                              <h4 class="head2">Govt. Reg. No.: S-1028/98</h4>
                              <p class="head3">59/3, Brammonchiron, Jatrabari, Dhaka-1203, Bangladesh, Mobile: +88-02-224458009</p>
                              <p class="head4">Email: infobkabd@gmail.com, info@bkabd.com, Web: www.bkabd.com/www.bka.com.bd/www.bka.org.bd</p>
                          </div>
                          <div class="clear"></div>
                      </div>
                      <div class="second">
                          <div class="item-one">
                              <img src="/images/authority/qrcode.png">
                          </div>
                          <div class="item-two">
                              <p class="card3"><b>Admit Card</b></p>
                              <h3 class="card2">Scholarship Examination - ' . e($student['session'] ?? $activeSession) . '</h3>
                          </div>
                          <div class="item-three">
                              <div class="student">';
            if (($student['photo'] ?? '') && file_exists(BASE_PATH . '/public/images/students/' . $student['photo'])) {
                $str .= ' <img src="/images/students/' . e($student['photo']) . '">';
            }
            $str .= '
                              </div>
                          </div>
                          <div class="clear"></div>
                      </div>
                      <div class="sub">
                          <table class="table table-bordered">
                              <tr>
                                  <th class="nonbold">Student\'s Name</th>
                                  <th class="nonbold">' . e($student['name'] ?? '') . '</th>
                              </tr>
                              <tr>
                                  <th class="nonbold">Father\'s Name</th>
                                  <th class="nonbold">' . e($student['father_name'] ?? '') . '</th>
                              </tr>
                              <tr>
                                  <th class="nonbold">Mother\'s Name</th>
                                  <th class="nonbold">' . e($student['mother_name'] ?? '') . '</th>
                              </tr>
                              <tr>
                                  <th class="nonbold">Date of Birth</th>
                                  <th class="nonbold">' . e($student['dob'] ?? '') . '</th>
                              </tr>
                              <tr>
                                  <th class="nonbold">Registration No.</th>
                                  <th class="nonbold">' . e($student['registration'] ?? '') . '</th>
                              </tr>
                              <tr>
                                  <th class="nonbold">Roll No.</th>
                                  <th class="nonbold">' . e($student['roll'] ?? '') . '</th>
                              </tr>
                              <tr>
                                  <th class="nonbold">Exam. Class</th>
                                  <th class="nonbold">' . e($className) . '</th>
                              </tr>
                              <tr>
                                  <th class="nonbold">Exam. Date & Time</th>
                                  <th class="nonbold">As per routine</th>
                              </tr>
                              <tr>
                                  <th class="nonbold">Exam. Center</th>
                                  <th class="nonbold">' . e($student['center_name'] ?? '') . '</th>
                              </tr>
                              <tr>
                                  <th class="nonbold">Exam. Venue</th>
                                  <th class="nonbold">' . e($student['institute_name'] ?? '') . '</th>
                              </tr>
</table>
                       </div>
                       <div style="margin:8px 4px 6px;font-size:13px;line-height:1.6;">
                           <b>নিয়মাবলী:</b>
                           ১। পরীক্ষা শুরুর কম পক্ষে ১৫ মিনিট আগে পরীক্ষা হলে উপস্থিত থাকতে হবে।
                           ২। পরীক্ষা হলে কোনো ধরনের কাগজপত্র বা বই নিয়ে প্রবেশ করা যাবে না।
                           ৩। পরীক্ষা প্রবেশ পত্র ছাড়া কোনো শিক্ষার্থী পরীক্ষায় অংশগ্রহণ করতে পারবে না।
                           ৪। পরীক্ষা হলে কোনো অনৈতিক বা অনাকাঙ্ক্ষিত ঘটনা ঘটানো যাবে না।
                           ৫। পরীক্ষা সময় মোবাইল ফোন ব্যবহার করা যাবে না।
                       </div>
                       <div style="margin-top:-15px;line-height:1.5">
                          <div class="left" style="width:20%;margin-top:40px;">
                              <div class="sign">
                                  <img src="/images/authority/head.gif">
                                  <p>Exam Controller</p>
                              </div>
                          </div>
                          <div class="left" style="width:60%;">
                              <div class="qr">
                                  <img src="/images/authority/qr.png" style="width:100px;">
                              </div>
                          </div>
                          <div class="right" style="width:20%;margin-top:40px;">
                              <div class="sign">
                                  <img src="/images/authority/sign2.gif">
                                  <p>Director</p>
                              </div>
                          </div>
                      </div>
                      <div class="clear"></div>
                      <div style="margin-top:10px;">
                          <hr style="margin-bottom:5px;">
                          <span style="font-size:10px;margin-bottom:0px;">© Copyright 1984 - ' . e($activeSession) . ' Bangladesh Kindergarten Association. All Rights Reserved. Developed by BKABD</span>
                      </div>
                  </div>
              </div>';
        }
        $brandPhone = brand('phone');
        $brandEmail = brand('email');
        $brandWebsite = brand('website');
        $str = str_replace(
            [
                'src="/logo2.png"',
                'BANGLADESH KINDERGARTEN ASSOCIATION',
                'Govt. Reg. No.: S-1028/98',
                '59/3, Brammonchiron, Jatrabari, Dhaka-1203, Bangladesh, Mobile: +88-02-224458009',
                'Email: infobkabd@gmail.com, info@bkabd.com, Web: www.bkabd.com/www.bka.com.bd/www.bka.org.bd',
                'Scholarship Examination - ',
                '© Copyright 1984 - ' . e($activeSession) . ' Bangladesh Kindergarten Association. All Rights Reserved. Developed by BKABD',
            ],
            [
                'src="' . e(brand_asset('logo_path', '/assets/img/result/logo.png')) . '"',
                e(brand('authority_name')),
                e(brand('registration_label')) . ': ' . e(brand('registration_no')),
                e(brand('address')) . ($brandPhone !== '' ? ', Mobile: ' . e($brandPhone) : ''),
                ($brandEmail !== '' ? 'Email: ' . e($brandEmail) : '') . ($brandWebsite !== '' ? ', Web: ' . e($brandWebsite) : ''),
                e(brand('exam_name')) . ' - ',
                '© ' . date('Y') . ' ' . e(brand('authority_name')) . '. ' . e(brand('copyright_text')) . ' ' . e(brand('developer_credit')),
            ],
            $str
        );
        return $str;
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
