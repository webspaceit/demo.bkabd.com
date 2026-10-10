<?php
/**
 * Pages test: /students, /user, /userrole, / (home), /api/dashboard.
 * Usage: php tools/test_pages.php [base_url]
 *
 * Flow copied from tools/smoke_test.php (login + CSRF + cookie jar).
 * Asserts:
 *   - all four pages render 200 for admin (permission-gated routes pass)
 *   - /api/dashboard returns 200 with the original Dashboard.vue keys
 *   - DELETE /api/students/{id} is registered (CSRF enforced, handler reachable)
 *   - every page 302-redirects to /login when anonymous
 */

$base = $argv[1] ?? 'http://127.0.0.1:8090';
$cookieFile = tempnam(sys_get_temp_dir(), 'bk_page');

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
    ];
    if ($method !== 'GET') {
        $opts2[CURLOPT_CUSTOMREQUEST] = $method;
    }
    if (isset($opts['body'])) {
        $opts2[CURLOPT_POSTFIELDS] = $opts['body'];
        if (is_array($opts['body']) && !isset($opts['contentType'])) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        } elseif (isset($opts['contentType'])) {
            $headers[] = 'Content-Type: ' . $opts['contentType'];
        }
    }
    $headers[] = 'X-Requested-With: XMLHttpRequest';
    $headers[] = 'Accept: application/json';
    $opts2[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($ch, $opts2);
    $raw = curl_exec($ch);
    if ($raw === false) {
        return [0, '', curl_error($ch)];
    }
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    return [$status, substr($raw, $headerSize), substr($raw, 0, $headerSize)];
}

/** Plain browser request (NO XHR header) with its own cookie jar. */
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
    return [$status, substr($raw, $headerSize), substr($raw, 0, $headerSize)];
}

function check(string $label, bool $ok, string $detail = ''): void
{
    echo ($ok ? 'PASS' : 'FAIL') . "  $label" . ($ok ? '' : "  -- $detail") . "\n";
    if (!$ok) {
        exit(1);
    }
}

// ---------------------------------------------------------------
// 1. Login as admin (see smoke_test.php for the same flow)
// ---------------------------------------------------------------
[$st, $body] = pageReq("$base/login", $cookieFile);
check('GET /login renders', $st === 200 && str_contains($body, 'Sign in'), "status=$st");
preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $body, $m);
$csrf = $m[1] ?? '';
check('CSRF token present', $csrf !== '', 'no token');

[$st, $body] = req('POST', "$base/api/login", [
    'body' => json_encode(['email' => 'mehedi@gmail.com', 'password' => 'admin123']),
    'contentType' => 'application/json',
    'headers' => ["X-CSRF-TOKEN: $csrf"],
]);
$decoded = json_decode($body, true);
check('POST /api/login ok', $st === 200 && ($decoded['success'] ?? false) === true, "status=$st body=$body");

// ---------------------------------------------------------------
// 2. Dashboard home + stats API
// ---------------------------------------------------------------
[$st, $body] = pageReq("$base/", $cookieFile);
check('GET / dashboard (home) 200', $st === 200 && str_contains($body, 'Welcome to') && str_contains($body, 'data-stat'), "status=$st");
// The CSRF token is rotated on login (defense in depth); a full page load
// re-issues the meta tag exactly like the browser after sign-in, so re-grab it.
preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $body, $m2);
if (!empty($m2[1])) { $csrf = $m2[1]; }

[$st, $body] = req('GET', "$base/api/dashboard");
$stats = json_decode($body, true);
$expectedKeys = [
    'totalstudents', 'totalinstitutes', 'totalcenter', 'totaldivision',
    'totaldistrict', 'totalupazila', 'totalnotice', 'totaluseraccess',
    'attendcenter', 'attendinstitute',
];
$missing = array_values(array_filter($expectedKeys, fn(string $k): bool => !array_key_exists($k, $stats)));
check('GET /api/dashboard 200 with original keys', $st === 200 && $missing === [], "status=$st keys_missing=" . implode(',', $missing) . ' body=' . substr($body, 0, 160));
check('GET /api/dashboard counts are numbers', array_values(array_filter($expectedKeys, fn(string $k): bool => !is_numeric($stats[$k] ?? null))) === [],
    'non_numeric=' . implode(',', array_values(array_filter($expectedKeys, fn(string $k): bool => !is_numeric($stats[$k] ?? null)))));
[$st, $body] = req('GET', "$base/api/dashboardcount");
check('GET /api/dashboardcount alias works', $st === 200 && isset(json_decode($body, true)['totalstudents']), "status=$st body=" . substr($body, 0, 120));

// ---------------------------------------------------------------
// 3. Permission-gated pages
// ---------------------------------------------------------------
[$st, $body] = pageReq("$base/students", $cookieFile);
check('GET /students 200 (student-list)', $st === 200 && str_contains($body, 'student-rows'), "status=$st");

// Filtered list: paginator shape + server-side registration search.
[$st, $body] = req('GET', "$base/api/students?per_page=200");
$list = json_decode($body, true);
$pager = $list['data'] ?? [];
check('GET /api/students paginator shape', $st === 200 && is_array($pager['data'] ?? null) && isset($pager['total']), "status=$st body=" . substr($body, 0, 160));

