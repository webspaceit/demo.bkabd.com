<?php
/**
 * Certificates + reports hub test.
 * Usage: php tools/test_certificates.php [base_url]
 */

$base = $argv[1] ?? 'http://127.0.0.1:8090';
$cookieFile = tempnam(sys_get_temp_dir(), 'bk_cert');

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

function check(string $label, bool $ok, string $detail = ''): void
{
    echo ($ok ? 'PASS' : 'FAIL') . "  $label" . ($ok ? '' : "  -- $detail") . "\n";
    if (!$ok) {
        exit(1);
    }
}

// 1. Login
[$st, $body] = req('GET', "$base/login");
preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $body, $m);
$csrf = $m[1] ?? '';
check('CSRF token present', $csrf !== '', 'no token');
[$st] = req('POST', "$base/api/login", [
    'body' => json_encode(['email' => 'mehedi@gmail.com', 'password' => 'admin123']),
    'headers' => ["X-CSRF-TOKEN: $csrf"],
]);
check('POST /api/login ok', $st === 200, "status=$st");

// 2. Pages
[$st, $body] = req('GET', "$base/reports");
check('GET /reports hub renders', $st === 200 && str_contains($body, 'Reports'), "status=$st");
check('hub links to both certificate pages', str_contains($body, '/student-certificate') && str_contains($body, '/institute-certificate'));
[$st, $body] = req('GET', "$base/student-certificate");
check('GET /student-certificate renders', $st === 200 && str_contains($body, 'sc-institute'), "status=$st");
[$st, $body] = req('GET', "$base/institute-certificate");
check('GET /institute-certificate renders', $st === 200 && str_contains($body, 'ic-institute'), "status=$st");

// 3. Student certificates (printable HTML). The sample active session (2026)
//    has no awarded grades yet, so an empty result shows a readable empty state
//    instead of a blank page.
[$st, $body] = req('GET', "$base/api/certificate/1/1");
check('GET /api/certificate/{institute}/{class} -> empty state', $st === 200 && str_contains($body, 'data-empty="1"'), "status=$st");

// Populated render: switch the admin exam session to 2025 (which has grades 1-3),
// then restore 2026 so the rest of the run is unaffected.
[$st] = req('GET', "$base/api/updateSession/3");
check('switch admin exam session to 2025', $st === 200, "status=$st");
[$st, $body] = req('GET', "$base/api/certificate/318/1");
check(
    'GET /api/certificate/{institute}/{class} -> certificates rendered',
    $st === 200 && substr_count($body, 'class="page"') > 0,
    "status=$st pages=" . substr_count($body, 'class="page"')
);
[$st] = req('GET', "$base/api/updateSession/4");
check('restore admin exam session to 2026', $st === 200, "status=$st");

// 4. Institute certificate guards and output
[$st, $body] = req('POST', "$base/api/institutes", [
    'body' => json_encode(['center_id' => 1, 'name' => 'Cert Test ' . bin2hex(random_bytes(2)), 'active' => '1']),
    'headers' => ["X-CSRF-TOKEN: $csrf"],
]);
check('create test institute', $st === 200, "status=$st body=$body");
[, $body] = req('GET', "$base/api/institutes?search=Cert%20Test&per_page=50");
$rows = json_decode($body, true)['data'] ?? [];
$testId = null;
foreach ($rows as $row) {
    if (str_starts_with((string) $row['name'], 'Cert Test') && empty($row['validity_date'])) {
        $testId = (int) $row['id'];
    }
}
check('found test institute without validity', $testId !== null);

[$st, $body] = req('GET', "$base/api/institute-certificate/$testId");
check('institute certificate without validity -> 422', $st === 422 && str_contains($body, 'validity date'), "status=$st body=" . substr($body, 0, 160));

[$st] = req('PUT', "$base/api/institutevalidity/$testId", [
    'body' => json_encode(['last_renewal_date' => '2024-01-01', 'validity_date' => '2031-06-30']),
    'headers' => ["X-CSRF-TOKEN: $csrf"],
]);
check('set validity on test institute', $st === 200, "status=$st");
[$st, $body] = req('GET', "$base/api/institute-certificate/$testId");
check('institute certificate with validity -> 200 HTML', $st === 200 && str_contains($body, 'membership registration number') && str_contains($body, '30-06-2031'), "status=$st");

// 5. Cleanup
[$st] = req('DELETE', "$base/api/institutes/$testId", ['headers' => ["X-CSRF-TOKEN: $csrf"]]);
check('cleanup test institute', $st === 200, "status=$st");

@unlink($cookieFile);
echo "\nAll certificate and reports-hub tests passed.\n";
