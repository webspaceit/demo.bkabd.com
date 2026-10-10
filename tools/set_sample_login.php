<?php
/**
 * Set a known password on sample-data admin accounts so the imported database
 * can be logged into (the original hashes are unknown).
 *
 *   admin@gmail.com          -> role 1  Admin (53 perms)
 *   abusayem759@gmail.com    -> role 7  Admin Pro (full access)
 *   mehedi@gmail.com         -> role 7  Admin Pro (full access)
 *
 * Password becomes: admin123
 */
require __DIR__ . '/../bootstrap/app.php';

$hash = password_hash('admin123', PASSWORD_BCRYPT);
$pdo  = App\Core\Database::pdo();
$stmt = $pdo->prepare(
    "UPDATE users SET password = ?, updated_at = NOW()
      WHERE email IN ('admin@gmail.com','abusayem759@gmail.com','mehedi@gmail.com')"
);
$stmt->execute([$hash]);
echo 'accounts updated: ' . $stmt->rowCount() . "\n";
