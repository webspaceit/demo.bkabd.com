<?php
/**
 * Application bootstrap: config, autoload, timezone, error handling, session.
 */

define('BASE_PATH', dirname(__DIR__));

// ---------------------------------------------------------------
// App\ PSR-4 autoloader.
// Composer also maps App\ -> app/, but on hosts whose optimised (classmap)
// autoloader predates a freshly pulled file — and is authoritative — a newly
// added class such as App\Core\RateLimiter would not resolve. This loader
// guarantees app/ classes on every host with no `composer dump-autoload` step;
// Composer still handles the vendored packages (dompdf, phpspreadsheet) and
// the app/helpers.php "files" entry.
// ---------------------------------------------------------------
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// Composer autoloader (vendored packages + helpers.php).
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
        error_log('[uncaught] ' . App\Core\Database::redact($e->getMessage()) . ' @ ' . $e->getFile() . ':' . $e->getLine());
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
