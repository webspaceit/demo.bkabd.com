<?php
// One-off: print permission names from seed_data.sql
$sql = file_get_contents(__DIR__ . '/../database/seed_data.sql');
if (!preg_match('/INSERT INTO `permissions`.*?;\s/s', $sql, $m)) {
    exit("not found\n");
}
preg_match_all("/\((\d+),'([^']+)'/", $m[0], $pairs, PREG_SET_ORDER);
foreach ($pairs as [, $id, $name]) {
    echo "$id\t$name\n";
}
