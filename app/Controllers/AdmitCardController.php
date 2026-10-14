<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\Student;
use App\Core\Query;

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

        // In original admitcard_print takes institute_id
        $students = Student::query()
            ->where('institute_id', $id)
            ->where('session', $activeSession)
            ->where('class', $class)
            // Approval workflow: pending registrations get no admit card.
            ->where('active', 1)
            ->orderBy('roll', 'asc')
            ->get();

        return $this->renderAdmitCards($students, $activeSession);
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
