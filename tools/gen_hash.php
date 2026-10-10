<?php
/**
 * Print a bcrypt hash for a password (for manual seed edits).
 *
 * Usage: php tools/gen_hash.php <password>
 */
require __DIR__ . '/../bootstrap/app.php';

$pw = (string) ($argv[1] ?? '');
if ($pw === '') {
    fwrite(STDERR, "usage: php tools/gen_hash.php <password>\n");
    exit(1);
}
echo password_hash($pw, PASSWORD_DEFAULT) . "\n";
