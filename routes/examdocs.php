<?php

use App\Controllers\AdmitCardController;
use App\Controllers\SeatPlanController;
use App\Core\Request;
use App\Core\Router;
use App\Core\View;

/** Exam documents module routes - Admit Cards and Seat Plan */

// Page routes (HTML)
Router::get('/admitcards', function (Request $request) {
    return View::render('admitcards.index', [
        'title'        => 'Admit Cards',
        'sessions'     => \App\Models\ExamSession::query()->orderBy('id', 'desc')->get(),
        'classes'      => [1, 2, 3, 4, 5, 6],
        'centers'      => \App\Core\Database::select(
            'SELECT c.`id`, u.`name` FROM `centers` c
               JOIN `users` u ON u.`id` = c.`user_id`
              WHERE c.`active` = 1
              ORDER BY u.`name`'
        ),
        'institutes'   => (new \App\Core\Query('institutes'))->where('active', 1)->orderBy('name', 'asc')->get(),
        'activeSession' => \App\Core\Auth::activeSession(),
        'filters'      => [
            'session'      => $request->query('session'),
            'class'        => $request->query('class'),
            'center_id'    => $request->query('center_id'),
            'institute_id' => $request->query('institute_id'),
            'search'       => $request->query('search'),
        ],
    ], 'layouts.dashboard');
}, 'auth|permission:admit-card');

Router::get('/seatplan', function () {
    return View::render('seatplan.index', [
        'title' => 'Seat Plan',
        'activeSession' => \App\Core\Auth::activeSession(),
    ], 'layouts.dashboard');
}, 'auth|permission:seatplan');

// API endpoints - match original API routes for admit cards and seatplan
// Original: GET /admitcards/{id}/{class} - returns view/HTML data
Router::get('/api/admitcards/{id}/{class}', [AdmitCardController::class, 'fetchAdmitCards'], 'auth|permission:admit-card');
Router::get('/api/admitcard_print/{id}/{class}', [AdmitCardController::class, 'printAdmitCards'], 'auth|permission:admit-card');
Router::get('/api/admitcard_student/{id}', [AdmitCardController::class, 'printStudentAdmitCard'], 'auth|permission:admit-card');
Router::get('/api/admitcard_center/{id}', [AdmitCardController::class, 'printByCenter'], 'auth|permission:admit-card');
// NOTE: /api/CenterWiseStudentDetailsReportIssuedAdmitCard/{id} is a *report*
// (ReportController) — registered once in routes/reports.php. An earlier
// duplicate here (AdmitCardController::printAdmitCardsByCenter) shadowed it
// and returned admit cards instead of the report sheet. Removed.

// Seat plan endpoints
Router::get('/api/seatplan/{id}/{class}', [SeatPlanController::class, 'fetchSeatPlan'], 'auth|permission:seatplan');
Router::get('/api/seatplan_print/{id}/{class}', [SeatPlanController::class, 'printSeatPlan'], 'auth|permission:seatplan');
Router::get('/api/seatplan_cards/{id}/{class}', [SeatPlanController::class, 'printSeatArrangementCards'], 'auth|permission:seatplan');
Router::get('/api/seatplan/assignments', [SeatPlanController::class, 'assignments'], 'auth|permission:seatplan');
Router::post('/api/seatplan/assignments', [SeatPlanController::class, 'saveAssignments'], 'auth|permission:seatplan');
