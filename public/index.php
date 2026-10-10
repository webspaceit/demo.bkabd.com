<?php
/**
 * BKABD Exam System — front controller.
 * Every request (page or /api/*) enters here.
 */

// Allow running under PHP built-in server: serve real files directly.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($file !== __DIR__ . '/' && is_file($file)) {
        return false;
    }
}

require __DIR__ . '/../bootstrap/app.php';

App\Core\App::boot()->run();
