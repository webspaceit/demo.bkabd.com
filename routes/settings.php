<?php

use App\Controllers\SettingController;
use App\Controllers\BrandingController;
use App\Core\Router;

/**
 * Settings / instructions / roll-place module (original routes/api.php:
 * Route::apiResource('settings') + GET /api/rollplace/{id}/{sta}).
 *
 * Page paths mirror the original Vue router (resources/js/routes.js):
 *   /settings, /instructions, /role-place
 *
 * The original rollplace endpoint was a GET that writes data — converted to
 * POST here (blueprint hard rule 3). The Vue page is ported accordingly.
 */

// Pages
Router::get('/settings', [SettingController::class, 'pageIndex'], 'auth|permission:settings');
Router::get('/settings/branding', [BrandingController::class, 'page'], 'auth|permission:settings');
Router::get('/instructions', [SettingController::class, 'pageInstructions'], 'auth');
Router::get('/role-place', [SettingController::class, 'pageRolePlace'], 'auth|permission:role-place');

// API (original apiResource('settings') — full resource now ported)
Router::get('/api/settings', [SettingController::class, 'index'], 'auth|permission:settings');
Router::post('/api/settings', [SettingController::class, 'store'], 'auth|permission:setting-edit');
Router::get('/api/settings/{id}', [SettingController::class, 'show'], 'auth');
Router::put('/api/settings/{id}', [SettingController::class, 'update'], 'auth|permission:setting-edit');
Router::delete('/api/settings/{id}', [SettingController::class, 'destroy'], 'auth|permission:setting-edit');
// Result-sheet signature uploads (multipart).
Router::post('/api/settings/signatures', [SettingController::class, 'uploadSignatures'], 'auth|permission:setting-edit');
Router::get('/api/branding', [BrandingController::class, 'show'], 'auth|permission:settings');
Router::put('/api/branding', [BrandingController::class, 'update'], 'auth|permission:setting-edit');
Router::post('/api/branding/assets', [BrandingController::class, 'upload'], 'auth|permission:setting-edit');

// Roll/seat placement (state-changing → POST, original was GET)
Router::post('/api/rollplace/{id}/{sta}', [SettingController::class, 'rollplace'], 'auth|permission:role-place');
