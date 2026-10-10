<?php

namespace App\Core;

/**
 * PHP template renderer with layouts and partials.
 *
 *   echo View::render('students.index', $data, 'layouts.dashboard');
 *
 * View file: resources/views/students/index.php
 * Layout:    resources/views/layouts/dashboard.php  (receives $content)
 * Partial:   View::partial('shared.pagination', $data)
 *
 * Helpers available inside templates: e(), url(), csrf_field(), old(),
 * errors_for(), auth_user(), can(), partial().
 */
final class View
{
    /** Render a view, optionally wrapped in a layout. */
    public static function render(string $view, array $data = [], ?string $layout = null): string
    {
        $content = self::partial($view, $data);
        if ($layout === null) {
            return $content;
        }
        return self::partial($layout, $data + ['content' => $content]);
    }

    /** Include a template file with extracted data. */
    public static function partial(string $view, array $data = []): string
    {
        $file = BASE_PATH . '/resources/views/' . str_replace('.', '/', $view) . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View not found: $view ($file)");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    /** Escape for HTML context. */
    public static function e(mixed $value): string
    {
        if ($value === null || is_bool($value)) {
            return '';
        }
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** JSON-encode safe for embedding in HTML attributes / inline JS. */
    public static function js(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
}
