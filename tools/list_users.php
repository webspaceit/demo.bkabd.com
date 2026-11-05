<?php
require __DIR__ . '/../bootstrap/app.php';
$users = \App\Core\Database::select("SELECT id,email,type FROM users LIMIT 20");
foreach ($users as $u) { echo $u['id'] . " " . $u['email'] . " type=" . $u['type'] . "\n"; }
echo "count: " . count($users) . "\n";