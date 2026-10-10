<?php
/**
 * Users + Roles (Manage User) pages test — the JSON shapes the UI reads.
 *
 * Usage: php tools/test_users_roles.php [base_url]
 *
 * Covers the contract mismatch that left /userrole empty and the inline-SQL
 * literal bug that made roleWisePermissionList return [].
 */

$base = $argv[1] ?? 'http://127.0.0.1:8090';
$jar = tempnam(sys_get_temp_dir(), 'bk_ur');
$failures = 0;
$csrf = '';

function req(string $method, string $url, ?array $body = null): array
{
    global $jar, $csrf;
    $ch = curl_init($url);
    $headers = ['Accept: application/json', 'X-Requested-With: XMLHttpRequest'];
    $cfg = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CUSTOMREQUEST => $method,
    ];
    if ($body !== null) {
        $cfg[CURLOPT_POSTFIELDS] = json_encode($body);
        $headers[] = 'Content-Type: application/json';
    }
    if ($csrf !== '') {
        $headers[] = 'X-CSRF-TOKEN: ' . $csrf;
    }
    $cfg[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($ch, $cfg);
    $raw = (string) curl_exec($ch);
    $st = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    return [$st, substr($raw, 0, $hs), substr($raw, $hs)];
}

function check(string $label, bool $ok, string $detail = ''): void
{
    global $failures;
    echo ($ok ? 'PASS' : 'FAIL') . "  $label" . ($ok ? '' : "  -- $detail") . "\n";
    if (!$ok) {
        $failures++;
    }
}

// ---- login ---------------------------------------------------------------
[, , $body] = req('GET', "$base/login");
preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $body, $m);
$csrf = $m[1] ?? '';
[$st, , $body] = req('POST', "$base/api/login", ['email' => 'mehedi@gmail.com', 'password' => 'admin123']);
check('admin login ok', $st === 200, "status=$st body=$body");

// ---- /api/roleList -------------------------------------------------------
[$st, , $body] = req('GET', "$base/api/roleList");
$j = json_decode($body, true);
$roles = $j['roleList'] ?? null;
check('GET /api/roleList -> {roleList:[...]}', $st === 200 && is_array($roles) && count($roles) > 0, "status=$st body=" . substr($body, 0, 200));
check('roleList rows have id+name', is_array($roles) && isset($roles[0]['id'], $roles[0]['name']));

// ---- /api/roleWisePermissionList (the [] regression) ---------------------
[$st, , $body] = req('GET', "$base/api/roleWisePermissionList");
$j = json_decode($body, true);
$mine = $j['roleWisePermissionList'] ?? null;
$names = is_array($mine) ? array_map(fn ($p) => is_array($p) ? ($p['name'] ?? '') : $p, $mine) : [];
check('GET /api/roleWisePermissionList -> non-empty {name} list', $st === 200 && is_array($mine) && count($mine) > 0, "status=$st body=" . substr($body, 0, 200));
check('roleWisePermissionList includes role-list', in_array('role-list', $names, true), 'names=' . count($names));

// ---- /api/permissionList -------------------------------------------------
[$st, , $body] = req('GET', "$base/api/permissionList");
$j = json_decode($body, true);
$perms = $j['permissionList'] ?? null;
check('GET /api/permissionList -> {permissionList:[{id,name}]}', $st === 200 && is_array($perms) && count($perms) > 0 && isset($perms[0]['id'], $perms[0]['name']), "status=$st body=" . substr($body, 0, 200));

// ---- /api/roleById/{id} --------------------------------------------------
$firstRoleId = (int) ($roles[0]['id'] ?? 0);
[$st, , $body] = req('GET', "$base/api/roleById/$firstRoleId");
$j = json_decode($body, true);
check('GET /api/roleById/{id} -> {role,rolePermissions}', $st === 200 && isset($j['role']['id'], $j['role']['name']) && is_array($j['rolePermissions']), "status=$st body=" . substr($body, 0, 200));

// ---- /api/userList (role_name) ------------------------------------------
[$st, , $body] = req('GET', "$base/api/userList");
$j = json_decode($body, true);
$users = $j['userList'] ?? null;
check('GET /api/userList -> {userList:[...]}', $st === 200 && is_array($users) && count($users) > 0, "status=$st body=" . substr($body, 0, 200));
$withRole = null;
foreach ((array) $users as $u) {
    if (($u['email'] ?? '') === 'mehedi@gmail.com') {
        $withRole = $u;
        break;
    }
}
check('userList row carries role_name', $withRole !== null && ($withRole['role_name'] ?? '') !== '', 'row=' . json_encode($withRole));

