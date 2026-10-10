<?php
/** Compare columns between testdform (source) and oop_exam (target). */
require __DIR__ . '/../bootstrap/app.php';

$pdo = App\Core\Database::pdo();

function cols(PDO $pdo, string $schema): array
{
    $rows = $pdo->query(
        "SELECT table_name AS tn, column_name AS cn, column_type AS ct,
                is_nullable AS nu, column_default AS cd, extra AS ex
           FROM information_schema.columns WHERE table_schema = " . $pdo->quote($schema) . "
          ORDER BY table_name, ordinal_position"
    )->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $r) {
        $out[$r['tn']][$r['cn']] =
            $r['ct'] . '|' . $r['nu'] . '|' . ($r['cd'] ?? 'NULL') . '|' . $r['ex'];
    }
    return $out;
}

$a = cols($pdo, 'testdform');
$b = cols($pdo, 'oop_exam');

foreach (array_unique(array_merge(array_keys($a), array_keys($b))) as $table) {
    $ac = $a[$table] ?? [];
    $bc = $b[$table] ?? [];
    if ($ac === $bc) {
        continue;
    }
    foreach (array_unique(array_merge(array_keys($ac), array_keys($bc))) as $col) {
        $av = $ac[$col] ?? '(missing)';
        $bv = $bc[$col] ?? '(missing)';
        if ($av !== $bv) {
            echo "$table.$col\n  source: $av\n  target: $bv\n";
        }
    }
}
echo "done\n";
