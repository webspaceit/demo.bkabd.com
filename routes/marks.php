<?php

use App\Controllers\GradeAssignController;
use App\Controllers\MarkDistributionController;
use App\Controllers\MarkEntryController;
use App\Core\Router;

/**
 * Marks module: mark distribution CRUD + mark entry + grade assign.
 * Original routes: Route::apiResource('markdistributions') and the
 * /markentrystudent* endpoints from StudentController.
 *
 * NOTE: routes/marks.php loads before routes/reports.php (glob sorted), and
 * the first matching route wins — so registering
 * POST /api/markentrystudent/updateTotalNumbers HERE deliberately overrides
 * the reports-module stub that never persists. It returns both shapes the
 * front-ends read: `message` (original) and `data.students` (reports).
 *
 * Page paths mirror the original Vue router (resources/js/routes.js):
 *   /mark-distributions, /createMarkDistribution, /editMarkDistribution/{id},
 *   /markentry, /gradeassign
 */

// ---------------------------------------------------------------
// Mark distribution pages
// ---------------------------------------------------------------
Router::get('/mark-distributions', [MarkDistributionController::class, 'pageIndex'], 'auth|permission:mark-distributions');
Router::get('/createMarkDistribution', [MarkDistributionController::class, 'pageCreate'], 'auth|permission:mark-distribution-create');
Router::get('/editMarkDistribution/{id}', [MarkDistributionController::class, 'pageEdit'], 'auth|permission:mark-distribution-edit');

// ---------------------------------------------------------------
// Mark distribution API (original apiResource('markdistributions'))
// ---------------------------------------------------------------
Router::get('/api/markdistributions', [MarkDistributionController::class, 'index'], 'auth|permission:mark-distributions');
Router::post('/api/markdistributions', [MarkDistributionController::class, 'store'], 'auth|permission:mark-distribution-create');
Router::get('/api/markdistributions/{id}', [MarkDistributionController::class, 'show'], 'auth|permission:mark-distribution-edit');
Router::put('/api/markdistributions/{id}', [MarkDistributionController::class, 'update'], 'auth|permission:mark-distribution-edit');
// No `mark-distribution-delete` permission exists in the DB — gate delete with -edit.
Router::delete('/api/markdistributions/{id}', [MarkDistributionController::class, 'destroy'], 'auth|permission:mark-distribution-edit');

// ---------------------------------------------------------------
// Mark entry / grade assign pages
// ---------------------------------------------------------------
Router::get('/markentry', [MarkEntryController::class, 'pageIndex'], 'auth|permission:mark-entry');
Router::get('/gradeassign', [GradeAssignController::class, 'pageIndex'], 'auth|permission:grade-assign');

// ---------------------------------------------------------------
// Mark entry API (original StudentController endpoints)
// ---------------------------------------------------------------
Router::get('/api/markentrystudent/{id}/{class}', [MarkEntryController::class, 'students'], 'auth|permission:mark-entry');
Router::get('/api/markentrystudenthighestmark/{id}/{class}', [GradeAssignController::class, 'students'], 'auth|permission:grade-assign');
// Saves are allowed from either page — passed as an array because a pipe in
// the string form would be parsed as a separate middleware token.
Router::post('/api/markentrystudent/updateTotalNumbers', [MarkEntryController::class, 'submit'], ['auth', 'permission:mark-entry|grade-assign']);
