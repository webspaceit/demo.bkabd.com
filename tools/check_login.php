<?php
$h = file_get_contents('http://127.0.0.1:8090/login');
$ok = str_contains($h, 'password') && (str_contains($h, 'Sign in') || str_contains($h, 'Login') || str_contains($h, 'login'));
echo ($ok ? 'LOGIN PAGE OK' : 'LOGIN PAGE MISSING MARKERS') . ' bytes=' . strlen($h) . "\n";
if (!$ok) { echo substr($h, 0, 600); }
