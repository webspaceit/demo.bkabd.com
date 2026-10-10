<?php
/**
 * Shared login for tools/test_*.php.
 *
 * The admin password is NOT committed. It is read from .env:
 *   TEST_ADMIN_EMAIL     (default: mehedi@gmail.com  — sample data)
 *   TEST_ADMIN_PASSWORD  (required; set it with one of)
 *       php tools/set_sample_login.php          (sample DB)
 *       php tools/set_password.php <email>      (any DB)
 *
 * .env is gitignored, so no credentials ship in the repository. If the
 * password is missing the tool fails fast with instructions instead of
 * silently trying a weak default.
 */
require_once __DIR__ . '/../bootstrap/app.php';

use App\Core\Config;

if (!function_exists('test_admin_email')) {
    function test_admin_email(): string
    {
        return (string) Config::get('TEST_ADMIN_EMAIL', 'mehedi@gmail.com');
    }

    function test_admin_password(): string
    {
        $p = (string) Config::get('TEST_ADMIN_PASSWORD', '');
        if ($p === '') {
            fwrite(STDERR, "TEST_ADMIN_PASSWORD is not set in .env.\n"
                . "Set it with: php tools/set_sample_login.php   (or  php tools/set_password.php <email>)\n");
            exit(2);
        }
        return $p;
    }

    /** JSON body for POST /api/login (or other credential endpoints). */
    function test_login_json(): string
    {
        return (string) json_encode(['email' => test_admin_email(), 'password' => test_admin_password()]);
    }

    /** Same credentials as an array (for form/other callers). */
    function test_login_array(): array
    {
        return ['email' => test_admin_email(), 'password' => test_admin_password()];
    }
}
