<?php

use App\Controllers\RoleController;
use App\Core\Router;
use App\Core\View;

/** Role management routes — URL contracts identical to original routes/api.php. */

// List roles (with pagination)
// Original: GET /api/roleList  ->  index
Router::get('/api/roleList', [RoleController::class, 'index'], 'auth|permission:role-list');

// Create role
// Original: POST /api/roleCreate  ->  store
Router::post('/api/roleCreate', [RoleController::class, 'store'], 'auth|permission:role-create');
// URL-contract alias — the original frontend posted to /api/roleSave.
Router::post('/api/roleSave', [RoleController::class, 'store'], 'auth|permission:role-create');

// View role
// Original: GET /api/roleById/{id}  ->  show
Router::get('/api/roleById/{id}', [RoleController::class, 'show'], 'auth');

// Update role
// Original: POST /api/roleUpdate/{id}  ->  update
Router::post('/api/roleUpdate/{id}', [RoleController::class, 'update'], 'auth|permission:role-edit');

// Delete role
// Original: GET /api/roleDelete/{id}  ->  delete (blueprint: make DELETE)
Router::delete('/api/roleDelete/{id}', [RoleController::class, 'destroy'], 'auth|permission:role-delete');

// Role-wise permission list
// Original: GET /api/roleWisePermissionList  ->  {roleWisePermissionList:[...]}
Router::get('/api/roleWisePermissionList', [RoleController::class, 'roleWisePermissionList'], 'auth');

// Permission list (for role create/edit checkboxes)
// Original: GET /api/permissionList  ->  all permissions
Router::get('/api/permissionList', [RoleController::class, 'permissionList'], 'auth');

// Manage User (roles) page (behaviour in /assets/js/roles.js)
// Original: GET /userrole -> role/list.vue
Router::get('/userrole', fn(): string => View::render('roles.index', [
    'title'       => 'Manage User',
    'pageScripts' => ['/assets/js/roles.js'],
], 'layouts.dashboard'), 'auth|permission:role-list');

// Role create / edit pages (behaviour in /assets/js/roles.js)
Router::get('/create-role', [RoleController::class, 'pageCreate'], 'auth|permission:role-create');
Router::get('/edit-role/{id}', [RoleController::class, 'pageEdit'], 'auth|permission:role-edit');