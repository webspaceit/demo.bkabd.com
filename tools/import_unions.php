<?php
/**
 * Import union data into the `unions` table (enables the Union cascade on the
 * student registration / edit forms).
 *
 * Usage:
 *   php tools/import_unions.php <file> [--dry-run] [--truncate] [--update] [--relax-unique]
 *
 * Accepted input
 *   .sql   Raw SQL (e.g. a dump from the original app). Statements are run as-is.
 *   .csv   Comma / tab / semicolon separated; a header row is optional and is
 *          matched case-insensitively. Recognised columns:
 *            name | union | union_name           (required)
 *            upozila_id | upazila_id             numeric upazila FK
 *            upazila | upozila | upazila_name    upazila NAME (resolved to its id)
 *            district | district_name            optional, disambiguates an upazila name
 *            user_id                             optional (default 1)
 *          Either an upozila id or an upazila name must be present.
 *
 * Flags
 *   --dry-run        validate + resolve, print a summary, change nothing
 *   --truncate       DELETE FROM unions before inserting
 *   --update         if the (upozila_id, name) pair already exists, keep it
 *                    (default: it is skipped)
 *   --relax-unique   apply the unions migration (drop legacy name-only UNIQUE,
 *                    switch id to AUTO_INCREMENT, add UNIQUE(upozila_id,name)).
 *                    Idempotent; also available in database/upgrade_notes.sql.
 *
 * The script never arms destructive behaviour implicitly: without --truncate it
 * only inserts, and without --update an existing row is left untouched.
 */
require __DIR__ . '/../bootstrap/app.php';

use App\Core\Database;

$args = array_slice($argv, 1);
$flags = array_values(array_filter($args, static fn ($a) => str_starts_with($a, '--')));
$file = '';
foreach ($args as $a) {
    if (!str_starts_with($a, '--')) { $file = $a; break; }
}
$dryRun = in_array('--dry-run', $flags, true);
$truncate = in_array('--truncate', $flags, true);
$update = in_array('--update', $flags, true);
$relax = in_array('--relax-unique', $flags, true);

if ($relax) {
    apply_union_migration();
}

if ($file === '') {
    fwrite(STDERR, "usage: php tools/import_unions.php <file.csv|file.sql> [--dry-run] [--truncate] [--update] [--relax-unique]\n");
    exit(1);
}
if (!is_file($file) || !is_readable($file)) {
    fwrite(STDERR, "Cannot read file: {$file}\n");
    exit(1);
}

$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$rows = $ext === 'sql' ? [] : parse_csv($file);

if ($truncate && !$dryRun) {
    $before = (int) Database::scalar('SELECT COUNT(*) FROM `unions`');
    Database::statement('DELETE FROM `unions`');
    echo "Truncated unions (removed {$before} rows).\n";
}

$inserted = 0; $skipped = 0; $unresolved = 0; $errors = 0;

if ($ext === 'sql') {
    $sql = (string) file_get_contents($file);
    $stmts = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql)));
    foreach ($stmts as $stmt) {
        if ($stmt === '' || str_starts_with($stmt, '--')) { continue; }
        if ($dryRun) { echo "[dry-run] would run: " . substr(preg_replace('/\s+/', ' ', $stmt), 0, 100) . "...\n"; $inserted++; continue; }
        try { Database::pdo()->exec($stmt); $inserted++; }
        catch (Throwable $e) { $errors++; fwrite(STDERR, "SQL error: " . $e->getMessage() . "\n"); }
    }
    echo "\nSQL: executed {$inserted}, errors {$errors}" . ($dryRun ? ' (dry-run)' : '') . "\n";
    exit($errors > 0 ? 1 : 0);
}

if ($rows === []) {
    fwrite(STDERR, "No data rows found in {$file}.\n");
    exit(1);
}

