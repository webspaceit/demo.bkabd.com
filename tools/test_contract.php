<?php
/**
 * URL-contract regression test for the endpoints added while closing the
 * PROJECT_ANALYSIS.md gaps:
 *
 *   Public : /membership-institute-registration, POST /api/ins-store,
 *            GET /api/institute-registration/{id}, /api/districtById,
 *            /api/upazilaById
 *   Auth   : /api/totalStudent, /api/totalInstitute, /api/user,
 *            /api/isAdmin, /api/isCenter, /api/userSave, /api/roleSave,
 *            /api/registrationpdf/{registration}, /api/settings CRUD
 *
 * Usage: php tools/test_contract.php [base_url]
 */

require __DIR__ . '/../bootstrap/app.php';

use App\Core\Database;

require __DIR__ . '/_test_credentials.php';

$base = $argv[1] ?? 'http://127.0.0.1:8090';
$cookieFile = tempnam(sys_get_temp_dir(), 'bk_contract');

function req(string $method, string $url, array $opts = []): array
{
    global $cookieFile;
    $ch = curl_init($url);
    $headers = $opts['headers'] ?? [];
    $cfg = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CUSTOMREQUEST => $method,
    ];
    if (isset($opts['body'])) {
        $cfg[CURLOPT_POSTFIELDS] = $opts['body'];
        if (is_string($opts['body'])) {
            $headers[] = 'Content-Type: application/json';
        }
    }
    $headers[] = 'X-Requested-With: XMLHttpRequest';
    $headers[] = 'Accept: application/json';
    $cfg[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($ch, $cfg);
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
        CURLOPT_TIMEOUT => 20,
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

// ---------------------------------------------------------------
// Public contract (no login)
// ---------------------------------------------------------------
[$st, $body] = pageReq("$base/membership-institute-registration", $cookieFile);
check('GET /membership-institute-registration renders form', $st === 200 && str_contains($body, 'institute-register-form'), "status=$st");
preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $body, $m);
$csrf = $m[1] ?? '';
check('public page exposes CSRF token', $csrf !== '', 'no token');

[$st, $body] = req('GET', "$base/api/districtById/1");
check('public GET /api/districtById/{id} -> districtById', $st === 200 && str_contains($body, 'districtById'), "status=$st body=$body");
[$st, $body] = req('GET', "$base/api/upazilaById/1");
check('public GET /api/upazilaById/{id} -> upazilaById', $st === 200 && str_contains($body, 'upazilaById'), "status=$st body=$body");

$instId = (int) (Database::selectOne('SELECT `id` FROM `institutes` ORDER BY `id` LIMIT 1')['id'] ?? 0);
check('an institute exists to render a card', $instId > 0, 'institutes empty');
[$st, $body] = req('GET', "$base/api/institute-registration/$instId");
check('public GET /api/institute-registration/{id} -> printable card', $st === 200 && str_contains($body, 'Registration Card'), "status=$st");

// ins-store validation (no auth, CSRF checked) -> 422 not 404/500
[$st, $body] = req('POST', "$base/api/ins-store", ['body' => ['_token' => $csrf]]);
check('POST /api/ins-store validates -> 422', $st === 422 && str_contains($body, 'errors'), "status=$st body=$body");

// Public self-registration happy path (multipart upload).
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
$logoPath = tempnam(sys_get_temp_dir(), 'bk_logo') . '.png';
file_put_contents($logoPath, $png);

$suffix = bin2hex(random_bytes(3));
$regName = 'Contract Institute ' . $suffix;
$regPhone = '017' . substr((string) (time() % 100000000), 0, 8);
$regEmail = "contract.$suffix@example.com";

$fields = [
    '_token' => $csrf,
    'name' => $regName,
    'director_name' => 'Contract Tester',
    'phone' => $regPhone,
    'email' => $regEmail,
    'website' => 'https://example.com',
    'division_id' => '1',
    'district_id' => '1',
    'upazila_id' => '1',
    'address' => 'Test Address',
    'zip' => '1200',
    'established_date' => '2020-01-01',
    'total_students' => '100',
    'total_teachers' => '10',
    'membership_type' => '1',
    'member_type' => '1',
    'type' => '1',
    'recognized' => '1',
    'institution_run' => '1',
    'monthly_income' => '1000',
    'monthly_expense' => '500',
    'note' => 'contract test',
    'logo' => new CURLFile($logoPath, 'image/png', 'logo.png'),
];
[$st, $body] = req('POST', "$base/api/ins-store", ['body' => $fields]);
$created = json_decode($body, true);
$newId = (int) ($created['id'] ?? 0);
check('POST /api/ins-store creates public registration', $st === 200 && ($created['status'] ?? '') === 'success' && $newId > 0, "status=$st body=$body");

