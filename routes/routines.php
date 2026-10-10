<?php

use App\Controllers\RoutineController;
use App\Core\Router;

/**
 * Routine routes — URL contracts identical to the original
 * routes/api.php (Route::apiResource('routines')).
 */

// Pages (original Vue router paths: /routines, /createRoutine, /editRoutine/:routineId)
Router::get('/routines', [RoutineController::class, 'pageIndex'], 'auth|permission:routine-list');
Router::get('/createRoutine', [RoutineController::class, 'pageCreate'], 'auth|permission:routine-create');
Router::get('/editRoutine/{id}', [RoutineController::class, 'pageEdit'], 'auth|permission:routine-edit');

// API
Router::get('/api/routines', [RoutineController::class, 'index'], 'auth|permission:routine-list');
Router::post('/api/routines', [RoutineController::class, 'store'], 'auth|permission:routine-create');
Router::get('/api/routines/{id}', [RoutineController::class, 'show'], 'auth|permission:routine-edit');
Router::put('/api/routines/{id}', [RoutineController::class, 'update'], 'auth|permission:routine-edit');
Router::delete('/api/routines/{id}', [RoutineController::class, 'destroy'], 'auth|permission:routine-delete');
