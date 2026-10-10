<?php
// Print the last N lines of the app error log.
$n = (int) ($argv[1] ?? 30);
$f = dirname(__DIR__) . '/storage/logs/error.log';
if (!is_file($f)) { echo "(no error.log)\n"; exit; }
$lines = file($f, FILE_IGNORE_NEW_LINES);
foreach (array_slice($lines, -$n) as $l) { echo $l, "\n"; }
