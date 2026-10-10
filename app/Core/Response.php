<?php

namespace App\Core;

/**
 * HTTP response helpers.
 */
final class Response
{
    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function html(string $html, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }

    public static function redirect(string $to, int $status = 302): never
    {
        http_response_code($status);
        header('Location: ' . $to);
        exit;
    }

    /** Validation failure — Laravel-compatible envelope. */
    public static function validationError(array $errors, string $message = 'The given data was invalid.'): never
    {
        self::json(['message' => $message, 'errors' => $errors], 422);
    }

    public static function download(string $body, string $filename, string $mime = 'application/octet-stream'): never
    {
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
        header('Content-Length: ' . strlen($body));
        echo $body;
        exit;
    }

    /** Inline (streamed in browser) file — PDF viewing. */
    public static function inline(string $body, string $filename, string $mime = 'application/pdf'): never
    {
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . str_replace('"', '', $filename) . '"');
        header('Content-Length: ' . strlen($body));
        header('Cache-Control: private, max-age=0, must-revalidate');
        echo $body;
        exit;
    }

    public static function noStore(): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
}
