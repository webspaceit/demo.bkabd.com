<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Models\Student;

final class SeatPlanController extends Controller
{
    /**
     * Fetch seat plan data
     */
    public function fetchSeatPlan(Request $request): mixed
    {
        if (!Auth::check()) {
            Response::json(['status' => 'errors'], 401);
        }
        if (!Auth::can('seatplan')) {
            Response::json(['message' => 'This action is unauthorized.'], 403);
        }

        $id = (int) $request->params['id']; // center_id or institute_id
        $class = (int) $request->params['class'];
        $activeSession = $this->activeSession();

        // In original, seatplan is called with institute_id/class based on some routes,
        // but seatplan_print takes center_id/class - need to check context
        $students = Student::query()
            ->select('id', 'session', 'name', 'class', 'roll', 'center_id', 'institute_id')
            ->where('institute_id', $id)
            ->where('session', $activeSession)
            ->where('class', $class)
            ->orderBy('roll', 'asc')
            ->get();

        // Return view for seatplan (HTML)
        return $this->renderSeatPlan($students, $activeSession, $id, $class);
    }

    /**
     * Print seat plan
     * Original: GET /api/seatplan_print/{center_id}/{class}
     */
    public function printSeatPlan(Request $request): mixed
    {
        if (!Auth::check()) {
            Response::json(['status' => 'errors'], 401);
        }
        if (!Auth::can('seatplan')) {
            Response::json(['message' => 'This action is unauthorized.'], 403);
        }

        $centerId = (int) $request->params['id'];
        $class = (int) $request->params['class'];
        $activeSession = $this->activeSession();

        // Center scoping for non-admin
        if (!Auth::isAdmin()) {
            $authCenterId = Auth::centerId();
            if ($authCenterId !== null && $authCenterId != $centerId) {
                Response::json(['message' => 'This action is unauthorized.'], 403);
            }
        }

        $students = Student::query()
            ->select('id', 'session', 'name', 'class', 'roll', 'center_id', 'institute_id')
            ->where('center_id', $centerId)
            ->where('session', $activeSession)
            ->where('class', $class)
            ->orderBy('roll', 'asc')
            ->get();

        return $this->renderSeatPlanPrint($students, $activeSession);
    }

    private function renderSeatPlan($students, $activeSession, $instituteId, $class): string
    {
        // Simple seat plan view - similar to what would be expected
        return $this->renderSeatPlanPrint($students, $activeSession);
    }

    private function renderSeatPlanPrint($students, $activeSession): string
    {
        $count = count($students);
        $i = 0;
        $str = "<style media='print'>
                .noprint
                  {
                   display:none;
                   margin-top:-25px;
                  }
                  
                  @media screen { #printOnly:{display:none;} }
                  @media print{ #printOnly:{display:block;} }
                  @media print {
                     body {-webkit-print-color-adjust: exact;font-family: Arial, Helvetica, sans-serif;}
                   }
                   @page {     margin: 12px !important; }
                   .logo img {
                     margin:5px;
                     border-radius: 50%;
                     width:35px;
                     height:35px;
                     }
                 .card3 {
                     text-align: center;
                     background-color: black;
                     color: white;
                     width: 36%;
                     height: 22px;
                     line-height: 0;
                     border-radius: 15px;
                     line-height: 18px;
                     margin:0 auto;
                     font-size:11px;
                     border-style: solid;
                     padding-top:2px;
                     padding-bottom:1px;
                   
                 }
                 .card2{
                     text-align: center;
                     font-size:12px;
                     margin-bottom:0px;
                     margin-top:-10px;
                     
                 }
                 .head{
                     text-align: center;
                     margin-bottom:0px;
                     font-weight: bold;
                     margin-top: 8px;
                     font-size: 13.5px;
                     
                 }
                 .head2{
                     text-align: center;
                     line-height: 1;
                     margin-top:0px;
                     font-size:12px;
                     margin-bottom: 0px;
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
                 table{
                     font-size: 10px;
                     font-weight:normal;
                 }
                 th {
                   padding-top: 2px;
                   padding-bottom: 2px;
                 }
                 h2, .card2, h3, h4, h5{
                 line-height:12px;
                 margin-top:4px;
                 }
               </style>";

        $className = $count > 0 ? $this->getClassName((int)($students[0]['class'] ?? 0)) : '';
        $centerName = $count > 0 ? ($students[0]['center_id'] ?? '') : '';

        $str .= '<div class="container" style="width:100%;margin-top:0px">
                    <div class="logo left">
                        <img src="/logo2.png">
                    </div>
                    <div style="margin-top:5px;" class="right">
                        <h2 class="head">BANGLADESH KINDERGARTEN ASSOCIATION</h2>
                        <h4 class="head2">Govt. Reg. No.: S-1028/98</h4>
                    </div>
                    <div class="clear"></div>
                </div>
                <div class="card3"><b>Seat Plan</b></div>
                <div class="card2">Scholarship Examination - ' . e($activeSession) . '</div>
                <div class="card2">Class: ' . e($className) . ' | Total: ' . $count . '</div>
                <div class="sub">
                    <table class="table table-bordered" style="width:100%;margin-top:5px;">
                        <thead>
                            <tr>
                                <th style="width:5%">SL</th>
                                <th style="width:10%">Roll</th>
                                <th style="width:40%">Name</th>
                                <th style="width:15%">Room</th>
                                <th style="width:15%">Seat</th>
                                <th style="width:15%">Signature</th>
                            </tr>
                        </thead>
                        <tbody>';
        foreach ($students as $student) {
            $i++;
            $str .= '<tr>
                <th style="text-align:center;font-weight:normal;">' . $i . '</th>
                <th style="text-align:center;font-weight:normal;">' . e($student['roll'] ?? '') . '</th>
                <th style="font-weight:normal;text-align:left;padding-left:4px;">' . e($student['name'] ?? '') . '</th>
                <th style="text-align:center;font-weight:normal;"></th>
                <th style="text-align:center;font-weight:normal;"></th>
                <th style="text-align:center;font-weight:normal;"></th>
            </tr>';
        }
        $str .= '</tbody></table></div>';
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
