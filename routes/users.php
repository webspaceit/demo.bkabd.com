<?php

use App\Controllers\UserController;
use App\Core\Router;
use App\Core\View;

/** User management routes — URL contracts identical to original routes/api.php. */

// List users (admin only)
// Original: GET /api/userList  ->  index (with pagination)
Router::get('/api/userList', [UserController::class, 'index'], 'auth|permission:user-list');

// Create user
// Original: POST /api/userCreate  ->  store
Router::post('/api/userCreate', [UserController::class, 'store'], 'auth|permission:user-create');
// URL-contract alias — the original frontend posted to /api/userSave.
Router::post('/api/userSave', [UserController::class, 'store'], 'auth|permission:user-create');

// Current user's name (original: GET /api/user -> {username})
Router::get('/api/user', [UserController::class, 'me'], 'auth');

// Role flags used by the original frontend menu
// Original: GET /api/isAdmin -> {status, isAdmin}
Router::get('/api/isAdmin', [UserController::class, 'isAdminEndpoint'], 'auth');
// Original: GET /api/isCenter -> {status, isAdmin} (isAdmin = !isAdmin, kept 1:1)
Router::get('/api/isCenter', [UserController::class, 'isCenterEndpoint'], 'auth');

// View user
// Original: GET /api/userById/{id}  ->  show
Router::get('/api/userById/{id}', [UserController::class, 'show'], 'auth');

// Update user
// Original: POST /api/userUpdate/{id}  ->  update
Router::post('/api/userUpdate/{id}', [UserController::class, 'update'], 'auth|permission:user-edit');

// Delete user
// Original: GET /api/userDelete/{id}  ->  delete (blueprint: make DELETE)
Router::delete('/api/userDelete/{id}', [UserController::class, 'destroy'], 'auth|permission:user-delete');

// All User Access page (behaviour in /assets/js/users.js)
// Original: GET /user -> user/index.vue
Router::get('/user', fn(): string => View::render('users.index', [
    'title'       => 'All User Access',
    'pageScripts' => ['/assets/js/users.js'],
], 'layouts.dashboard'), 'auth|permission:user-list');