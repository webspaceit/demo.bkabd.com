<?php
/** php -l every project PHP file (skips vendor/storage); prints only failures. */
$root = dirname(__DIR__);
$php  = PHP_BINARY;
$dirs = ['app', 'routes', 'bootstrap', 'public', 'tools', 'resources'];
$fail = 0;
$ok   = 0;

$iter = function (string $dir) use (&$iter) {
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') {
            continue;
        }
        $p = $dir . DIRECTORY_SEPARATOR . $f;
        if (is_dir($p)) {
            foreach ($iter($p) as $x) {
                yield $x;
            }
        } elseif (str_ends_with($f, '.php')) {
            yield $p;
        }
    }
};

foreach ($dirs as $d) {
    $base = $root . DIRECTORY_SEPARATOR . $d;
    if (!is_dir($base)) {
        continue;
    }
    foreach ($iter($base) as $file) {
        exec('"' . $php . '" -l "' . $file . '" 2>&1', $out, $code);
        if ($code !== 0) {
            $fail++;
            echo "FAIL: " . implode("\n     ", $out) . "\n";
        } else {
            $ok++;
        }
        $out = [];
    }
}

echo "$ok files OK, $fail failed\n";
exit($fail > 0 ? 1 : 0);
