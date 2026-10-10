<?php
/**
 * Centers test: delete guard (406 + count) and the institute center filter.
 * Usage: php tools/test_centers.php [base_url]
 *
 * Flow copied from tools/test_pages.php (login + CSRF + cookie jar).
 * Asserts:
 *   - GET /api/institutes?center_id=N returns only that center's institutes
 *   - GET /institutes?center_id=N renders (filter chip present)
 *   - DELETE /api/centers/{id} returns 406 + {status:'error', message,
 *     institutes} while institutes still reference the center (original guard)
 *   - the center is NOT deleted by the blocked request
 *   - anonymous DELETE is refused
 *
 * The delete target is deliberately a center that HAS institutes, so the
 * guard fires and no rows are removed.
 */

require __DIR__ . '/_test_credentials.php';

$base = $argv[1] ?? 'http://127.0.0.1:8090';
$cookieFile = tempnam(sys_get_temp_dir(), 'bk_ctr');

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
// 1. Login (see smoke_test.php / test_pages.php for the same flow)
// ---------------------------------------------------------------
[$st, $body] = pageReq("$base/login", $cookieFile);
check('GET /login renders', $st === 200 && str_contains($body, 'Sign in'), "status=$st");
preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $body, $m);
$csrf = $m[1] ?? '';
check('CSRF token present', $csrf !== '', 'no token');

[$st, $body] = req('POST', "$base/api/login", [
    'body' => test_login_json(),
    'contentType' => 'application/json',
    'headers' => ["X-CSRF-TOKEN: $csrf"],
]);
$decoded = json_decode($body, true);
check('POST /api/login ok', $st === 200 && ($decoded['success'] ?? false) === true, "status=$st body=$body");

// ---------------------------------------------------------------
// 2. Find a center that has institutes (via the institutes list)
// ---------------------------------------------------------------
[$st, $body] = req('GET', "$base/api/institutes?per_page=200");
$list = json_decode($body, true);
$candidates = array_values(array_filter(
    $list['data'] ?? [],
    fn(array $r): bool => !empty($r['center_id'])
));
check('GET /api/institutes returns a row with a center', $st === 200 && $candidates !== [],
    "status=$st body=" . substr($body, 0, 160));
$centerId = (int) $candidates[0]['center_id'];

// ---------------------------------------------------------------
// 3. center_id filter on /api/institutes
// ---------------------------------------------------------------
[$st, $body] = req('GET', "$base/api/institutes?per_page=200&center_id=$centerId");
$list = json_decode($body, true);
$rows = $list['data'] ?? [];
$mismatch = array_values(array_filter($rows, fn(array $r): bool => (int) ($r['center_id'] ?? 0) !== $centerId));
check('GET /api/institutes?center_id filters to one center',
    $st === 200 && count($rows) > 0 && $mismatch === [],
    "status=$st rows=" . count($rows) . ' mismatched=' . count($mismatch));

[$st, $body] = pageReq("$base/institutes?center_id=$centerId", $cookieFile);
check('GET /institutes?center_id renders with filter chip',
    $st === 200 && str_contains($body, 'institute-center-filter') && str_contains($body, 'institute-rows'),
    "status=$st");

// ---------------------------------------------------------------
// 4. Delete guard: center with institutes -> 406 + count, no delete
// ---------------------------------------------------------------
[$st, $body] = req('DELETE', "$base/api/centers/$centerId", [
    'headers' => ["X-CSRF-TOKEN: $csrf"],
]);
$guard = json_decode($body, true);
$count = (int) ($guard['institutes'] ?? 0);
check('DELETE /api/centers/{id} with institutes -> 406 + count',
    $st === 406
        && ($guard['status'] ?? '') === 'error'
        && $count >= 1
        && str_contains($body, 'registered in this center'),
    "status=$st body=" . substr($body, 0, 200));

// The blocked delete must not have removed the center.
[$st, $body] = req('GET', "$base/api/centers/$centerId");
$detail = json_decode($body, true);
check('center still exists after blocked delete',
    $st === 200 && isset($detail['centerById']),
    "status=$st body=" . substr($body, 0, 160));

// ---------------------------------------------------------------
// 5. Anonymous delete is refused
// ---------------------------------------------------------------
$anonJar = tempnam(sys_get_temp_dir(), 'bk_anon');
$ch = curl_init("$base/api/centers/$centerId");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_CUSTOMREQUEST => 'DELETE',
    CURLOPT_COOKIEJAR => $anonJar,
    CURLOPT_COOKIEFILE => $anonJar,
    CURLOPT_HTTPHEADER => ['X-Requested-With: XMLHttpRequest', 'Accept: application/json'],
    CURLOPT_TIMEOUT => 15,
]);
$raw = curl_exec($ch);
$anonStatus = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);
@unlink($anonJar);
check('anonymous DELETE /api/centers/{id} refused',
    in_array($anonStatus, [302, 401, 403], true),
    "status=$anonStatus");

echo "\nAll center checks passed.\n";