Database::transaction(function () use ($rows, $dryRun, $update, &$inserted, &$skipped, &$unresolved, &$errors) {
    foreach ($rows as $i => $row) {
        $name = trim((string) ($row['name'] ?? $row['union'] ?? $row['union_name'] ?? ''));
        if ($name === '') { $errors++; fwrite(STDERR, "row " . ($i + 2) . ": missing name\n"); continue; }

        $upozilaId = 0;
        if (isset($row['upozila_id']) && ctype_digit(trim((string) $row['upozila_id']))) {
            $upozilaId = (int) trim((string) $row['upozila_id']);
        } elseif (isset($row['upazila_id']) && ctype_digit(trim((string) $row['upazila_id']))) {
            $upozilaId = (int) trim((string) $row['upazila_id']);
        } else {
            $upaName = trim((string) ($row['upazila'] ?? $row['upozila'] ?? $row['upazila_name'] ?? ''));
            $distName = trim((string) ($row['district'] ?? $row['district_name'] ?? ''));
            if ($upaName === '') { $unresolved++; fwrite(STDERR, "row " . ($i + 2) . ": no upozila id or name\n"); continue; }
            $upozilaId = resolve_upozila($upaName, $distName);
            if ($upozilaId <= 0) { $unresolved++; fwrite(STDERR, "row " . ($i + 2) . ": cannot resolve upazila '{$upaName}'" . ($distName !== '' ? " in '{$distName}'" : '') . "\n"); continue; }
        }

        $userId = isset($row['user_id']) && ctype_digit(trim((string) $row['user_id'])) ? (int) trim((string) $row['user_id']) : 1;

        if ($dryRun) { $inserted++; continue; }

        $exists = Database::selectOne(
            'SELECT `id` FROM `unions` WHERE `upozila_id` = ? AND `name` = ? LIMIT 1',
            [$upozilaId, $name]
        );
        if ($exists !== null) {
            if (!$update) { $skipped++; continue; }
            Database::update('unions', ['updated_at' => date('Y-m-d H:i:s')], '`id` = :id', [':id' => (int) $exists['id']]);
            $skipped++;
            continue;
        }

        try {
            Database::insert('unions', [
                'name' => $name, 'upozila_id' => $upozilaId, 'user_id' => $userId,
                'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $inserted++;
        } catch (Throwable $e) {
            $errors++;
            fwrite(STDERR, "row " . ($i + 2) . " (name='{$name}'): " . $e->getMessage() . "\n");
        }
    }
});

echo "\nImport " . ($dryRun ? '(dry-run) ' : '') . "complete:\n";
echo "  inserted:   {$inserted}\n";
echo "  skipped:    {$skipped}\n";
echo "  unresolved: {$unresolved}\n";
echo "  errors:     {$errors}\n";
echo "  unions now: " . (int) Database::scalar('SELECT COUNT(*) FROM `unions`') . "\n";
exit(($errors > 0 || $unresolved > 0) ? 1 : 0);

// ---------------------------------------------------------------

/** Detect delimiter, skip an optional header, return normalised rows. */
function parse_csv(string $file): array
{
    $fh = fopen($file, 'r');
    if ($fh === false) { return []; }

    $first = (string) fgets($fh);
    $delim = ',';
    foreach (["\t" => "\t", ';' => ';', ',' => ','] as $d) {
        if (substr_count($first, $d) > substr_count($first, $delim)) { $delim = $d; }
    }
    rewind($fh);

    $rows = [];
    $header = null;
    while (($cells = fgetcsv($fh, 0, $delim, '"', '\\')) !== false) {
        if ($cells === [null] || (count($cells) === 1 && trim((string) $cells[0]) === '')) { continue; }
        $cells = array_map(static fn ($c) => trim((string) $c), $cells);
        if ($header === null && looks_like_header($cells)) {
            $header = array_map(static fn ($h) => strtolower(str_replace([' ', '-'], '_', $h)), $cells);
            continue;
        }
        if ($header === null) {
            // No header: assume (name, upozila_id).
            $header = ['name', 'upozila_id'];
        }
        $row = [];
        foreach ($header as $idx => $col) { $row[$col] = $cells[$idx] ?? ''; }
        $rows[] = $row;
    }
    fclose($fh);
    return $rows;
}

function looks_like_header(array $cells): bool
{
    foreach ($cells as $c) {
        $c = strtolower(str_replace([' ', '-'], '_', $c));
        if (in_array($c, ['name', 'union', 'union_name', 'upozila_id', 'upazila_id', 'upazila', 'upozila', 'upazila_name', 'district', 'district_name', 'user_id'], true)) {
            return true;
        }
    }
    return false;
}

function resolve_upozila(string $upaName, string $distName): int
{
    if ($distName !== '') {
        $rows = Database::select(
            'SELECT u.`id` FROM `upozilas` u JOIN `districts` d ON d.`id` = u.`district_id`
             WHERE u.`name` = ? AND d.`name` = ? LIMIT 5',
            [$upaName, $distName]
        );
    } else {
        $rows = Database::select('SELECT `id` FROM `upozilas` WHERE `name` = ? LIMIT 5', [$upaName]);
    }
    return count($rows) === 1 ? (int) $rows[0]['id'] : 0;
}

/** Idempotently relax the unions unique index and enable AUTO_INCREMENT. */
function apply_union_migration(): void
{
    // Already migrated? (composite unique present and id auto-increment)
    $hasComposite = (int) Database::scalar(
        "SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'unions' AND INDEX_NAME = 'unions_upozila_name_unique'"
    ) > 0;
    $autoInc = (string) Database::scalar(
        "SELECT EXTRA FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'unions' AND COLUMN_NAME = 'id'"
    );
    if ($hasComposite && stripos($autoInc, 'auto_increment') !== false) {
        echo "unions migration already applied.\n";
        return;
    }

    $legacy = (int) Database::scalar(
        "SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'unions' AND INDEX_NAME = 'unions_name_unique'"
    );
    if ($legacy > 0) {
        Database::statement('ALTER TABLE `unions` DROP INDEX `unions_name_unique`');
        echo "dropped legacy index unions_name_unique\n";
    }
    if (stripos($autoInc, 'auto_increment') === false) {
        Database::statement('ALTER TABLE `unions` MODIFY `id` bigint unsigned NOT NULL AUTO_INCREMENT');
        echo "id -> AUTO_INCREMENT\n";
    }
    if (!$hasComposite) {
        Database::statement('ALTER TABLE `unions` ADD UNIQUE KEY `unions_upozila_name_unique` (`upozila_id`, `name`)');
        echo "added unique (upozila_id, name)\n";
    }
}
