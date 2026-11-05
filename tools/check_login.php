<?php
require __DIR__ . '/../bootstrap/app.php';
$u = \App\Core\Database::selectOne("SELECT id,email,password,type FROM users WHERE email='mehedi@gmail.com'");
var_dump($u);
$r = \App\Core\Auth::credentials('mehedi@gmail.com', '928c258731dcddadc5');
echo "verify: " . var_export($r !== null, true) . "\n";