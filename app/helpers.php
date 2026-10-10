<?php

use App\Core\Auth;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;

// ---------------------------------------------------------------
// Display helpers (ported 1:1 from original app/Helpers/helpers.php)
// ---------------------------------------------------------------

if (!function_exists('DateFormat')) {
    /**
     * Format a date using a PHP date() format.
     *
     * The original used Carbon; Carbon is not vendored (no Composer on the
     * host), so this uses native DateTime with identical output for the
     * formats the app uses.
     */
    function DateFormat($date, $format)
    {
        if ($date === null || $date === '') {
            return '';
        }
        $ts = strtotime((string) $date);
        return $ts !== false ? date($format, $ts) : (string) $date;
    }
}

if (!function_exists('MembershipType')) {
    function MembershipType($data)
    {
        if ($data == 1) {
            return 'Temporary (1500 BDT)';
        } else if ($data == 2) {
            return 'Life Time (3000 BDT)';
        }
    }
}

function MemberType($data)
{
    if ($data == 1) {
        return 'New Member';
    } else if ($data == 2) {
        return 'Old Member';
    }
}

if (!function_exists('ClassName')) {
    function ClassName($data)
    {
        return match ((int) $data) {
            1 => 'ONE',
            2 => 'TWO',
            3 => 'THREE',
            4 => 'FOUR',
            5 => 'FIVE',
            6 => 'KG',
            default => '',
        };
    }
}

if (!function_exists('GenderName')) {
    function GenderName($data)
    {
        return match ((int) $data) {
            1 => 'Male',
            2 => 'Female',
            3 => 'Others',
            default => '',
        };
    }
}

if (!function_exists('MeritName')) {
    function MeritName($data)
    {
        return match ((int) $data) {
            1 => '1st',
            2 => '2nd',
            3 => '3rd',
            default => '',
        };
    }
}

if (!function_exists('GradeName')) {
    function GradeName($data)
    {
        return match ((int) $data) {
            1 => 'Talent Pool',
            2 => 'General Grade',
            3 => 'S.G',
            4 => 'AB',
            default => '',
        };
    }
}

if (!function_exists('ReligionName')) {
    function ReligionName($data)
    {
        return match ((int) $data) {
            1 => 'Muslim',
            2 => 'Hindu',
            3 => 'Buddhist',
            4 => 'Christian',
            default => '',
        };
    }
}

if (!function_exists('EducationType')) {
    function EducationType($data)
    {
        return match ((int) $data) {
            1 => 'Bangla Medium',
            2 => 'English Version',
            3 => 'Arabic Version',
            default => '',
        };
    }
}

function send_sms($number, $msg)
{
    $url = "https://sms.mram.com.bd/smsapi";
    $data = [
      "api_key" => "C300012663903e4fde6c31.18748747",
      "type" => "text",
      "contacts" => $number,
      "senderid" => "8809601002953",
      "msg" => $msg,
    ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
    curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

function send_sms_mask($number, $msg)
{
    $url = "https://sms.mram.com.bd/smsapi";
    $data = [
      "api_key" => "C300012663903e4fde6c31.18748747",
      "type" => "text",
      "contacts" => $number,
      "senderid" => "BKABD",
      "msg" => $msg,
    ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
    curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

// ---------------------------------------------------------------
// View helpers (available inside templates)
// ---------------------------------------------------------------

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        if ($value === null || is_bool($value)) {
            return '';
        }
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('js')) {
    function js(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        return '/' . ltrim($path, '/');
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Session::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(Session::token()) . '">';
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        // One-shot "old input" carried across the validation redirect — flash
        // storage (Session::flash('old', …)), NOT the plain session key.
        $old = Session::pullFlash('old', []);
        return is_array($old) && array_key_exists($key, $old) ? $old[$key] : $default;
    }
}

if (!function_exists('errors_for')) {
    function errors_for(string $field): array
    {
        $errors = Session::pullFlash('errors', []);
        return is_array($errors) && isset($errors[$field]) ? (array) $errors[$field] : [];
    }
}

if (!function_exists('has_errors')) {
    function has_errors(): bool
    {
        return (array) Session::pullFlash('errors', []) !== [];
    }
}

if (!function_exists('flash')) {
    function flash(string $key, mixed $default = null): mixed
    {
        return Session::pullFlash($key, $default);
    }
}

if (!function_exists('auth_user')) {
    function auth_user(): ?array
    {
        return Auth::user();
    }
}

if (!function_exists('can')) {
    function can(string $permission): bool
    {
        return Auth::can($permission);
    }
}

if (!function_exists('is_admin')) {
    function is_admin(): bool
    {
        return Auth::isAdmin();
    }
}

if (!function_exists('partial')) {
    function partial(string $view, array $data = []): string
    {
        $file = BASE_PATH . '/resources/views/' . str_replace('.', '/', $view) . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View not found: $file");
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
}

if (!function_exists('active_menu')) {
    function active_menu(string ...$paths): string
    {
        $current = Request::capture()->path();
        foreach ($paths as $p) {
            if ($p === $current || ($p !== '/' && str_starts_with($current, $p))) {
                return 'active';
            }
        }
        return '';
    }
}

if (!function_exists('money')) {
    function money($amount): string
    {
        return number_format((float) $amount, 2);
    }
}

// ---------------------------------------------------------------
// Form helper functions
// ---------------------------------------------------------------

if (!function_exists('form_open')) {
    function form_open(string $action = ''): string
    {
        $action = url($action);
        $token = csrf_token();
        return '<form method="POST" action="' . e($action) . '" accept-charset="utf-8" enctype="multipart/form-data">' . csrf_field();
    }
}

if (!function_exists('form_close')) {
    function form_close(): string
    {
        return '</form>';
    }
}