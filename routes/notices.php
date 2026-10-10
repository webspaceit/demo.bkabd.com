<?php

use App\Controllers\NoticeController;
use App\Core\Router;

/**
 * Notice board routes — URL contracts identical to the original
 * routes/api.php (Route::apiResource('notices') + /noticeById).
 */

// Pages (original Vue router paths: /notices, /create-notice, /editnotice/:noticeId)
Router::get('/notices', [NoticeController::class, 'pageIndex'], 'auth|permission:notice-list');
Router::get('/create-notice', [NoticeController::class, 'pageCreate'], 'auth|permission:notice-create');
Router::get('/editnotice/{id}', [NoticeController::class, 'pageEdit'], 'auth|permission:notice-edit');

// API
Router::get('/api/notices', [NoticeController::class, 'index'], 'auth|permission:notice-list');
Router::post('/api/notices', [NoticeController::class, 'store'], 'auth|permission:notice-create');
Router::get('/api/notices/{id}', [NoticeController::class, 'show'], 'auth|permission:notice-edit');
Router::put('/api/notices/{id}', [NoticeController::class, 'update'], 'auth|permission:notice-edit');
Router::delete('/api/notices/{id}', [NoticeController::class, 'destroy'], 'auth|permission:notice-delete');
Router::get('/api/noticeById', [NoticeController::class, 'noticeById'], 'auth');
