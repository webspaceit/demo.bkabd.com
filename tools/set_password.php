<?php
/**
 * Set / rotate a user's password (use after importing data or if an account's
 * credentials may have leaked).
 *
 * Usage:
 *   php tools/set_password.php <email> [new-password] [--no-2fa]
 *
 * With no password argument a strong random password is generated and printed
 * once. Passwords are stored with password_hash(PASSWORD_DEFAULT); the plain
 * value is never written to the database, a log, or the repository.
 *
 * --no-2fa also clears the account's two-factor secret (useful when an account
 * is suspected compromised and the attacker may hold the authenticator).
 */
require __DIR__ . '/../bootstrap/app.php';

use App\Core\Database;

$args = array_slice($argv, 1);
$no2fa = in_array('--no-2fa', $args, true);
$args = array_values(array_filter($args, static fn ($a) => $a !== '--no-2fa'));
$email = (string) ($args[0] ?? '');
$password = (string) ($args[1] ?? '');

if ($email === '') {
    fwrite(STDERR, "usage: php tools/set_password.php <email> [new-password] [--no-2fa]\n");
    exit(1);
}

$user = Database::selectOne('SELECT `id`, `email` FROM `users` WHERE `email` = ? LIMIT 1', [$email]);
if ($user === null) {
    fwrite(STDERR, "No user with email: {$email}\n");
    exit(1);
}

$generated = false;
if ($password === '') {
    $password = bin2hex(random_bytes(9)); // 18 hex chars
    $generated = true;
}

$data = [
    'password'   => password_hash($password, PASSWORD_DEFAULT),
    'updated_at' => date('Y-m-d H:i:s'),
];
if ($no2fa) {
    $data['two_factor_secret'] = '';
    $data['two_factor_enabled'] = 0;
}

Database::update('users', $data, '`id` = :id', [':id' => (int) $user['id']]);

// Clear this host's login rate-limit counters so the operator isn't locked out
// right after a rotation.
foreach ((array) glob(BASE_PATH . '/storage/cache/ratelimit/*.json') as $f) {
    @unlink($f);
}

echo "Password updated for {$user['email']}.\n";
if ($generated) {
    echo "New password: {$password}\n";
}
if ($no2fa) {
    echo "Two-factor authentication cleared.\n";
}
