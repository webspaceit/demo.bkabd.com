<?php

use App\Controllers\SmsController;
use App\Core\Router;

/**
 * SMS module (original routes/api.php: GET /api/getNumberCenter/{id},
 * GET /api/getNumberInstitute/{id}/{institute}, POST /api/smsSave).
 *
 * The two number-lookup endpoints were public in the original (registered
 * before the auth:sanctum group) — they expose phone numbers, so they are
 * gated behind auth + the `sms` permission here.
 */

// Page (original Vue router path: /sms)
Router::get('/sms', [SmsController::class, 'pageIndex'], 'auth|permission:sms');

// API
Router::get('/api/getNumberCenter/{id}', [SmsController::class, 'getNumberCenter'], 'auth|permission:sms');
Router::get('/api/getNumberInstitute/{id}/{institute}', [SmsController::class, 'getNumberInstitute'], 'auth|permission:sms');
Router::post('/api/smsSave', [SmsController::class, 'store'], 'auth|permission:sms');
