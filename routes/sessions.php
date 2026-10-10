<?php

use App\Controllers\SessionController;
use App\Core\Router;

/**
 * Exam session routes — URL contracts identical to the original
 * routes/api.php (Route::apiResource('sessions') + /recentSession).
 *
 * NOTE: routes/core.php already owns GET /api/sessionList (header switcher) and
 * GET /api/updateSession/{id} (admin override) — do not re-register them here.
 */

// Pages (original Vue router paths: /sessions, /createSession, /editSession/:sessionId)
Router::get('/sessions', [SessionController::class, 'pageIndex'], 'auth|permission:session-list');
Router::get('/createSession', [SessionController::class, 'pageCreate'], 'auth|permission:session-create');
Router::get('/editSession/{id}', [SessionController::class, 'pageEdit'], 'auth|permission:session-edit');

// API
Router::get('/api/sessions', [SessionController::class, 'index'], 'auth|permission:session-list');
Router::post('/api/sessions', [SessionController::class, 'store'], 'auth|permission:session-create');
Router::get('/api/sessions/{id}', [SessionController::class, 'show'], 'auth|permission:session-edit');
Router::put('/api/sessions/{id}', [SessionController::class, 'update'], 'auth|permission:session-edit');
Router::delete('/api/sessions/{id}', [SessionController::class, 'destroy'], 'auth|permission:session-delete');
Router::get('/api/recentSession', [SessionController::class, 'recentSessionApi'], 'auth');
