<?php
require __DIR__ . '/../bootstrap/app.php';
$r = \App\Core\Auth::credentials('admin@gmail.com', 'eed37d188c1414ae90');
echo "verify: " . var_export($r !== null, true) . "\n";
if ($r) { echo "name: " . $r['name'] . "\n"; }