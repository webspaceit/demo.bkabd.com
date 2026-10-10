<?php
/**
 * Module test: Exam Sessions + Notices + Routines (pages + APIs + CRUD cycle).
 * Usage: php tools/test_sessions.php [base_url]
 *
 * Covers:
 *   pages  /sessions /notices /routines (+ create/edit subpages) — 200 admin, 302 anon
 *   APIs   GET/POST/PUT/DELETE /api/sessions|notices|routines + /api/recentSession + /api/noticeById
 *   shapes raw paginator (sessions, routines), {noticeList}, {sessionById}, {noticeById},
 *          {routineById}, {status:'success'}, 422 validation, 419 CSRF, 401 anon, 404 missing
 */

require __DIR__ . '/../bootstrap/app.php';

use App\Core\Database;

$base = $argv[1] ?? 'http://127.0.0.1:8090';
$cookieFile = tempnam(sys_get_temp_dir(), 'bk_cookie');

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

/** Plain browser request (no XHR/Accept headers) — used for the 302 checks. */
function plainReq(string $url, string $jar): array
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
    if ($raw === false) {
        return [0, ''];
    }
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    return [$status, substr($raw, $headerSize)];
}

function check(string $label, bool $ok, string $detail = ''): void
{
    echo ($ok ? 'PASS' : 'FAIL') . "  $label" . ($ok ? '' : "  -- $detail") . "\n";
    if (!$ok) {
        exit(1);
    }
}

function jsonBody(string $body): array
{
    $decoded = json_decode($body, true);
    return is_array($decoded) ? $decoded : [];
}

// ---------------------------------------------------------------------
// 1. Login (same flow as tools/smoke_test.php)
// ---------------------------------------------------------------------
[$st, $body] = req('GET', "$base/login");
check('GET /login renders', $st === 200 && str_contains($body, 'Sign in'), "status=$st");
preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $body, $m);
$csrf = $m[1] ?? '';
check('CSRF token present', $csrf !== '');

// A foreign/malformed session cookie (e.g. the original Laravel app's, same host
// and same cookie name) must not crash session_start() with a PHP warning.
$ch = curl_init("$base/login");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => ['Cookie: bkabd_session=eyJpdiI6IkFiQz09In0='],
]);
$badCookieBody = (string) curl_exec($ch);
$badCookieStatus = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
check(
    'malformed bkabd_session cookie ignored (no PHP warning)',
    $badCookieStatus === 200
        && !str_contains($badCookieBody, 'Session ID is too long')
        && !str_contains($badCookieBody, 'Failed to read session data'),
    "status=$badCookieStatus"
);

[$st, $body] = req('POST', "$base/api/login", [
    'body' => json_encode(['email' => 'mehedi@gmail.com', 'password' => 'admin123']),
    'contentType' => 'application/json',
    'headers' => ["X-CSRF-TOKEN: $csrf"],
]);
$decoded = jsonBody($body);
check('POST /api/login ok', $st === 200 && ($decoded['success'] ?? false) === true && !empty($decoded['data']['token']), "status=$st body=$body");

// ---------------------------------------------------------------------
// 2. Pages render for admin (200) — sidebar menu items come with them
// ---------------------------------------------------------------------
$pages = [
    '/sessions' => "List of All Session's",
    '/notices' => 'Notices (',
    '/routines' => "List of All Routine's",
    '/createSession' => 'id="session-form"',
    '/create-notice' => 'id="notice-form"',
    '/createRoutine' => 'id="routine-form"',
];
foreach ($pages as $path => $marker) {
    [$st, $body] = req('GET', "$base$path");
    check("GET $path renders", $st === 200 && str_contains($body, $marker), "status=$st marker missing");
}

// ---------------------------------------------------------------------
// 3. Anonymous: pages redirect (302), APIs 401
// ---------------------------------------------------------------------
$anonJar = tempnam(sys_get_temp_dir(), 'bk_anon');
foreach (['/sessions', '/notices', '/routines'] as $path) {
    [$st] = plainReq("$base$path", $anonJar);
    check("Anonymous GET $path -> 302", $st === 302, "status=$st");
}
foreach (['/api/sessions', '/api/notices', '/api/routines'] as $path) {
    $save = $cookieFile;
    $cookieFile = $anonJar;
    [$st, $body] = req('GET', "$base$path");
    $cookieFile = $save;
    check("Anonymous GET $path -> 401", $st === 401, "status=$st body=$body");
}
$save = $cookieFile;
$cookieFile = $anonJar;
[$st, $body] = req('DELETE', "$base/api/routines/1");
$cookieFile = $save;
check('Anonymous DELETE /api/routines/{id} -> 401', $st === 401, "status=$st body=$body");
@unlink($anonJar);

