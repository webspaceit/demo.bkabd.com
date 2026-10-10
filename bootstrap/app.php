<?php
/**
 * Application bootstrap: config, autoload, timezone, error handling, session.
 */

define('BASE_PATH', dirname(__DIR__));

// Composer autoloader (PSR-4 App\ + dompdf + phpspreadsheet).
require BASE_PATH . '/vendor/autoload.php';

// ---------------------------------------------------------------
// .env loader (no framework — plain parser, read-only at runtime)
// ---------------------------------------------------------------
App\Core\Config::load(BASE_PATH . '/.env');
date_default_timezone_set(App\Core\Config::get('APP_TIMEZONE', 'Asia/Dhaka'));
mb_internal_encoding('UTF-8');

// ---------------------------------------------------------------
// Error handling
// ---------------------------------------------------------------
$debug = App\Core\Config::getBool('APP_DEBUG', false);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/storage/logs/error.log');
error_reporting(E_ALL);

if ($debug) {
    ini_set('display_startup_errors', '1');
} else {
    set_exception_handler(function (Throwable $e) {
        error_log('[uncaught] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        http_response_code(500);
        if (App\Core\Request::isJsonPreferred()) {
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Server error', 'errors' => []]);
        } else {
            echo '<h1>Something went wrong</h1><p>Please try again later.</p>';
        }
        exit;
    });
}

// ---------------------------------------------------------------
// Session (started lazily by Session::start() — called by App::run)
// ---------------------------------------------------------------
