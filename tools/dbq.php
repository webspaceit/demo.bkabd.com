<?php
/** Run ad-hoc SQL (passed as argv[1] or @file) and print rows as TSV. Usage:
 *  php dbq.php "SELECT id, registration FROM students LIMIT 5"
 *  php dbq.php @path/to/query.sql
 */
require __DIR__ . '/../bootstrap/app.php';

$sql = $argv[1] ?? '';
if ($sql === '') {
    fwrite(STDERR, "usage: dbq.php \"SQL\" | dbq.php @file.sql\n");
    exit(1);
}
if ($sql[0] === '@') {
    $sql = file_get_contents(substr($sql, 1));
}

$pdo  = App\Core\Database::pdo();
$parts = array_filter(array_map('trim', explode(";\n", $sql)), fn($s): bool => $s !== '');
foreach ($parts as $one) {
    $stmt = $pdo->query($one);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cells = [];
        foreach ($row as $v) {
            $cells[] = $v === null ? 'NULL' : (string) $v;
        }
        echo implode("\t", $cells), "\n";
    }
    echo "--\n";
}