// ---------------------------------------------------------------------
// 4. CSRF enforcement on one of the module endpoints
// ---------------------------------------------------------------------
[$st, $body] = req('POST', "$base/api/sessions", [
    'body' => json_encode(['session' => 2030, 'result' => 1, 'result_time' => '2030-06-15 18:00']),
    'contentType' => 'application/json',
]);
check('POST /api/sessions without CSRF -> 419', $st === 419, "status=$st body=$body");

// ---------------------------------------------------------------------
// 5. Sessions: list shape, validation, CRUD cycle
// ---------------------------------------------------------------------
[$st, $body] = req('GET', "$base/api/sessions");
$pager = jsonBody($body);
check('GET /api/sessions -> raw paginator shape',
    $st === 200 && isset($pager['current_page'], $pager['data'], $pager['total'], $pager['last_page'], $pager['links'])
        && is_array($pager['data']),
    "status=$st body=" . substr($body, 0, 300));

[$st, $body] = req('POST', "$base/api/sessions", [
    'body' => json_encode(['result' => 1]),
    'contentType' => 'application/json',
    'headers' => ["X-CSRF-TOKEN: $csrf"],
]);
$decoded = jsonBody($body);
check('POST /api/sessions invalid -> 422 errors.session',
    $st === 422 && isset($decoded['errors']['session']),
    "status=$st body=$body");

$sessionYear = 2030 + (int) date('i') % 20;
[$st, $body] = req('POST', "$base/api/sessions", [
    'body' => json_encode(['session' => $sessionYear, 'result' => 1, 'result_time' => "$sessionYear-06-15T18:00"]),
    'contentType' => 'application/json',
    'headers' => ["X-CSRF-TOKEN: $csrf"],
]);
$decoded = jsonBody($body);
check('POST /api/sessions create -> {status:success}',
    $st === 200 && ($decoded['status'] ?? '') === 'success',
    "status=$st body=$body");

[$st, $body] = req('GET', "$base/api/sessions?search=$sessionYear");
$pager = jsonBody($body);
$sessionRow = null;
foreach (($pager['data'] ?? []) as $row) {
    if ((int) $row['session'] === $sessionYear) {
        $sessionRow = $row;
    }
}
check('GET /api/sessions?search= finds created row', $st === 200 && $sessionRow !== null, "status=$st body=" . substr($body, 0, 300));
$sessionId = (int) ($sessionRow['id'] ?? 0);
check('created session is the active one', $sessionRow !== null && (int) $sessionRow['active'] === 1, 'active flag not flipped');

[$st, $body] = req('GET', "$base/api/sessions/$sessionId");
$decoded = jsonBody($body);
check('GET /api/sessions/{id} -> {sessionById}',
    $st === 200 && (int) ($decoded['sessionById']['session'] ?? 0) === $sessionYear,
    "status=$st body=$body");

[$st, $body] = req('GET', "$base/editSession/$sessionId");
check('GET /editSession/{id} renders', $st === 200 && str_contains($body, 'id="session-form"'), "status=$st");

$newYear = $sessionYear + 1;
[$st, $body] = req('PUT', "$base/api/sessions/$sessionId", [
    'body' => json_encode(['session' => $newYear, 'result' => 0, 'result_time' => "$newYear-06-15 18:00"]),
    'contentType' => 'application/json',
    'headers' => ["X-CSRF-TOKEN: $csrf"],
]);
$decoded = jsonBody($body);
check('PUT /api/sessions/{id} -> {status:success}', $st === 200 && ($decoded['status'] ?? '') === 'success', "status=$st body=$body");

[$st, $body] = req('GET', "$base/api/sessions/$sessionId");
$decoded = jsonBody($body);
check('PUT /api/sessions/{id} persisted',
    (int) ($decoded['sessionById']['session'] ?? 0) === $newYear && (int) ($decoded['sessionById']['result'] ?? -1) === 0,
    "body=$body");

[$st, $body] = req('GET', "$base/api/recentSession");
$decoded = jsonBody($body);
check('GET /api/recentSession -> {recentSession}', $st === 200 && isset($decoded['recentSession']), "status=$st body=$body");

[$st, $body] = req('DELETE', "$base/api/sessions/$sessionId", ['headers' => ["X-CSRF-TOKEN: $csrf"]]);
$decoded = jsonBody($body);
check('DELETE /api/sessions/{id} -> {status:success}', $st === 200 && ($decoded['status'] ?? '') === 'success', "status=$st body=$body");

