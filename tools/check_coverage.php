<?php
/**
 * Compare the original Laravel route surface (E:\wamp64\www\exam\routes) with
 * this clone's routes/*.php and report original API paths that are not
 * registered here.
 *
 * Path-only comparison: method changes are intentional (blueprint hard rule 3
 * turns GET deletes/state changes into DELETE/POST), placeholders are
 * normalised so `{id}` == `{center_id}`.
 *
 * Usage: php tools/check_coverage.php [path/to/original/routes]
 *        (default: ../exam/routes relative to the workspace parent)
 */

$origDir = $argv[1] ?? dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'exam' . DIRECTORY_SEPARATOR . 'routes';
$origApi = $origDir . DIRECTORY_SEPARATOR . 'api.php';

if (!is_file($origApi)) {
    fwrite(STDERR, "original routes not found: $origApi\n");
    exit(2);
}

$norm = static function (string $path): string {
    $path = '/' . ltrim($path, '/');
    $path = preg_replace('/\{[^}]+\}/', '{}', $path);
    $path = rtrim($path, '/');
    return $path === '' ? '/' : $path;
};

// ---- Original /api/* paths ---------------------------------------
$orig = [];
foreach (file($origApi) as $line) {
    if (!preg_match('/Route::(get|post|put|patch|delete|apiResource|resource)\(\s*[\'"]([^\'"]+)/', $line, $m)) {
        continue;
    }
    // Skip commented-out lines.
    if (preg_match('/^\s*\/\//', $line)) {
        continue;
    }
    $verb = strtolower($m[1]);
    $path = $m[2];
    if ($verb === 'apiresource' || $verb === 'resource') {
        $base = '/api/' . trim($path, '/');
        $orig[$norm($base)] = true;
        $orig[$norm($base . '/{id}')] = true;
        continue;
    }
    $orig[$norm('/api/' . trim($path, '/'))] = true;
}

// ---- Clone paths -------------------------------------------------
$clone = [];
foreach (glob(dirname(__DIR__) . '/routes/*.php') as $f) {
    foreach (file($f) as $line) {
        if (preg_match('/Router::(get|post|put|patch|delete)\(\s*[\'"]([^\'"]+)/', $line, $m)) {
            $clone[$norm($m[2])] = true;
        }
    }
}

// Paths that only exist as inline-handler route definitions inside another file.
//
// Known-broken original routes: `/api/center-certificate/{id}` points at
// ReportController::center_certificate(), which does not exist in the original
// codebase (calling it fatals) and is never referenced by the frontend. The
// clone deliberately does not reproduce it (CONVENTIONS.md hard rule 6).
$knownBroken = ['/api/center-certificate/{}'];

$missing = array_values(array_diff(array_keys(array_diff_key($orig, $clone)), $knownBroken));
sort($missing);

echo 'original /api paths: ' . count($orig) . "\n";
echo 'clone routes:        ' . count($clone) . "\n";
if ($missing === []) {
    if ($knownBroken !== []) {
        echo 'skipped broken originals: ' . implode(', ', $knownBroken) . "\n";
    }
    echo "full API path coverage\n";
    exit(0);
}

echo "missing paths (" . count($missing) . "):\n";
foreach ($missing as $p) {
    echo "  $p\n";
}
exit(1);
