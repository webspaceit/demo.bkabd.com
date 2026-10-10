<?php

use App\Controllers\ReportController;
use App\Core\Router;

/** Report module routes — URL contracts identical to original routes/api.php. */

// Student list report
// Original: GET /api/report-student-list  ->  student list with filters
Router::get('/api/report-student-list', [ReportController::class, 'studentList'], 'auth');

// Full marks
// Original: GET /api/fullmarks  ->  full marks config
Router::get('/api/fullmarks', [ReportController::class, 'fullmarks'], 'auth');

// Center list for reports
// Original: GET /api/centerList  ->  active centers
Router::get('/api/centerList', [ReportController::class, 'centersForReports'], 'auth');

// Institute list by center
// Original: GET /api/instituteListByCenter  ->  institutes filtered by center_id
Router::get('/api/instituteListByCenter', [ReportController::class, 'institutesByCenter'], 'auth');

// Report hub page (sidebar: Reports)
Router::get('/reports', [ReportController::class, 'pageHub'], 'auth|permission:report-list');

// ---------------------------------------------------------------
// Report sheets / exports — URL contracts identical to the original
// routes/api.php (Laravel applies the /api prefix).
// ---------------------------------------------------------------

// Excel exports
Router::get('/api/institutestudentdetailsexport/{id}', [ReportController::class, 'institutestudentdetailsexport'], 'auth');
Router::get('/api/centerstudentdetailsexport/{id}', [ReportController::class, 'centerstudentdetailsexport'], 'auth');
Router::get('/api/centerstudentdetailsexportwithoutimage/{id}', [ReportController::class, 'centerstudentdetailsexportwithoutimage'], 'auth');

// Center info
Router::get('/api/centerinfo/{id}', [ReportController::class, 'centerinfo'], 'auth');

// Student detail sheets
Router::get('/api/centerwisedetails/{id}', [ReportController::class, 'centerwisedetails'], 'auth');
Router::get('/api/EducationalTypeStudentCountReport/{id}', [ReportController::class, 'EducationalTypeStudentCountReport'], 'auth');
Router::get('/api/centerwisedetailswithoutimage/{id}', [ReportController::class, 'centerwisedetailswithoutimage'], 'auth');
Router::get('/api/institutewisedetails/{id}', [ReportController::class, 'institutewisedetails'], 'auth');

// Attendance sheets
Router::get('/api/ClassWiseExamAttendSheetReport/{id}', [ReportController::class, 'ClassWiseExamAttendSheetReport'], 'auth');
Router::get('/api/ClassWiseExamAttendSheetReportWithPhoto/{id}', [ReportController::class, 'ClassWiseExamAttendSheetReportWithPhoto'], 'auth');
Router::get('/api/ClassWiseExamAttendSheetReport2/{id}', [ReportController::class, 'ClassWiseExamAttendSheetReport2'], 'auth');
Router::get('/api/ClassWiseExamAttendSheetReportWithPhoto2/{id}', [ReportController::class, 'ClassWiseExamAttendSheetReportWithPhoto2'], 'auth');

// Institute membership / information
Router::get('/api/TotalInstituteInformation/{id}', [ReportController::class, 'TotalInstituteInformation'], 'auth');
Router::get('/api/AllCenterDetailsInformationReport/{id}', [ReportController::class, 'AllCenterDetailsInformationReport'], 'auth');
Router::get('/api/LifeTimeMembershipInstituteReport/{id}', [ReportController::class, 'LifeTimeMembershipInstituteReport'], 'auth');
Router::get('/api/TemporaryMembershipInstituteReport/{id}', [ReportController::class, 'TemporaryMembershipInstituteReport'], 'auth');

// Aggregate reports
Router::get('/api/DocumentsDistributionReport/{id}', [ReportController::class, 'DocumentsDistributionReport'], 'auth');
Router::get('/api/CenterWiseStudentDetailsReportIssuedAdmitCard/{id}', [ReportController::class, 'CenterWiseStudentDetailsReportIssuedAdmitCard'], 'auth');
Router::get('/api/CenterWiseHighestMarksReport/{id}', [ReportController::class, 'CenterWiseHighestMarksReport'], 'auth');
Router::get('/api/InstituteWiseHighestMarksReport/{id}/{inst_id}', [ReportController::class, 'InstituteWiseHighestMarksReport'], 'auth');
Router::get('/api/CenterWiseTabulationSheet/{id}', [ReportController::class, 'CenterWiseTabulationSheet'], 'auth');
Router::get('/api/CenterWiseGradeSheetCountReport/{id}', [ReportController::class, 'CenterWiseGradeSheetCountReport'], 'auth');
Router::get('/api/CenterWiseGradeListMeritPositionCountReport/{id}', [ReportController::class, 'CenterWiseGradeListMeritPositionCountReport'], 'auth');
Router::get('/api/CenterWiseGradeSheetResultDetails/{id}', [ReportController::class, 'CenterWiseGradeSheetResultDetails'], 'auth');
Router::get('/api/InstituteWiseGradeSheetResultDetails/{id}/{inst_id}', [ReportController::class, 'InstituteWiseGradeSheetResultDetails'], 'auth');
Router::get('/api/MeritListResultwithPosition/{id}', [ReportController::class, 'MeritListResultwithPosition'], 'auth');
