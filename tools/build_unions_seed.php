<?php
/**
 * Build database/unions_seed.csv from a Bangladesh "all unions" CSV.
 *
 * The source file (e.g. Bangladesh_All_Unions.csv) has columns
 *   ... Upazila_EN, Union_EN, Union_BN, Union_URL, Union_ID, Upazila_ID
 * where Upazila_ID matches the `upozilas`.`id` in this app.
 *
 * The importer (tools/import_unions.php) wants a simple `name,upozila_id`
 * file, so this normalises it:
 *   - name       <- Union_EN (the app's other geo tables are in English)
 *   - upozila_id <- Upazila_ID
 * Where two rows share the same (upozila_id, Union_EN):
 *   - if their Bengali names are identical they are true duplicates -> keep one
 *   - otherwise they are distinct unions -> qualify with the Bengali
 *     parenthetical, e.g. "Natai (দক্ষিন)" / "Natai (উত্তর)"
 *
 * Usage:
 *   php tools/build_unions_seed.php --in <source.csv> [--out database/unions_seed.csv]
 */
require __DIR__ . '/../bootstrap/app.php';

$args = $argv;
$in = arg($args, '--in');
$out = arg($args, '--out', BASE_PATH . '/database/unions_seed.csv');
if ($in === '' || !is_file($in)) {
    fwrite(STDERR, "usage: php tools/build_unions_seed.php --in <source.csv> [--out <out.csv>]\n");
    exit(1);
}

$fh = fopen($in, 'r');
$header = fgetcsv($fh, 0, ',', '"', '\\');
$idx = array_flip(array_map(static fn ($h) => trim((string) $h), $header));
foreach (['Upazila_ID', 'Union_EN'] as $need) {
    if (!isset($idx[$need])) { fwrite(STDERR, "Missing column: {$need}\n"); exit(1); }
}

/** @var array<string, list<array{en:string,bn:string}>> $groups */
$groups = [];
$order = [];
$rows = 0;
while (($r = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
    if ($r === [null] || count($r) < 2) { continue; }
    $get = static fn (string $k) => trim((string) ($r[$idx[$k]] ?? ''));
    $up = $get('Upazila_ID');
    $en = $get('Union_EN');
    $bn = $get('Union_BN');
    if ($up === '' || $en === '') { continue; }
    $key = $up . "\x1f" . $en;
    if (!isset($groups[$key])) { $groups[$key] = []; $order[] = $key; }
    $groups[$key][] = ['en' => $en, 'bn' => $bn, 'up' => $up];
    $rows++;
}
fclose($fh);

$outRows = [];
$dropped = 0;
$renamed = 0;
foreach ($order as $key) {
    $items = $groups[$key];
    if (count($items) === 1) {
        $outRows[] = [$items[0]['en'], $items[0]['up']];
        continue;
    }
    $distinctBn = array_values(array_unique(array_map(static fn ($x) => $x['bn'], $items)));
    if (count($distinctBn) === 1) {
        // True duplicate: keep the first.
        $outRows[] = [$items[0]['en'], $items[0]['up']];
        $dropped += count($items) - 1;
        continue;
    }
    // Distinct unions with the same English name: qualify each.
    foreach ($items as $it) {
        $suffix = bn_qualifier($it['bn']);
        $name = $it['en'];
        if ($suffix !== '' && strcasecmp($suffix, $it['en']) !== 0) {
            $name = $it['en'] . ' (' . $suffix . ')';
        }
        $outRows[] = [$name, $it['up']];
        $renamed++;
    }
}

$fh = fopen($out, 'w');
fputcsv($fh, ['name', 'upozila_id'], ',', '"', '\\');
foreach ($outRows as $row) { fputcsv($fh, $row, ',', '"', '\\'); }
fclose($fh);

echo "source rows:        {$rows}\n";
echo "output unions:      " . count($outRows) . "\n";
echo "duplicates dropped: {$dropped}\n";
echo "qualified names:    {$renamed}\n";
echo "written:            {$out}\n";

function arg(array $argv, string $flag, string $default = ''): string
{
    foreach ($argv as $i => $a) {
        if ($a === $flag && isset($argv[$i + 1])) { return trim($argv[$i + 1], "\"'"); }
    }
    return $default;
}

/** Inner text of a Bengali "name (qualifier)" pattern, else the whole string. */
function bn_qualifier(string $bn): string
{
    if (preg_match('/[（(]\s*([^)）]+?)\s*[)）]/u', $bn, $m)) {
        return trim($m[1]);
    }
    return trim($bn);
}
