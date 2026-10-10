<?php
/**
 * Exam docs test: admitcards and seatplan
 * Usage: php tools/test_examdocs.php [base_url]
 */

require __DIR__ . '/_test_credentials.php';

$base = $argv[1] ?? 'http://127.0.0.1:8090';
$cookieFile = tempnam(sys_get_temp_dir(), 'bk_cookie_exdocs');
$csrf = '';

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

function check(string $label, bool $ok, string $detail = ''): void
{
    echo ($ok ? 'PASS' : 'FAIL') . "  $label" . ($ok ? '' : "  -- $detail") . "\n";
    if (!$ok) {
        exit(1);
    }
}

// 1. Login page
[$st, $body] = req('GET', "$base/login");
check('GET /login renders', $st === 200 && str_contains($body, 'Sign in'), "status=$st");

// Get CSRF token from meta tag
if (preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $body, $m)) {
    $csrf = $m[1];
}
check('CSRF token present', $csrf !== '');

// 2. Login
[$st, $body] = req('POST', "$base/api/login", [
    'body' => test_login_json(),
    'contentType' => 'application/json',
    'headers' => ["X-CSRF-TOKEN: $csrf"],
]);
$decoded = json_decode($body, true);
check('POST /api/login ok', $st === 200 && ($decoded['success'] ?? false) === true, "status=$st");

// 3. /admitcards page returns 200
[$st, $body] = req('GET', "$base/admitcards");
check('GET /admitcards returns 200', $st === 200, "status=$st");

// 4. /seatplan page returns 200
[$st, $body] = req('GET', "$base/seatplan");
check('GET /seatplan returns 200', $st === 200, "status=$st");

// 5. Anonymous access to protected page - redirect
$cookieFile2 = tempnam(sys_get_temp_dir(), 'bk_cookie_exdocs2');
$oldCookie = $cookieFile;
$cookieFile = $cookieFile2;
$ch = curl_init("$base/admitcards");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_COOKIEJAR => $cookieFile2,
    CURLOPT_COOKIEFILE => $cookieFile2,
    CURLOPT_TIMEOUT => 15,
]);
$raw = curl_exec($ch);
$st = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
check('Anonymous /admitcards -> redirect', $st === 302, "status=$st");

$ch = curl_init("$base/seatplan");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_COOKIEJAR => $cookieFile2,
    CURLOPT_COOKIEFILE => $cookieFile2,
    CURLOPT_TIMEOUT => 15,
]);
$raw = curl_exec($ch);
$st = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
check('Anonymous /seatplan -> redirect', $st === 302, "status=$st");
$cookieFile = $oldCookie;
@unlink($cookieFile2);

// 6. Test backing API endpoints - get centers first
[$st, $body] = req('GET', "$base/api/centerList");
$decoded = json_decode($body, true);
check('GET /api/centerList returns 200', $st === 200 && isset($decoded['data']['centerList']), "status=$st");

// Get a center
$centerId = $decoded['data']['centerList'][0]['id'] ?? null;
if ($centerId) {
    [$st, $body] = req('GET', "$base/api/instituteListByCenter?center_id=$centerId");
    $decodedInst = json_decode($body, true);
    check('GET /api/instituteListByCenter returns 200', $st === 200 && isset($decodedInst['data']['instituteList']), "status=$st body=$body");
}

// Test seatplan print endpoint (should work with center and class)
if ($centerId) {
    // Try with class 1 as default
    [$st, $body] = req('GET', "$base/api/seatplan_print/$centerId/1");
    check('GET /api/seatplan_print returns 200', $st === 200, "status=$st");
}

// Test report-student-list API for admit cards
[$st, $body] = req('GET', "$base/api/report-student-list?per_page=5");
$decoded = json_decode($body, true);
check('GET /api/report-student-list returns 200', $st === 200 && isset($decoded['data']['studentList']) || isset($decoded['data']['paginator']), "status=$st");

@unlink($cookieFile);
echo "\nAll examdocs tests passed.\n";
