<?php

use App\Controllers\PasswordController;
use App\Core\Router;

// ---------------------------------------------------------------
// Password change — ported from original routes/api.php
// (POST passchange, auth only, no permission gate).
// ---------------------------------------------------------------
Router::post('/api/passchange', [PasswordController::class, 'change'], 'auth');
Router::post('/api/account/profile', [PasswordController::class, 'updateProfile'], 'auth');

// ---------------------------------------------------------------
// Page
// ---------------------------------------------------------------
Router::get('/passchange', [PasswordController::class, 'page'], 'auth');
