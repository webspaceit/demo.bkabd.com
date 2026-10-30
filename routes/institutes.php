<?php

use App\Controllers\InstituteController;
use App\Core\Router;

// ---------------------------------------------------------------
// Institutes API (original routes/api.php: apiResource 'institutes' + extras)
// ---------------------------------------------------------------
Router::get('/api/institutes', [InstituteController::class, 'index'], 'auth|permission:institute-list');
Router::post('/api/institutes', [InstituteController::class, 'store'], 'auth|permission:institute-create');
Router::get('/api/institutes/{id}', [InstituteController::class, 'show'], 'auth|permission:institute-list');
Router::put('/api/institutes/{id}', [InstituteController::class, 'update'], 'auth|permission:institute-edit');
// Multipart twin of PUT: PHP only parses form bodies on POST.
Router::post('/api/institutes/{id}', [InstituteController::class, 'update'], 'auth|permission:institute-edit');
Router::delete('/api/institutes/{id}', [InstituteController::class, 'destroy'], 'auth|permission:institute-delete');
// Original: list of a center's active institutes (NOT a single institute).
Router::get('/api/instituteById/{id}', [InstituteController::class, 'instituteById'], 'auth|permission:institute-list');
Router::post('/api/institutes/changestatus/{id}/{sta}', [InstituteController::class, 'changestatus'], 'auth|permission:institute-edit');
Router::post('/api/institutes/{id}/assign-certificate-number', [InstituteController::class, 'assignCertificateNumber'], 'auth|permission:institute-edit');
Router::put('/api/institutevalidity/{id}', [InstituteController::class, 'updateValidity'], 'auth|permission:institute-validity');
// Total institutes (original: GET /api/totalInstitute)
Router::get('/api/totalInstitute', [InstituteController::class, 'totalInstitute'], 'auth');

// ---------------------------------------------------------------
// Public membership self-registration (original web.php + routes/api.php)
// GET  /membership-institute-registration  -> public form
// POST /api/ins-store                      -> store2 (duplicate -> 401)
// GET  /api/institute-registration/{id}    -> printable registration card
// ---------------------------------------------------------------
Router::get('/membership-institute-registration', [InstituteController::class, 'pagePublicCreate']);
Router::post('/api/ins-store', [InstituteController::class, 'store2']);
Router::get('/api/institute-registration/{id}', [InstituteController::class, 'instituteregistrationpdf']);

// ---------------------------------------------------------------
// Pages
// ---------------------------------------------------------------
Router::get('/institutes', [InstituteController::class, 'pageIndex'], 'auth|permission:institute-list');
Router::get('/institute-registration', [InstituteController::class, 'pageCreate'], 'auth|permission:institute-create');
Router::get('/edit-institute/{id}', [InstituteController::class, 'pageEdit'], 'auth|permission:institute-edit');
Router::get('/validityinstitute/{id}', [InstituteController::class, 'pageValidity'], 'auth|permission:institute-validity');