// ---- /api/userById/{id} (roles) -----------------------------------------
$meId = (int) ($withRole['id'] ?? 0);
if ($meId > 0) {
    [$st, , $body] = req('GET', "$base/api/userById/$meId");
    $j = json_decode($body, true);
    check('GET /api/userById/{id} -> {userById:{...,roles}}', $st === 200 && isset($j['userById']) && ($j['userById']['roles'] ?? '') !== '', "status=$st body=" . substr($body, 0, 200));
}

// ---- role create -> read -> delete --------------------------------------
$permIds = array_map(fn ($p) => (int) $p['id'], array_slice((array) $perms, 0, 2));
$roleName = 'ZZ Test Role ' . bin2hex(random_bytes(3));
[$st, , $body] = req('POST', "$base/api/roleCreate", ['name' => $roleName, 'permission' => $permIds]);
$j = json_decode($body, true);
$newRoleId = (int) ($j['data']['id'] ?? 0);
check('POST /api/roleCreate -> success + id', $st === 200 && $newRoleId > 0, "status=$st body=$body");

if ($newRoleId > 0) {
    [$st2, , $body2] = req('GET', "$base/api/roleById/$newRoleId");
    $j2 = json_decode($body2, true);
    $got = array_map('intval', (array) ($j2['rolePermissions'] ?? []));
    sort($got);
    $want = $permIds;
    sort($want);
    check('new role persisted its permissions', $st2 === 200 && $got === $want, 'got=' . json_encode($got) . ' want=' . json_encode($want));

    [$st3] = req('DELETE', "$base/api/roleDelete/$newRoleId");
    check('DELETE /api/roleDelete/{id} -> success', $st3 === 200, "status=$st3");
    [$st4] = req('GET', "$base/api/roleById/$newRoleId");
    check('deleted role -> 404', $st4 === 404, "status=$st4");
}

// ---- user create (with role) -> read -> delete --------------------------
if (!empty($roles)) {
    $roleId = (int) $roles[0]['id'];
    $email = 'zz.user.' . bin2hex(random_bytes(3)) . '@example.test';
    [$st, , $body] = req('POST', "$base/api/userCreate", [
        'name' => 'ZZ Test User',
        'email' => $email,
        'password' => 'Secret123',
        'confirmpassword' => 'Secret123',
        'roles' => $roleId,
    ]);
    $j = json_decode($body, true);
    $newUserId = (int) ($j['data']['id'] ?? 0);
    check('POST /api/userCreate (with role) -> success + id', $st === 200 && $newUserId > 0, "status=$st body=$body");

    if ($newUserId > 0) {
        [$st2, , $body2] = req('GET', "$base/api/userById/$newUserId");
        $j2 = json_decode($body2, true);
        check('created user has the assigned role id', $st2 === 200 && (int) ($j2['userById']['roles'] ?? 0) === $roleId, 'body=' . substr($body2, 0, 200));

        [$st3] = req('DELETE', "$base/api/userDelete/$newUserId");
        check('DELETE /api/userDelete/{id} -> success', $st3 === 200, "status=$st3");
    }
}

// ---- form pages render ---------------------------------------------------
[$st, , $body] = req('GET', "$base/create-role");
check('GET /create-role renders role form', $st === 200 && str_contains($body, 'id="role-form"') && str_contains($body, '/assets/js/roles.js'), "status=$st");
[$st, , $body] = req('GET', "$base/edit-role/$firstRoleId");
check('GET /edit-role/{id} renders role form', $st === 200 && str_contains($body, 'id="role-form"') && str_contains($body, 'data-role-id="' . $firstRoleId . '"'), "status=$st");
[$st, , $body] = req('GET', "$base/create-user");
check('GET /create-user renders user form', $st === 200 && str_contains($body, 'id="user-form"') && str_contains($body, '/assets/js/users.js'), "status=$st");
if ($meId > 0) {
    [$st, , $body] = req('GET', "$base/edit-user/$meId");
    check('GET /edit-user/{id} renders user form', $st === 200 && str_contains($body, 'id="user-form"') && str_contains($body, 'data-user-id="' . $meId . '"'), "status=$st");
}

@unlink($jar);
echo "\n" . ($failures === 0 ? 'All users/roles tests passed.' : "$failures users/roles test(s) FAILED.") . "\n";
exit($failures === 0 ? 0 : 1);