[$st, $body] = req('GET', "$base/api/institute-registration/$newId");
check('new institute card renders', $st === 200 && str_contains($body, $regName), "status=$st");

// Duplicate registration -> 401 {status:errors} (original contract)
[$st, $body] = req('POST', "$base/api/ins-store", ['body' => $fields]);
check('duplicate POST /api/ins-store -> 401 errors', $st === 401 && str_contains($body, 'errors'), "status=$st body=$body");

// Cleanup the uploaded files + the row.
$uploaded = Database::selectOne('SELECT `logo`, `principal_photo` FROM `institutes` WHERE `id` = ?', [$newId]);
if ($uploaded !== null) {
    foreach (['images/logos' => $uploaded['logo'], 'images/principals' => $uploaded['principal_photo']] as $dir => $name) {
        if (!empty($name)) {
            @unlink(dirname(__DIR__) . '/public/' . $dir . '/' . basename((string) $name));
        }
    }
}
Database::delete('institutes', 'id = ?', [$newId]);
@unlink($logoPath);

// ---------------------------------------------------------------
// Authenticated contract
// ---------------------------------------------------------------
[$st, $body] = pageReq("$base/login", $cookieFile);
check('GET /login renders', $st === 200, "status=$st");
preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $body, $m);
$csrf = $m[1] ?? '';
[$st, $body] = req('POST', "$base/api/login", [
    'body' => test_login_json(),
    'headers' => ["X-CSRF-TOKEN: $csrf"],
]);
check('POST /api/login ok', $st === 200, "status=$st body=$body");

// Anonymous must be rejected by the new auth endpoints.
$anon = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bk_contract_anon_' . bin2hex(random_bytes(3));
[$st] = pageReq("$base/api/totalInstitute", $anon);
check('anonymous GET /api/totalInstitute -> 401', $st === 401, "status=$st");
@unlink($anon);

[$st, $body] = req('GET', "$base/api/totalStudent");
check('GET /api/totalStudent -> totalstudents', $st === 200 && array_key_exists('totalstudents', json_decode($body, true) ?? []), "status=$st body=$body");

// Regression: the list page always sends empty filter params; they must be
// ignored, and the page must default to the active session.
[$st, $body] = req('GET', "$base/api/students?page=1&per_page=20&session=&class=&center_id=&institute_id=&search=");
$pager = json_decode($body, true)['data'] ?? [];
check('GET /api/students with empty filters returns rows', $st === 200 && ($pager['total'] ?? 0) > 0, "status=$st total=" . ($pager['total'] ?? '?'));

$activeSession = (string) (Database::selectOne('SELECT `session` FROM `sessions` ORDER BY `id` DESC LIMIT 1')['session'] ?? '');
[$st, $body] = req('GET', "$base/api/students?session=" . urlencode($activeSession) . "&class=&center_id=&institute_id=&search=");
$pager = json_decode($body, true)['data'] ?? [];
check("GET /api/students?session=$activeSession returns rows", $st === 200 && ($pager['total'] ?? 0) > 0, "status=$st total=" . ($pager['total'] ?? '?'));

[$st, $body] = pageReq("$base/students", $cookieFile);
check('GET /students defaults session dropdown to active session', $st === 200 && str_contains($body, 'value="' . $activeSession . '" selected'), "status=$st active=$activeSession");
check('GET /students page script waits for DOMContentLoaded (BK loaded)', $st === 200 && str_contains($body, "document.addEventListener('DOMContentLoaded'"), 'inline script runs before app.js');
// Each list row renderer must emit a <tr>: BK.loadList concatenates raw HTML
// into tbody.innerHTML, so bare <td> cells collapse every row into one.
check('GET /students row renderer wraps rows in <tr>', $st === 200 && str_contains($body, "return '<tr><td>'"), 'rows would collapse into a single <tr>');

