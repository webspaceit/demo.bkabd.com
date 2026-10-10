<?php
/**
 * Report duplicate route registrations across routes/*.php.
 *
 * Duplicates are keyed on METHOD + PATH with every `{param}` placeholder
 * normalised to `{}` — otherwise `/api/x/{id}` and `/api/x/{center_id}` would
 * look distinct while matching the exact same URL (that false negative hid a
 * shadowed report route).
 */
$all = [];
foreach (glob(__DIR__ . '/../routes/*.php') as $f) {
    foreach (file($f) as $i => $l) {
        if (preg_match('/Router::(get|post|put|patch|delete)\(\s*[\'"]([^\'"]+)/', $l, $m)) {
            $path = preg_replace('/\{[^}]+\}/', '{}', $m[2]);
            $k = strtolower($m[1]) . ' ' . $path;
            $all[$k][] = basename($f) . ':' . ($i + 1) . ' (' . $m[2] . ')';
        }
    }
}
$dupes = 0;
foreach ($all as $k => $v) {
    if (count($v) > 1) {
        $dupes++;
        echo "DUPLICATE $k => " . implode(', ', $v) . "\n";
    }
}
echo $dupes === 0 ? "no duplicates (" . count($all) . " routes)\n" : "$dupes duplicate(s)\n";
