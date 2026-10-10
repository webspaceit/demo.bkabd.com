<?php

use App\Controllers\AdmitCardController;
use App\Controllers\SeatPlanController;
use App\Core\Router;
use App\Core\View;

/** Exam documents module routes - Admit Cards and Seat Plan */

// Page routes (HTML)
Router::get('/admitcards', function () {
    return View::render('admitcards.index', [
        'title' => 'Admit Cards',
        'activeSession' => \App\Core\Auth::activeSession(),
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
// NOTE: /api/CenterWiseStudentDetailsReportIssuedAdmitCard/{id} is a *report*
// (ReportController) — registered once in routes/reports.php. An earlier
// duplicate here (AdmitCardController::printAdmitCardsByCenter) shadowed it
// and returned admit cards instead of the report sheet. Removed.

// Seat plan endpoints
Router::get('/api/seatplan/{id}/{class}', [SeatPlanController::class, 'fetchSeatPlan'], 'auth|permission:seatplan');
Router::get('/api/seatplan_print/{id}/{class}', [SeatPlanController::class, 'printSeatPlan'], 'auth|permission:seatplan');
