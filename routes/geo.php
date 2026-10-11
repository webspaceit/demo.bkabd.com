<?php

use App\Controllers\DistrictController;
use App\Controllers\DivisionController;
use App\Controllers\UnionController;
use App\Controllers\UpazilaController;
use App\Core\Router;
use App\Core\View;

// ---------------------------------------------------------------
// Geo API — ported from original routes/api.php.
// Read endpoints that were public/open in the original now require 'auth'
// (all logged-in pages cascade through them); destructive GETs converted to DELETE.
// ---------------------------------------------------------------

// Divisions
Router::get('/api/divisionList', [DivisionController::class, 'index'], 'auth');
Router::post('/api/divisionSave', [DivisionController::class, 'store'], 'auth|permission:division-create');
Router::get('/api/divisionById/{id}', [DivisionController::class, 'edit'], 'auth');
Router::post('/api/divisionUpdate/{id}', [DivisionController::class, 'update'], 'auth|permission:division-edit');
Router::delete('/api/divisionDelete/{id}', [DivisionController::class, 'destroy'], 'auth|permission:division-delete');

// Geo cascade lookups (original: DivisionController).
// districtById/upazilaById are public because the public institute
// self-registration form cascades through them (read-only geo data, same as
// the original). Everything else under /api/* requires auth.
Router::get('/api/districtById/{id}', [DivisionController::class, 'districtById']);
Router::get('/api/upazilaById/{id}', [DivisionController::class, 'upazilaById']);
Router::get('/api/unionById/{id}', [UnionController::class, 'unionById'], 'auth');

// Districts (apiResource + original custom update/edit routes)
Router::get('/api/districts', [DistrictController::class, 'index'], 'auth');
Router::post('/api/districts', [DistrictController::class, 'store'], 'auth|permission:district-create');
Router::get('/api/districts/{id}', [DistrictController::class, 'show'], 'auth');
Router::put('/api/districts/{id}', [DistrictController::class, 'update'], 'auth|permission:district-edit');
Router::delete('/api/districts/{id}', [DistrictController::class, 'destroy'], 'auth|permission:district-delete');
Router::post('/api/districtUpdate/{id}', [DistrictController::class, 'update'], 'auth|permission:district-edit');
Router::get('/api/districtById2/{id}', [DistrictController::class, 'edit'], 'auth');

// Upazilas (apiResource)
Router::get('/api/upazilas', [UpazilaController::class, 'index'], 'auth');
Router::post('/api/upazilas', [UpazilaController::class, 'store'], 'auth|permission:upazila-create');
Router::get('/api/upazilas/{id}', [UpazilaController::class, 'show'], 'auth');
Router::put('/api/upazilas/{id}', [UpazilaController::class, 'update'], 'auth|permission:upazila-edit');
Router::delete('/api/upazilas/{id}', [UpazilaController::class, 'destroy'], 'auth|permission:upazila-delete');

// Unions (apiResource + the cascade lookup above)
Router::get('/api/unions', [UnionController::class, 'index'], 'auth');
Router::post('/api/unions', [UnionController::class, 'store'], 'auth|permission:union-create');
Router::get('/api/unions/{id}', [UnionController::class, 'show'], 'auth');
Router::put('/api/unions/{id}', [UnionController::class, 'update'], 'auth|permission:union-edit');
Router::delete('/api/unions/{id}', [UnionController::class, 'destroy'], 'auth|permission:union-delete');

// ---------------------------------------------------------------
// Pages
// ---------------------------------------------------------------
Router::get('/divisions', fn(): string => View::render('geo.divisions', [
    'title' => 'Divisions',
    'pageScripts' => ['/assets/js/geo.js'],
], 'layouts.dashboard'), 'auth|permission:division-list');

Router::get('/districts', fn(): string => View::render('geo.districts', [
    'title' => 'Districts',
    'pageScripts' => ['/assets/js/geo.js'],
], 'layouts.dashboard'), 'auth|permission:district-list');

Router::get('/upazilas', fn(): string => View::render('geo.upazilas', [
    'title' => 'Upazilas',
    'pageScripts' => ['/assets/js/geo.js'],
], 'layouts.dashboard'), 'auth|permission:upazila-list');

Router::get('/unions', fn(): string => View::render('geo.unions', [
    'title' => 'Unions',
    'pageScripts' => ['/assets/js/geo.js'],
], 'layouts.dashboard'), 'auth|permission:union-list');
