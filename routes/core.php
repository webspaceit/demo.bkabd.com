<?php

use App\Controllers\AuthController;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Models\ExamSession;

// ---------------------------------------------------------------
// Auth API
// ---------------------------------------------------------------
Router::post('/api/login', [AuthController::class, 'login'], 'guest');
Router::post('/api/logout', [AuthController::class, 'logout'], 'auth');
Router::post('/api/register', [AuthController::class, 'register'], 'auth');

/** Admin picks the exam session to work in (original: GET /api/updateSession/{id}). */
Router::get('/api/updateSession/{id}', function (Request $request) {
    if (!Auth::isAdmin()) {
        Response::json(['message' => 'This action is unauthorized.'], 403);
    }
    $id = (int) $request->params['id'];
    $row = ExamSession::find($id);
    if ($row === null) {
        Response::json(['status' => 'errors', 'message' => 'Session not found'], 404);
    }
    Session::put('admin_session', $row['session']);
    // Shown as a toast after the page reload by the dashboard flash bridge.
    Session::flash('success', 'Exam session changed to ' . $row['session'] . '.');
    \App\Core\Cache::forgetPrefix('latest_session');
    return ['status' => 'success', 'session' => $row['session']];
}, 'auth');

/** Session list used by header switcher. */
Router::get('/api/sessionList', function () {
    $list = ExamSession::query()->orderBy('id', 'desc')->get();
    return ['sessionList' => $list, 'activeSession' => Auth::activeSession()];
}, 'auth');

// ---------------------------------------------------------------
// Pages (SPA-style pages rendered server-side)
// ---------------------------------------------------------------
Router::get('/login', function () {
    if (Auth::check()) {
        Response::redirect('/');
    }
    return \App\Core\View::render('auth.login', [], 'layouts.guest');
}, 'guest');

Router::get('/', function () {
    return \App\Core\View::render('home', [
        'title' => 'Dashboard',
        'activeSession' => Auth::activeSession(),
    ], 'layouts.dashboard');
}, 'auth');

Router::get('/home', function () {
    Response::redirect('/');
});

/** Logout: POST form/JS only (GET no longer destroys the session — CSRF-safe). */
Router::post('/logout', function () {
    Auth::logout();
    Response::redirect('/login');
}, 'auth');

Router::get('/logout', function () {
    Response::redirect('/');
});
