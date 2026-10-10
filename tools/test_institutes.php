<?php
/**
 * Institutes module test: pages, permissions, CRUD, validity, status.
 * Usage: php tools/test_institutes.php [base_url]
 */

$base = $argv[1] ?? 'http://127.0.0.1:8090';
$cookieFile = tempnam(sys_get_temp_dir(), 'bk_inst');

function req(string $method, string $url, array $opts = []): array
{
    global $cookieFile;
    $ch = curl_init($url);
    $headers = $opts['headers'] ?? [];
    $opts2 = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CUSTOMREQUEST => $method,
    ];
    if (isset($opts['body'])) {
        $opts2[CURLOPT_POSTFIELDS] = $opts['body'];
        $headers[] = 'Content-Type: application/json';
    }
    $headers[] = 'X-Requested-With: XMLHttpRequest';
    $headers[] = 'Accept: application/json';
    $opts2[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($ch, $opts2);
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return [(int) $status, substr((string) $raw, $headerSize)];
}

function pageReq(string $url, string $jar): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_TIMEOUT => 15,
    ]);
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return [(int) $status, substr((string) $raw, $headerSize)];
}

function check(string $label, bool $ok, string $detail = ''): void
{
    echo ($ok ? 'PASS' : 'FAIL') . "  $label" . ($ok ? '' : "  -- $detail") . "\n";
    if (!$ok) {
        exit(1);
    }
}

// 1. Login
[$st, $body] = pageReq("$base/login", $cookieFile);
check('GET /login renders', $st === 200, "status=$st");
preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $body, $m);
$csrf = $m[1] ?? '';
check('CSRF token present', $csrf !== '', 'no token');

[$st, $body] = req('POST', "$base/api/login", [
    'body' => json_encode(['email' => 'mehedi@gmail.com', 'password' => 'admin123']),
    'headers' => ["X-CSRF-TOKEN: $csrf"],
]);
check('POST /api/login ok', $st === 200, "status=$st");
[$st, $body] = req('GET', "$base/api/center");
$centers = json_decode($body, true)['centerList'] ?? [];
$centerId = $centers[0]['id'] ?? null;
check('center dropdown source available', $centerId !== null, "status=$st");

// 2. Pages
[$st, $body] = pageReq("$base/institutes", $cookieFile);
check('GET /institutes renders', $st === 200 && str_contains($body, 'institute-rows'), "status=$st");
[$st, $body] = pageReq("$base/institute-registration", $cookieFile);
check('GET /institute-registration renders', $st === 200 && str_contains($body, 'institute-form'), "status=$st");

// 3. Anonymous gates
$anon = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bk_inst_anon_' . bin2hex(random_bytes(3));
[$st] = pageReq("$base/institutes", $anon);
check('anonymous /institutes -> 302', $st === 302, "status=$st");
[$st] = pageReq("$base/validityinstitute/1", $anon);
check('anonymous /validityinstitute -> 302', $st === 302, "status=$st");
[$st] = pageReq("$base/api/institutes", $anon);
check('anonymous API -> 401', $st === 401, "status=$st");
@unlink($anon);

// 4. Validation
[$st, $body] = req('POST', "$base/api/institutes", [
    'body' => json_encode(['name' => '']),
    'headers' => ["X-CSRF-TOKEN: $csrf"],
]);
check('POST invalid -> 422 errors.name', $st === 422 && str_contains($body, 'name'), "status=$st body=$body");

// 5. Create
$name = 'Test Institute ' . bin2hex(random_bytes(2));
[$st, $body] = req('POST', "$base/api/institutes", [
    'body' => json_encode([
        'center_id' => $centerId, 'name' => $name, 'director_name' => 'Tester',
        'phone' => '01700000000', 'email' => '', 'division_id' => '', 'district_id' => '',
        'upazila_id' => '', 'active' => '1',
    ]),
    'headers' => ["X-CSRF-TOKEN: $csrf"],
]);
check('POST /api/institutes create -> success', $st === 200 && str_contains($body, '"success"'), "status=$st body=$body");

// 6. List + search
[$st, $body] = req('GET', "$base/api/institutes?search=" . urlencode($name));
$pager = json_decode($body, true);
$created = null;
foreach (($pager['data'] ?? []) as $row) {
    if (($row['name'] ?? '') === $name) {
        $created = $row;
    }
}
check('GET /api/institutes search finds created row', $st === 200 && $created !== null, "status=$st");
check('list row carries center_name', array_key_exists('center_name', $created ?? []), 'no center_name');
$id = (int) $created['id'];

