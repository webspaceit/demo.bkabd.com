<?php
/**
 * Spine smoke test: login flow, auth guard, CSRF, 404s.
 * Usage: php tools/smoke_test.php [base_url]
 */

$base = $argv[1] ?? 'http://127.0.0.1:8090';
$cookieFile = tempnam(sys_get_temp_dir(), 'bk_cookie');

function req(string $method, string $url, array $opts = []) : array
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

// 1. Login page renders (no auth)
[$st, $body] = req('GET', "$base/login");
check('GET /login renders', $st === 200 && str_contains($body, 'Sign in'), "status=$st");
preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $body, $m);
$csrf = $m[1] ?? '';
check('CSRF token present', $csrf !== '');

// 2. Wrong password -> 401 {status:errors}
[$st, $body] = req('POST', "$base/api/login", [
    'body' => json_encode(['email' => 'mehedi@gmail.com', 'password' => 'wrong']),
    'contentType' => 'application/json',
    'headers' => ["X-CSRF-TOKEN: $csrf"],
]);
check('POST /api/login bad creds -> 401', $st === 401 && str_contains($body, 'errors'), "status=$st body=$body");

// 3. Correct login
[$st, $body] = req('POST', "$base/api/login", [
    'body' => json_encode(['email' => 'mehedi@gmail.com', 'password' => 'admin123']),
    'contentType' => 'application/json',
    'headers' => ["X-CSRF-TOKEN: $csrf"],
]);
$decoded = json_decode($body, true);
check('POST /api/login ok', $st === 200 && ($decoded['success'] ?? false) === true && !empty($decoded['data']['token']), "status=$st body=$body");

// 4. Dashboard with session cookie
[$st, $body] = req('GET', "$base/");
check('GET / dashboard', $st === 200 && str_contains($body, 'Dashboard'), "status=$st");

// 5. sessionList API
[$st, $body] = req('GET', "$base/api/sessionList");
$decoded = json_decode($body, true);
check('GET /api/sessionList', $st === 200 && !empty($decoded['sessionList']), "status=$st body=$body");

// 6. updateSession override
$firstSession = $decoded['sessionList'][0]['id'] ?? 1;
[$st, $body] = req('GET', "$base/api/updateSession/$firstSession");
check('GET /api/updateSession', $st === 200 && str_contains($body, 'success'), "status=$st body=$body");

// 7. Protected API without auth (fresh cookie jar)
$cookieFile2 = tempnam(sys_get_temp_dir(), 'bk_cookie2');
$oldCookie = $cookieFile;
$cookieFile = $cookieFile2;
[$st, $body] = req('GET', "$base/api/sessionList");
check('Protected API unauthenticated -> 401', $st === 401, "status=$st body=$body");

// 7b. Plain browser page request (no XHR headers) -> redirect to /login
$ch = curl_init("$base/");
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
check('Protected page unauthenticated -> redirect login', $st === 302, "status=$st");
$cookieFile = $oldCookie;
@unlink($cookieFile2);

// 8. CSRF enforcement (POST without token)
[$st, $body] = req('POST', "$base/api/logout", ['body' => '{}', 'contentType' => 'application/json']);
check('POST without CSRF -> 419', $st === 419, "status=$st body=$body");

// 9. 404s
[$st, $body] = req('GET', "$base/api/definitely-missing");
check('Unknown API -> 404 JSON', $st === 404, "status=$st");

// 10. Logout
[$st, $body] = req('POST', "$base/api/logout", [
    'body' => json_encode([]),
    'contentType' => 'application/json',
    'headers' => ["X-CSRF-TOKEN: $csrf"],
]);
check('POST /api/logout', $st === 200 && str_contains($body, 'success'), "status=$st body=$body");

@unlink($cookieFile);
echo "\nAll spine tests passed.\n";