[$st, $body] = req('GET', "$base/api/sessions/$sessionId");
check('GET /api/sessions/{id} after delete -> 404', $st === 404, "status=$st body=$body");

// Test hygiene: POST (activate) + DELETE above cleared the sessions.active flag.
// Restore it on the latest session (Auth::activeSession() always uses the latest
// row, so this matches the "(active)" badge shown in the UI).
$latest = Database::selectOne('SELECT `id` FROM `sessions` ORDER BY `id` DESC LIMIT 1');
if ($latest !== null) {
    Database::update('sessions', ['active' => 0], '1 = 1');
    Database::update('sessions', ['active' => 1], '`id` = :id', [':id' => (int) $latest['id']]);
}

// ---------------------------------------------------------------------
// 6. Notices: {noticeList} shape, validation, CRUD cycle
// ---------------------------------------------------------------------
[$st, $body] = req('GET', "$base/api/notices");
$decoded = jsonBody($body);
check('GET /api/notices -> {noticeList: [...]}', $st === 200 && is_array($decoded['noticeList'] ?? null), "status=$st body=" . substr($body, 0, 300));

[$st, $body] = req('POST', "$base/api/notices", [
    'body' => json_encode(['title' => 'no center']),
    'contentType' => 'application/json',
    'headers' => ["X-CSRF-TOKEN: $csrf"],
]);
$decoded = jsonBody($body);
check('POST /api/notices invalid -> 422 errors.center_id', $st === 422 && isset($decoded['errors']['center_id']), "status=$st body=$body");

$noticeTitle = 'Port Test Notice ' . time();
[$st, $body] = req('POST', "$base/api/notices", [
    'body' => json_encode(['center_id' => 0, 'title' => $noticeTitle, 'description' => 'Created by tools/test_sessions.php']),
    'contentType' => 'application/json',
    'headers' => ["X-CSRF-TOKEN: $csrf"],
]);
$decoded = jsonBody($body);
check('POST /api/notices create -> {status:success}', $st === 200 && ($decoded['status'] ?? '') === 'success', "status=$st body=$body");

[$st, $body] = req('GET', "$base/api/notices");
$decoded = jsonBody($body);
$noticeRow = null;
foreach (($decoded['noticeList'] ?? []) as $row) {
    if (($row['title'] ?? '') === $noticeTitle) {
        $noticeRow = $row;
    }
}
check('created notice present in noticeList', $noticeRow !== null, 'not found');
check('noticeList row carries center relation key',
    $noticeRow !== null && array_key_exists('center', $noticeRow) && array_key_exists('id', $noticeRow)
        && array_key_exists('title', $noticeRow) && array_key_exists('description', $noticeRow)
        && array_key_exists('center_id', $noticeRow),
    'row=' . json_encode($noticeRow));
$noticeId = (int) ($noticeRow['id'] ?? 0);

[$st, $body] = req('GET', "$base/api/notices/$noticeId");
$decoded = jsonBody($body);
check('GET /api/notices/{id} -> {noticeById}', $st === 200 && ($decoded['noticeById']['title'] ?? '') === $noticeTitle, "status=$st body=$body");

[$st, $body] = req('GET', "$base/editnotice/$noticeId");
check('GET /editnotice/{id} renders', $st === 200 && str_contains($body, 'id="notice-form"'), "status=$st");

$noticeTitle2 = 'Port Test Notice Updated ' . time();
[$st, $body] = req('PUT', "$base/api/notices/$noticeId", [
    'body' => json_encode(['center_id' => 0, 'title' => $noticeTitle2, 'description' => 'Updated by test']),
    'contentType' => 'application/json',
    'headers' => ["X-CSRF-TOKEN: $csrf"],
]);
$decoded = jsonBody($body);
check('PUT /api/notices/{id} -> {status:success}', $st === 200 && ($decoded['status'] ?? '') === 'success', "status=$st body=$body");

[$st, $body] = req('GET', "$base/api/notices/$noticeId");
$decoded = jsonBody($body);
check('PUT /api/notices/{id} persisted', ($decoded['noticeById']['title'] ?? '') === $noticeTitle2, "body=$body");

[$st, $body] = req('GET', "$base/api/noticeById");
$decoded = jsonBody($body);
check('GET /api/noticeById -> {noticeById: [...]}', $st === 200 && is_array($decoded['noticeById'] ?? null), "status=$st body=" . substr($body, 0, 300));

