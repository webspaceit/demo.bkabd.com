<?php
/**
 * One-off repair: students created before the fix have registration/active/paid
 * NULL because StudentController::store() simply never passed them. Recompute
 * exactly what store() would have written (same algorithm), for those rows only.
 *
 *   php tools/fix_student_columns.php          (dry run — prints the plan)
 *   php tools/fix_student_columns.php --apply  (writes the changes)
 */
require __DIR__ . '/../bootstrap/app.php';

use App\Core\Database;

$apply = in_array('--apply', $argv ?? [], true);
$pdo = Database::pdo();

$broken = $pdo->query(
    "SELECT id, name, session, class, center_id, institute_id, registration, active, paid, created_at
       FROM `students`
      WHERE `registration` IS NULL OR `registration` = ''
         OR `active` IS NULL OR `paid` IS NULL
      ORDER BY `id`"
)->fetchAll(PDO::FETCH_ASSOC);

if (!$broken) {
    echo "nothing to fix: every student already has registration/active/paid.\n";
    exit(0);
}

echo ($apply ? 'APPLYING' : 'DRY RUN') . ' — ' . count($broken) . " broken row(s)\n\n";

$findLast = $pdo->prepare(
    "SELECT `registration` FROM `students`
      WHERE `center_id` = ? AND `institute_id` = ? AND `class` = ?
        AND `session` = ? AND `registration` IS NOT NULL AND `registration` <> ''
      ORDER BY `registration` DESC LIMIT 1"
);
$existsReg = $pdo->prepare("SELECT `id` FROM `students` WHERE `registration` = ? LIMIT 1");
$update = $pdo->prepare(
    "UPDATE `students`
        SET `registration` = ?, `active` = 1, `paid` = 0, `updated_at` = ?
      WHERE `id` = ?"
);

foreach ($broken as $s) {
    $id = (int) $s['id'];

    // Same base form as store().
    $base = substr((string) $s['session'], -2)
        . sprintf('%03u', (int) $s['center_id'])
        . sprintf('%04u', (int) $s['institute_id'])
        . (int) $s['class']
        . '001';

    $findLast->execute([$s['center_id'], $s['institute_id'], (int) $s['class'], (int) $s['session']]);
    $last = $findLast->fetchColumn();

    $reg = $base;
    if ($last !== false && $last !== null) {
        $tail = (int) substr((string) $last, -3);
        $reg = substr((string) $last, 0, -3) . sprintf('%03d', $tail + 1);
    }

    // The unique index would reject a collision — walk forward instead.
    $guard = 0;
    while ($guard++ < 1000) {
        $existsReg->execute([$reg]);
        if ($existsReg->fetchColumn() === false) {
            break;
        }
        $tail = (int) substr($reg, -3) + 1;
        $reg = substr($reg, 0, -3) . sprintf('%03d', $tail);
    }

    printf("  id=%d  %s\n", $id, $s['name']);
    printf("      registration: %s -> %s   active: %s -> 1   paid: %s -> 0\n",
        $s['registration'] === null ? 'NULL' : $s['registration'],
        $reg,
        $s['active'] === null ? 'NULL' : $s['active'],
        $s['paid'] === null ? 'NULL' : $s['paid']);

    if ($apply) {
        $update->execute([$reg, date('Y-m-d H:i:s'), $id]);
    }
}

echo "\n" . ($apply ? 'done — changes written.' : 'dry run only; re-run with --apply to write.') . "\n";