[$st, $body] = pageReq("$base/centers", $cookieFile);
check('GET /centers renders (200)', $st === 200, "status=$st");

[$st, $body] = req('GET', "$base/assets/js/centers.js");
check('centers.js row renderer wraps rows in <tr>', $st === 200 && str_contains($body, "return '<tr><td>'"), 'rows would collapse into a single <tr>');

// Full-marks config (markentry/gradeassign caps) must exist for the active session.
[$st, $body] = req('GET', "$base/api/fullmarks?session=$activeSession");
$fm = json_decode($body, true)['data'] ?? [];
check("GET /api/fullmarks?session=$activeSession -> config", $st === 200 && (int) ($fm['bangla'] ?? 0) > 0, "status=$st body=$body");

[$st, $body] = req('GET', "$base/api/totalInstitute");
check('GET /api/totalInstitute -> totalinstitutes', $st === 200 && array_key_exists('totalinstitutes', json_decode($body, true) ?? []), "status=$st body=$body");

[$st, $body] = req('GET', "$base/api/user");
check('GET /api/user -> username', $st === 200 && array_key_exists('username', json_decode($body, true) ?? []), "status=$st body=$body");

[$st, $body] = req('GET', "$base/api/isAdmin");
$flag = json_decode($body, true);
check('GET /api/isAdmin -> status+isAdmin', $st === 200 && ($flag['status'] ?? '') === 'success' && array_key_exists('isAdmin', $flag), "status=$st body=$body");

[$st, $body] = req('GET', "$base/api/isCenter");
$flag = json_decode($body, true);
check('GET /api/isCenter -> status+isAdmin', $st === 200 && ($flag['status'] ?? '') === 'success' && array_key_exists('isAdmin', $flag), "status=$st body=$body");

// URL-contract aliases exist (empty payload -> 422, not 404).
[$st, $body] = req('POST', "$base/api/userSave", ['body' => json_encode([]), 'headers' => ["X-CSRF-TOKEN: $csrf"]]);
check('POST /api/userSave alias routes to store -> 422', $st === 422, "status=$st body=$body");
[$st, $body] = req('POST', "$base/api/roleSave", ['body' => json_encode([]), 'headers' => ["X-CSRF-TOKEN: $csrf"]]);
check('POST /api/roleSave alias routes to store -> 422', $st === 422, "status=$st body=$body");

// Student registration card (auth).
$registration = (string) (Database::selectOne('SELECT `registration` FROM `students` WHERE `registration` IS NOT NULL LIMIT 1')['registration'] ?? '');
if ($registration !== '') {
    [$st, $body] = req('GET', "$base/api/registrationpdf/" . rawurlencode($registration));
    check('GET /api/registrationpdf/{registration} -> printable card', $st === 200 && str_contains($body, 'Registration'), "status=$st");
}

// Settings CRUD.
[$st, $body] = req('GET', "$base/api/settings");
$pager = json_decode($body, true) ?? [];
check('GET /api/settings -> paginator', $st === 200 && array_key_exists('data', $pager) && array_key_exists('current_page', $pager), "status=$st body=$body");

[$st, $body] = req('POST', "$base/api/settings", [
    'body' => json_encode(['title' => 'Contract Setting', 'description' => 'created by test_contract']),
    'headers' => ["X-CSRF-TOKEN: $csrf"],
]);
$setting = json_decode($body, true) ?? [];
$settingId = (int) ($setting['setting']['id'] ?? 0);
check('POST /api/settings -> created', $st === 200 && ($setting['status'] ?? '') === 'success' && $settingId > 0, "status=$st body=$body");

[$st, $body] = req('GET', "$base/api/settings/$settingId");
check('GET /api/settings/{id} -> settingById', $st === 200 && array_key_exists('settingById', json_decode($body, true) ?? []), "status=$st body=$body");

[$st, $body] = req('DELETE', "$base/api/settings/$settingId", ['headers' => ["X-CSRF-TOKEN: $csrf"]]);
check('DELETE /api/settings/{id} -> success', $st === 200 && str_contains($body, 'success'), "status=$st body=$body");
[$st] = req('GET', "$base/api/settings/$settingId");
check('GET deleted setting -> 404', $st === 404, "status=$st");

@unlink($cookieFile);
echo "\nAll contract tests passed.\n";