[$st, $body] = req('DELETE', "$base/api/notices/$noticeId", ['headers' => ["X-CSRF-TOKEN: $csrf"]]);
$decoded = jsonBody($body);
check('DELETE /api/notices/{id} -> {status:success}', $st === 200 && ($decoded['status'] ?? '') === 'success', "status=$st body=$body");

[$st, $body] = req('GET', "$base/api/notices/$noticeId");
check('GET /api/notices/{id} after delete -> 404', $st === 404, "status=$st body=$body");

// ---------------------------------------------------------------------
// 7. Routines: paginator shape, validation, CRUD cycle
// ---------------------------------------------------------------------
[$st, $body] = req('GET', "$base/api/routines");
$pager = jsonBody($body);
check('GET /api/routines -> raw paginator shape',
    $st === 200 && isset($pager['current_page'], $pager['data'], $pager['total'], $pager['last_page'], $pager['links'])
        && is_array($pager['data']),
    "status=$st body=" . substr($body, 0, 300));

[$st, $body] = req('POST', "$base/api/routines", [
    'body' => json_encode(['day' => 'Saturday']),
    'contentType' => 'application/json',
    'headers' => ["X-CSRF-TOKEN: $csrf"],
]);
$decoded = jsonBody($body);
check('POST /api/routines invalid -> 422 errors.subject', $st === 422 && isset($decoded['errors']['subject']), "status=$st body=$body");

$subject = 'Port Test Subject ' . time();
[$st, $body] = req('POST', "$base/api/routines", [
    'body' => json_encode([
        'subject' => $subject,
        'date' => '2026-06-20',
        'day' => 'Saturday',
        'time' => '10:00',
        'order' => 1,
    ]),
    'contentType' => 'application/json',
    'headers' => ["X-CSRF-TOKEN: $csrf"],
]);
$decoded = jsonBody($body);
check('POST /api/routines create -> {status:success}', $st === 200 && ($decoded['status'] ?? '') === 'success', "status=$st body=$body");

[$st, $body] = req('GET', "$base/api/routines?search=" . rawurlencode($subject));
$pager = jsonBody($body);
$routineRow = $pager['data'][0] ?? null;
check('GET /api/routines?search= finds created row', $st === 200 && $routineRow !== null && $routineRow['subject'] === $subject, "status=$st body=" . substr($body, 0, 300));
$routineId = (int) ($routineRow['id'] ?? 0);

[$st, $body] = req('GET', "$base/api/routines/$routineId");
$decoded = jsonBody($body);
check('GET /api/routines/{id} -> {routineById}', $st === 200 && ($decoded['routineById']['subject'] ?? '') === $subject, "status=$st body=$body");

[$st, $body] = req('GET', "$base/editRoutine/$routineId");
check('GET /editRoutine/{id} renders', $st === 200 && str_contains($body, 'id="routine-form"'), "status=$st");

$subject2 = $subject . ' Updated';
[$st, $body] = req('PUT', "$base/api/routines/$routineId", [
    'body' => json_encode([
        'subject' => $subject2,
        'date' => '2026-06-21',
        'day' => 'Sunday',
        'time' => '11:00',
        'order' => 2,
    ]),
    'contentType' => 'application/json',
    'headers' => ["X-CSRF-TOKEN: $csrf"],
]);
$decoded = jsonBody($body);
check('PUT /api/routines/{id} -> {status:success}', $st === 200 && ($decoded['status'] ?? '') === 'success', "status=$st body=$body");

[$st, $body] = req('GET', "$base/api/routines/$routineId");
$decoded = jsonBody($body);
check('PUT /api/routines/{id} persisted',
    ($decoded['routineById']['subject'] ?? '') === $subject2 && ($decoded['routineById']['day'] ?? '') === 'Sunday'
        && (int) ($decoded['routineById']['order'] ?? 0) === 2,
    "body=$body");

[$st, $body] = req('DELETE', "$base/api/routines/$routineId", ['headers' => ["X-CSRF-TOKEN: $csrf"]]);
$decoded = jsonBody($body);
check('DELETE /api/routines/{id} -> {status:success}', $st === 200 && ($decoded['status'] ?? '') === 'success', "status=$st body=$body");

[$st, $body] = req('GET', "$base/api/routines/$routineId");
check('GET /api/routines/{id} after delete -> 404', $st === 404, "status=$st body=$body");

// ---------------------------------------------------------------------
// 8. Edit subpages for deleted records -> 404 (never fatal)
// ---------------------------------------------------------------------
[$st] = req('GET', "$base/editSession/$sessionId");
check('GET /editSession/{id} after delete -> 404', $st === 404, "status=$st");

@unlink($cookieFile);
echo "\nAll sessions/notices/routines tests passed.\n";
