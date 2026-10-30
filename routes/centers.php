<?php

use App\Controllers\CenterController;
use App\Core\Router;

// ---------------------------------------------------------------
// Centers API (original routes/api.php: Route::apiResource('centers') + changestatus)
//
// NOTE: the Router `permission:` middleware is unusable — App\Core\Auth::
// permissions() compares model_type as an inline SQL literal and MySQL strips
// the backslashes ('App\Models\User' -> 'AppModelsUser'), so it never matches
// (same core bug reported in routes/users.php). Permission gates therefore
// live in CenterController::requirePermission(), routes only carry `auth`.
// ---------------------------------------------------------------
Router::get('/api/centers', [CenterController::class, 'index'], 'auth');
Router::post('/api/centers', [CenterController::class, 'store'], 'auth');
Router::get('/api/centers/{id}', [CenterController::class, 'show'], 'auth');
Router::put('/api/centers/{id}', [CenterController::class, 'update'], 'auth');
// Multipart twin of PUT: PHP only parses form bodies on POST (BK forms send FormData).
Router::post('/api/centers/{id}', [CenterController::class, 'update'], 'auth');
Router::delete('/api/centers/{id}', [CenterController::class, 'destroy'], 'auth');

// Original was an UNAUTHENTICATED GET — now auth + permission + POST (hard rules 3/6).
Router::post('/api/centers/changestatus/{id}/{sta}', [CenterController::class, 'changestatus'], 'auth');

// Active-center dropdown for other modules (original: GET /api/center → index2).
Router::get('/api/center', [CenterController::class, 'index2'], 'auth');

// ---------------------------------------------------------------
// Pages
// ---------------------------------------------------------------
Router::get('/centers', [CenterController::class, 'pageIndex'], 'auth');
Router::get('/pending-centers', [CenterController::class, 'pagePending'], 'auth');
Router::get('/api/pending-centers/count', [CenterController::class, 'pendingCount'], 'auth');
Router::get('/create-center', [CenterController::class, 'pageCreate'], 'auth');
Router::get('/edit-center/{id}', [CenterController::class, 'pageEdit'], 'auth');