// Pick a sample student that has a registration number (first rows can be blank).
$sample = null;
foreach (($pager['data'] ?? []) as $r) {
    if (!empty($r['registration']) && !empty($r['id'])) { $sample = $r; break; }
}
check('sample student found for filter checks', $sample !== null, 'no row with registration in first 200');

$firstReg = $sample['registration'] ?? '';
$firstId = $sample['id'] ?? null;

[$st, $body] = req('GET', "$base/api/students?per_page=5&search=" . urlencode($firstReg));
$rows = ((json_decode($body, true))['data']['data'] ?? []);
$found = array_values(array_filter($rows, fn($r): bool => ($r['registration'] ?? '') === $firstReg));
check('GET /api/students search filter works', $st === 200 && $found !== [], "status=$st reg=$firstReg");

$cls = (string) ($sample['class'] ?? '');
$cid = (string) ($sample['center_id'] ?? '');
[$st, $body] = req('GET', "$base/api/students?per_page=200&class=" . urlencode($cls) . '&center_id=' . urlencode($cid));
$rows = ((json_decode($body, true))['data']['data'] ?? []);
$matchAll = array_values(array_filter($rows, fn($r): bool => (string) ($r['class'] ?? '') === $cls && (string) ($r['center_id'] ?? '') === $cid));
$hasSample = in_array($sample['id'], array_column($rows, 'id'), true);
check('GET /api/students class+center filters apply', $st === 200 && count($rows) !== 0 && count($rows) === count($matchAll) && $hasSample, "status=$st rows=" . count($rows) . " mismatched=" . (count($rows) - count($matchAll)) . " hasSample=" . ($hasSample ? 'y' : 'n'));

if ($firstId !== null) {
    [$st, $body] = req('GET', "$base/api/students/$firstId");
    $detail = json_decode($body, true);
    check('GET /api/students/{id} detail', $st === 200 && isset($detail['data']['name']), "status=$st body=" . substr($body, 0, 140));
}

[$st, $body] = pageReq("$base/user", $cookieFile);
check('GET /user 200 (user-list)', $st === 200 && str_contains($body, 'user-rows') && str_contains($body, '/assets/js/users.js'), "status=$st");

[$st, $body] = pageReq("$base/userrole", $cookieFile);
check('GET /userrole 200 (role-list)', $st === 200 && str_contains($body, 'role-rows') && str_contains($body, '/assets/js/roles.js'), "status=$st");

// ---------------------------------------------------------------
// 4. DELETE /api/students/{id} route + CSRF (route existence check)
// ---------------------------------------------------------------
[$st, $body] = req('DELETE', "$base/api/students/999999999", [
    'headers' => ["X-CSRF-TOKEN: $csrf"],
]);
check('DELETE /api/students/{id} registered (404 Student not found)', $st === 404 && str_contains($body, 'Student not found'), "status=$st body=$body");

[$st, $body] = req('DELETE', "$base/api/students/1");
check('DELETE without CSRF -> 419', $st === 419, "status=$st body=$body");

// ---------------------------------------------------------------
// 5. Server-side flash messages render as toasts (data-flash bridge)
// ---------------------------------------------------------------
[$st, $jsBody] = req('GET', "$base/assets/js/app.js");
check('app.js ships the data-flash -> toast bridge', $st === 200 && str_contains($jsBody, 'data-flash'), "status=$st");

[$st, $body] = req('GET', "$base/api/updateSession/3");
check('GET /api/updateSession sets a success flash', $st === 200 && str_contains($body, '"success"'), "status=$st body=" . substr($body, 0, 120));

[$st, $body] = pageReq("$base/", $cookieFile);
check(
    'pending flash renders as data-flash toast node, not the green box',
    $st === 200
        && str_contains($body, 'data-flash="success"')
        && str_contains($body, 'Exam session changed to 2025.')
        && !str_contains($body, 'bg-emerald-50 text-emerald-800'),
    "status=$st node=" . (str_contains($body, 'data-flash="success"') ? 'y' : 'n') . ' msg=' . (str_contains($body, 'Exam session changed to 2025.') ? 'y' : 'n')
);

[$st, $body] = pageReq("$base/", $cookieFile);
check('flash is one-shot (consumed by the previous page)', $st === 200 && !str_contains($body, 'data-flash="success"'), "status=$st");

[$st, $body] = req('GET', "$base/api/updateSession/4");
check('restore admin exam session to 2026', $st === 200, "status=$st body=" . substr($body, 0, 120));
[$st, $body] = pageReq("$base/", $cookieFile); // consume the restore flash

// ---------------------------------------------------------------
// 6. Anonymous redirects (fresh cookie jar, plain requests)
// ---------------------------------------------------------------
$anon = tempnam(sys_get_temp_dir(), 'bk_page_anon');
foreach (['/', '/students', '/user', '/userrole'] as $path) {
    [$st, $body, $headers] = pageReq("$base$path", $anon);
    $loc = preg_match('#^Location:\s*(.*)$#mi', $headers, $m) ? trim($m[1]) : '';
    check(
        "Anonymous GET $path -> 302 /login",
        $st === 302 && ($loc === '/login' || $loc === "$base/login"),
        "status=$st loc=$loc headers=$headers"
    );
}
@unlink($anon);

// Login again on the main jar (previous requests only read it).
unset($anon);

@unlink($cookieFile);
echo "\nAll page tests passed.\n";