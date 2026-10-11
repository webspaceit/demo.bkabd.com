<?php
/**
 * Create the union-* permissions (Manage Area > Union Info) and grant them,
 * mirroring the existing upazila-* role grants. Idempotent — safe to run on
 * any database (fresh or already migrated).
 *
 *   php tools/add_union_permissions.php
 */
require __DIR__ . '/../bootstrap/app.php';

use App\Core\Database;

$actions = ['list', 'create', 'edit', 'delete'];
$created = [];
$grants = 0;

foreach ($actions as $action) {
    $name = "union-$action";

    if (!Database::selectOne('SELECT `id` FROM `permissions` WHERE `name` = ?', [$name])) {
        Database::insert('permissions', [
            'name' => $name,
            'guard_name' => 'sanctum',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $created[] = $name;
    }
}

foreach ($actions as $action) {
    $perm = Database::selectOne('SELECT `id` FROM `permissions` WHERE `name` = ?', ["union-$action"]);
    if (!$perm) {
        continue;
    }
    $pid = (int) $perm['id'];

    // Roles that already hold the matching upazila-* permission.
    $roles = Database::select(
        'SELECT rp.`role_id` AS role_id FROM `role_has_permissions` rp
           JOIN `permissions` p ON p.`id` = rp.`permission_id`
          WHERE p.`name` = ?',
        ["upazila-$action"]
    );

    // Super Admin (role 5) always gets every permission.
    if ($action === 'list') {
        $roles[] = ['role_id' => 5];
    }

    foreach ($roles as $r) {
        $rid = (int) $r['role_id'];
        $has = Database::selectOne(
            'SELECT 1 AS x FROM `role_has_permissions` WHERE `permission_id` = ? AND `role_id` = ?',
            [$pid, $rid]
        );
        if ($has) {
            continue;
        }
        Database::insert('role_has_permissions', ['permission_id' => $pid, 'role_id' => $rid]);
        $grants++;
    }
}

echo 'permissions created: ' . (count($created) ? implode(', ', $created) : 'none (already present)') . PHP_EOL;
echo "role grants added:   {$grants}" . PHP_EOL;
