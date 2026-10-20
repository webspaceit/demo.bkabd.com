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

        $centerId      = (int) $request->params['id'];
        $activeSession = $this->activeSession();

        // RLS: center users are pinned to their own center
        if (!Auth::isAdmin()) {
            $mine = (int) (Auth::centerId() ?? 0);
            if ($centerId === 0) {
                $centerId = $mine;
            } else {
                Auth::assertCenterAccess($centerId);
            }
        }

        $params = [$activeSession];
        $centerWhere = '';
        if ($centerId > 0) {
            $centerWhere = ' AND s.`center_id` = ?';
            $params[]    = $centerId;
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
              WHERE s.`session` = ? AND s.`active` = 1" . $centerWhere . "
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

        return View::render('admitcards.center_print', [
            'title'      => 'Admit Cards — ' . $centerName,
            'centerName' => $centerName,
            'activeSession' => $activeSession,
            'students'   => $students,
            'cardMode'   => 'admit',
            'twoPerPage' => true,
            'examRoutines' => $this->activeExamRoutines($activeSession),
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
