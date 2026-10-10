<?php
/**
 * Set a strong password on the sample-data admin accounts (the imported DB's
 * original hashes are unknown) and record it in .env as TEST_ADMIN_PASSWORD so
 * the tools/test_*.php suite can log in.
 *
 *   php tools/set_sample_login.php [password]
 *
 * With no password a strong random one is generated. .env is gitignored, so the
 * credential is never committed.
 */
require __DIR__ . '/../bootstrap/app.php';

use App\Core\Database;

$emails = ['admin@gmail.com', 'abusayem759@gmail.com', 'mehedi@gmail.com'];
$password = (string) ($argv[1] ?? '');
$generated = false;
if ($password === '') {
    $password = bin2hex(random_bytes(9)); // 18 hex chars
    $generated = true;
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$place = implode(',', array_fill(0, count($emails), '?'));
$stmt = Database::pdo()->prepare(
    "UPDATE `users` SET `password` = ?, `updated_at` = NOW() WHERE `email` IN ($place)"
);
$stmt->execute(array_merge([$hash], $emails));
echo 'accounts updated: ' . $stmt->rowCount() . "\n";

// Record the credential for the test tools in .env (gitignored).
$envPath = BASE_PATH . '/.env';
if (is_file($envPath) && is_writable($envPath)) {
    $env = (string) file_get_contents($envPath);
    if (preg_match('/^TEST_ADMIN_PASSWORD=.*$/m', $env)) {
        $env = (string) preg_replace('/^TEST_ADMIN_PASSWORD=.*$/m', 'TEST_ADMIN_PASSWORD=' . $password, $env);
    } elseif (preg_match('/^TEST_ADMIN_EMAIL=.*$/m', $env)) {
        $env .= "\nTEST_ADMIN_PASSWORD=" . $password . "\n";
    } else {
        $env = rtrim($env, "\n")
            . "\n\n# Local test login for tools/test_*.php (gitignored — never commit real credentials)\n"
            . "TEST_ADMIN_EMAIL=mehedi@gmail.com\n"
            . 'TEST_ADMIN_PASSWORD=' . $password . "\n";
    }
    file_put_contents($envPath, $env);
    echo "Wrote TEST_ADMIN_PASSWORD to .env\n";
} else {
    echo "Could not write .env — set TEST_ADMIN_PASSWORD manually.\n";
}

if ($generated) {
    echo "Login: mehedi@gmail.com / {$password}\n";
}