// 7. Show
[$st, $body] = req('GET', "$base/api/institutes/$id");
$shown = json_decode($body, true)['instituteById'] ?? null;
check('GET /api/institutes/{id} -> instituteById', $st === 200 && ($shown['name'] ?? '') === $name, "status=$st");

// 7b. Center -> active institutes list (original /api/instituteById/{centerId}).
[$st, $body] = req('GET', "$base/api/instituteById/$centerId");
$list = json_decode($body, true)['instituteById'] ?? null;
$listIds = is_array($list) ? array_map('intval', array_column($list, 'id')) : [];
check('GET /api/instituteById/{center} -> list includes created institute', $st === 200 && in_array($id, $listIds, true), "status=$st body=" . substr($body, 0, 160));

// 8. Update
[$st, $body] = req('PUT', "$base/api/institutes/$id", [
    'body' => json_encode([
        'center_id' => $centerId, 'name' => $name . ' Edited', 'director_name' => 'Tester',
        'phone' => '01700000000', 'active' => '1',
    ]),
    'headers' => ["X-CSRF-TOKEN: $csrf"],
]);
check('PUT /api/institutes/{id} -> success', $st === 200, "status=$st body=$body");
[, $body] = req('GET', "$base/api/institutes/$id");
check('update persisted', (json_decode($body, true)['instituteById']['name'] ?? '') === $name . ' Edited', 'name not updated');

// 9. Validity (original requires BOTH last_renewal_date and validity_date)
[$st, $body] = req('PUT', "$base/api/institutevalidity/$id", [
    'body' => json_encode(['validity_date' => '2030-12-31']),
    'headers' => ["X-CSRF-TOKEN: $csrf"],
]);
check('PUT /api/institutevalidity missing last_renewal_date -> 422', $st === 422 && str_contains($body, 'last_renewal_date'), "status=$st body=$body");

[$st, $body] = req('PUT', "$base/api/institutevalidity/$id", [
    'body' => json_encode(['last_renewal_date' => '2025-01-01', 'validity_date' => '2030-12-31']),
    'headers' => ["X-CSRF-TOKEN: $csrf"],
]);
check('PUT /api/institutevalidity/{id} -> success', $st === 200, "status=$st body=$body");
[, $body] = req('GET', "$base/api/institutes/$id");
$afterValidity = json_decode($body, true)['instituteById'] ?? [];
check('validity_date persisted', ($afterValidity['validity_date'] ?? '') === '2030-12-31', 'validity not stored');
check('last_renewal_date persisted', ($afterValidity['last_renewal_date'] ?? '') === '2025-01-01', 'renewal not stored');

// 10. Status toggle
[$st, $body] = req('POST', "$base/api/institutes/changestatus/$id/0", ['headers' => ["X-CSRF-TOKEN: $csrf"]]);
check('POST changestatus -> success', $st === 200, "status=$st body=$body");
[, $body] = req('GET', "$base/api/institutes/$id");
check('status persisted as inactive', (string) (json_decode($body, true)['instituteById']['active'] ?? '') === '0', 'status not changed');

// 11. Edit page
[$st, $body] = pageReq("$base/edit-institute/$id", $cookieFile);
check('GET /edit-institute/{id} renders', $st === 200 && str_contains($body, 'Edit Institute'), "status=$st");

// 11b. Validity page (original SPA route /validityinstitute/:id)
[$st, $body] = pageReq("$base/validityinstitute/$id", $cookieFile);
check('GET /validityinstitute/{id} renders', $st === 200 && str_contains($body, 'institute-validity-page') && str_contains($body, 'Set Institute Validity'), "status=$st");

// 12. Delete
[$st, $body] = req('DELETE', "$base/api/institutes/$id", ['headers' => ["X-CSRF-TOKEN: $csrf"]]);
check('DELETE /api/institutes/{id} -> success', $st === 200, "status=$st body=$body");
[$st] = req('GET', "$base/api/institutes/$id");
check('GET after delete -> 404', $st === 404, "status=$st");

@unlink($cookieFile);
echo "\nAll institutes tests passed.\n";
