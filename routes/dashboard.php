<?php

use App\Controllers\DashboardController;
use App\Core\Router;

// ---------------------------------------------------------------
// Dashboard stats (original: GET /api/dashboardcount)
// ---------------------------------------------------------------
Router::get('/api/dashboard', [DashboardController::class, 'dashboardcount'], 'auth');

// Original URL contract kept as an alias (same dashboardcount payload).
Router::get('/api/dashboardcount', [DashboardController::class, 'dashboardcount'], 'auth');