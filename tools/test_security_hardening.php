<?php
/**
 * Security hardening test — BOLA / RLS, rate limiting, password hashing and
 * two-factor authentication (TOTP).
 *
 * Usage:
 *   php tools/test_security_hardening.php [base_url]      (default 127.0.0.1:8090)
 *
 * Part A (unit) — always runs:
 *   - HOTP core vs the published RFC 4226 test vectors (proves the TOTP codes
 *     match what Google Authenticator / Authy produce)
 *   - base32 encode/decode round trip
 *   - RateLimiter fixed-window behaviour + reset
 *   - password hashing / password_needs_rehash
 *
 * Part B (DB) — runs when the database is reachable:
 *   - Auth::assertCenterAccess / assertInstituteAccess / assertStudentAccess
 *     with a simulated center-user session (own = pass, any other = 403)
 *   - the same guards pass for an admin
 *   - ReportSheetService::rows() forces the caller's center (data-layer RLS)
 *
 * Part C (HTTP) — runs when a server is listening on [base_url]:
 *   - end-to-end MFA login on a throwaway user (created + deleted here, so no
 *     real account is touched): setup -> enable -> logout -> login
 *     (mfa_required) -> wrong code 422 -> correct code -> disable
 *   - anonymous access to a guarded report endpoint -> 401
 *   - admin report smoke test (RLS guards must not block admins)
 */

require __DIR__ . '/../bootstrap/app.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Totp;
use App\Models\User;
use App\Services\ReportSheetService;

require __DIR__ . '/_test_credentials.php';

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8090', '/');
$failures = 0;

// ---------------------------------------------------------------
// helpers
// ---------------------------------------------------------------

function check(string $label, bool $ok, string $detail = ''): void
{
    global $failures;
    echo ($ok ? 'PASS' : 'FAIL') . "  $label" . ($ok ? '' : "  -- $detail") . "\n";
    if (!$ok) {
        $failures++;
    }
}

/** Run $fn and return true when it throws HttpException with $code. */
function throwsHttp(callable $fn, int $code): bool
{
    try {
        $fn();
    } catch (HttpException $e) {
        return $e->status() === $code;
    } catch (\Throwable $e) {
        return false;
    }
    return false;
}

/** Invoke a private/protected static method (test-only reflection). */
function callPrivate(string $class, string $method, array $args): mixed
{
    $m = new \ReflectionMethod($class, $method);
    return $m->invokeArgs(null, $args);
}

/** Force the Auth identity without a real session (simulated login). */
function setAuthUser(?int $id): void
{
    $ref = new \ReflectionClass(Auth::class);
    if ($id === null) {
        $ref->setStaticPropertyValue('user', null);
        $ref->setStaticPropertyValue('loaded', true); // "loaded, nobody"
        $ref->setStaticPropertyValue('permissions', null);
        return;
    }
    $row = Database::selectOne('SELECT * FROM `users` WHERE `id` = ?', [$id]);
    $ref->setStaticPropertyValue('user', $row);
    $ref->setStaticPropertyValue('loaded', true);
    $ref->setStaticPropertyValue('permissions', null);
}

/** DB reachable? */
function dbUp(): bool
{
    try {
        Database::pdo();
        return true;
    } catch (\Throwable $e) {
        return false;
    }
}

// ===============================================================
// Part A — unit
// ===============================================================

echo "== Part A: unit ==\n";

// A0. The bootstrap's own App\ autoloader must resolve a newly added class
// without a `composer dump-autoload` (the production failure mode where an
// optimised/authoritative classmap predates the file).
$probePath = BASE_PATH . '/app/Core/ZzAutoloadProbe.php';
file_put_contents($probePath, "<?php\nnamespace App\\Core;\nclass ZzAutoloadProbe {}\n");
try {
    check('App\\ autoloader resolves a brand-new class', class_exists(App\Core\ZzAutoloadProbe::class));
} finally {
    @unlink($probePath);
}

// A0b. Log redaction never keeps quoted PII.
$redacted = Database::redact("SQLSTATE[23000]: Duplicate entry 'student@example.com' for key 'users_email_unique'");
check('log redaction masks quoted values', !str_contains($redacted, 'student@example.com') && str_contains($redacted, '***'));

// A1. HOTP core vs RFC 4226 Appendix D vectors (secret ASCII "12345678901234567890").
$rfcKey = '12345678901234567890';
$rfcB32 = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
$vectors = [0 => '755224', 1 => '287082', 2 => '359152', 3 => '969429', 4 => '338314'];
foreach ($vectors as $counter => $expected) {
    $got = callPrivate(Totp::class, 'hotp', [$rfcKey, $counter, 6]);
    check("HOTP(RFC4226) counter=$counter == $expected", $got === $expected, "got=$got");
}

// A2. base32.
check('base32 decode(RFC secret)', callPrivate(Totp::class, 'base32Decode', [$rfcB32]) === $rfcKey);
check('base32 encode round trip', callPrivate(Totp::class, 'base32Encode', [$rfcKey]) === $rfcB32);

// A3. generateSecret + verify against the current time step.
$secret = Totp::generateSecret();
check('generateSecret() is 32-char base32', (bool) preg_match('/^[A-Z2-7]{32}$/', $secret), $secret);
$key = callPrivate(Totp::class, 'base32Decode', [$secret]);
$now = str_pad(callPrivate(Totp::class, 'hotp', [$key, (int) floor(time() / 30), 6]), 6, '0', STR_PAD_LEFT);
check('Totp::verify accepts the current code', Totp::verify($secret, $now) === true);
check('Totp::verify rejects a wrong code', Totp::verify($secret, $now === '000000' ? '111111' : '000000') === false);
check('Totp::verify rejects non-6-digit input', Totp::verify($secret, '42') === false);
check('provisioningUri() shape', str_starts_with(Totp::provisioningUri($secret, 'a@b.c', 'BKABD'), 'otpauth://totp/BKABD:a%40b.c?'));

// A4. RateLimiter.
$ip = '203.0.113.55';
RateLimiter::clear('selftest', $ip);
RateLimiter::hit('selftest', 2, 60, $ip);
RateLimiter::hit('selftest', 2, 60, $ip);
check('RateLimiter: 3rd hit over a 2/window limit -> 429', throwsHttp(fn() => RateLimiter::hit('selftest', 2, 60, $ip), 429));
RateLimiter::clear('selftest', $ip);
check('RateLimiter: clear() resets the window', !throwsHttp(fn() => RateLimiter::hit('selftest', 2, 60, $ip), 429));
RateLimiter::clear('selftest', $ip);

// A5. Password hashing.
$hash = password_hash('S3cret!', PASSWORD_DEFAULT);
check('password_hash()/verify round trip', password_verify('S3cret!', $hash));
check('stored hash is bcrypt or argon2', str_starts_with($hash, '$2y$') || str_starts_with($hash, '$argon2'));
check('fresh hash does not need rehash', password_needs_rehash($hash, PASSWORD_DEFAULT) === false);
$low = password_hash('x', PASSWORD_BCRYPT, ['cost' => 4]);
check('low-cost hash wants rehash', password_needs_rehash($low, PASSWORD_DEFAULT) === true);

// ===============================================================
// Part B — RLS guards (DB)
// ===============================================================

echo "\n== Part B: BOLA / RLS guards ==\n";

if (!dbUp()) {
    echo "SKIP  Part B (database not reachable)\n";
} else {
    $ownCenter = 3;   // center of user 20 (bkarangamati@gmail.com)
    $otherCenter = 45; // JHENAIDAH

    // Simulate a logged-in center user (type 4).
    setAuthUser(20);
    $cid = Auth::centerId();
    check('center user resolves to own center (3)', $cid === $ownCenter, "centerId=$cid");
    check('center user is not admin', Auth::isAdmin() === false);

    check('assertCenterAccess(own 3) passes', !throwsHttp(fn() => Auth::assertCenterAccess($ownCenter), 403));
    check('assertCenterAccess(other 45) -> 403', throwsHttp(fn() => Auth::assertCenterAccess($otherCenter), 403));
    check('assertCenterAccess(0 / all) -> 403', throwsHttp(fn() => Auth::assertCenterAccess(0), 403));

    check('assertInstituteAccess(own inst 1) passes', !throwsHttp(fn() => Auth::assertInstituteAccess(1), 403));
    check('assertInstituteAccess(other inst 467) -> 403', throwsHttp(fn() => Auth::assertInstituteAccess(467), 403));

    $sOwnRow = Database::selectOne('SELECT `id` FROM `students` WHERE `center_id` = ? LIMIT 1', [$ownCenter]);
    $sOtherRow = Database::selectOne('SELECT `id` FROM `students` WHERE `center_id` = ? LIMIT 1', [$otherCenter]);
    $sOwn = (int) ($sOwnRow['id'] ?? 0);
    $sOther = (int) ($sOtherRow['id'] ?? 0);
    check('student fixtures exist', $sOwn > 0 && $sOther > 0, "own=$sOwn other=$sOther");
    check('assertStudentAccess(own) passes', !throwsHttp(fn() => Auth::assertStudentAccess([$sOwn]), 403));
    check('assertStudentAccess(other) -> 403', throwsHttp(fn() => Auth::assertStudentAccess([$sOther]), 403));
    check('assertStudentAccess(mixed batch) -> 403', throwsHttp(fn() => Auth::assertStudentAccess([$sOwn, $sOther]), 403));

    // Data-layer RLS: rows() must ignore a cross-center scope for center users.
    $leak = ReportSheetService::rows(['session' => Auth::activeSession(), 'center_id' => $otherCenter]);
    $allOwn = true;
    foreach ($leak as $r) {
        if ((int) ($r['center_id'] ?? 0) !== $ownCenter) {
            $allOwn = false;
            break;
        }
    }
    check('ReportSheetService::rows(center=45) returns only center-3 rows', $allOwn && count($leak) > 0, 'count=' . count($leak));

    // Admin bypass.
    $adminRow = Database::selectOne("SELECT `id` FROM `users` WHERE `email` = ? LIMIT 1", [test_admin_email()]);
    if ($adminRow === null) {
        echo "SKIP  admin bypass (no admin fixture)\n";
    } else {
        setAuthUser((int) $adminRow['id']);
        check('admin has no center scope', Auth::centerId() === null);
        check('admin assertCenterAccess(45) passes', !throwsHttp(fn() => Auth::assertCenterAccess($otherCenter), 403));
        check('admin assertInstituteAccess(467) passes', !throwsHttp(fn() => Auth::assertInstituteAccess(467), 403));
        check('admin assertStudentAccess(other) passes', !throwsHttp(fn() => Auth::assertStudentAccess([$sOther]), 403));

        $adminHash = Database::selectOne("SELECT `password` FROM `users` WHERE `email` = ? LIMIT 1", [test_admin_email()]);
        check('admin hash verifies the configured test password', $adminHash !== null && password_verify(test_admin_password(), $adminHash['password']));
    }
    setAuthUser(null);
}

// ===============================================================
// Part C — HTTP (two-step MFA, anonymous 401, admin smoke)
// ===============================================================

echo "\n== Part C: HTTP (MFA + smoke) ==\n";

function httpUp(string $base): bool
{
    $ctx = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
    $body = @file_get_contents($base . '/login', false, $ctx);
    return is_string($body) && str_contains($body, 'login-form');
}

function httpReq(string $method, string $url, array $opts = []): array
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
        CURLOPT_TIMEOUT => 120,
        CURLOPT_CUSTOMREQUEST => $method,
    ];
    if (array_key_exists('body', $opts)) {
        $cfg[CURLOPT_POSTFIELDS] = is_array($opts['body']) ? json_encode($opts['body']) : $opts['body'];
        $headers[] = 'Content-Type: application/json';
    }
    $headers[] = 'X-Requested-With: XMLHttpRequest';
    $headers[] = 'Accept: application/json';
    $cfg[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($ch, $cfg);
    $raw = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $hsize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    return [$status, substr($raw, 0, $hsize), substr($raw, $hsize)];
}

/** Fetch a page and pull its <meta name="csrf-token"> (fresh token per session). */
function fetchCsrf(string $base, string $path = '/login'): string
{
    [, , $body] = httpReq('GET', $base . $path);
    return preg_match('/name="csrf-token" content="([a-f0-9]+)"/', (string) $body, $m) ? $m[1] : '';
}

function currentCode(string $secret): string
{
    $key = callPrivate(Totp::class, 'base32Decode', [$secret]);
    $code = callPrivate(Totp::class, 'hotp', [$key, (int) floor(time() / 30), 6]);
    return str_pad((string) $code, 6, '0', STR_PAD_LEFT);
}

if (!httpUp($base)) {
    echo "SKIP  Part C (no server at $base)\n";
} else {
    // Reset this IP's counters so repeated runs stay deterministic (local only).
    $rlDir = BASE_PATH . '/storage/cache/ratelimit';
    foreach ((array) glob($rlDir . '/*.json') as $f) {
        @unlink($f);
    }

    $cookieFile = tempnam(sys_get_temp_dir(), 'bk_sec');
    $tmpEmail = 'sec.test.' . bin2hex(random_bytes(4)) . '@example.test';
    $tmpPass = 'Tmp!' . bin2hex(random_bytes(6));
    $uid = User::create([
        'name' => 'Security Test',
        'email' => $tmpEmail,
        'password' => password_hash($tmpPass, PASSWORD_DEFAULT),
        'type' => 0,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    try {
        // 1. First login (no MFA yet) -> classic envelope.
        $csrf = fetchCsrf($base);
        [$st, , $body] = httpReq('POST', "$base/api/login", [
            'body' => ['email' => $tmpEmail, 'password' => $tmpPass],
            'headers' => ["X-CSRF-TOKEN: $csrf"],
        ]);
        $j = json_decode((string) $body, true);
        check('temp login: classic envelope', $st === 200
            && ($j['success'] ?? false) === true
            && isset($j['data']['token'], $j['data']['name'])
            && ($j['message'] ?? '') === 'User login successfully.', "status=$st body=$body");

        // 2. Setup 2FA (GET, no CSRF needed) + enable (POST, fresh token from dashboard).
        [$st, , $body] = httpReq('GET', "$base/api/mfa/setup");
        $j = json_decode((string) $body, true);
        $secret = (string) ($j['data']['secret'] ?? '');
        check('mfa setup returns a secret + otpauth', $st === 200 && $secret !== '' && !empty($j['data']['otpauth']), "status=$st");

        $csrf = fetchCsrf($base, '/');
        [$st, , $body] = httpReq('POST', "$base/api/mfa/enable", [
            'body' => ['code' => currentCode($secret)],
            'headers' => ["X-CSRF-TOKEN: $csrf"],
        ]);
        check('mfa enable succeeds', $st === 200, "status=$st body=$body");

        // 3. Logout, then re-login -> should ask for MFA.
        [$st] = httpReq('POST', "$base/api/logout", ['body' => [], 'headers' => ["X-CSRF-TOKEN: $csrf"]]);
        check('logout succeeds', $st === 200, "status=$st");

        $csrf = fetchCsrf($base);
        [$st, , $body] = httpReq('POST', "$base/api/login", [
            'body' => ['email' => $tmpEmail, 'password' => $tmpPass],
            'headers' => ["X-CSRF-TOKEN: $csrf"],
        ]);
        $j = json_decode((string) $body, true);
        $mfaToken = (string) ($j['data']['mfa_token'] ?? '');
        check('login now returns mfa_required + mfa_token', $st === 200
            && ($j['data']['mfa_required'] ?? false) === true
            && $mfaToken !== '', "status=$st body=$body");

        // 4. Wrong code -> 422; correct code -> session.
        [$st, , $body] = httpReq('POST', "$base/api/login/mfa", [
            'body' => ['mfa_token' => $mfaToken, 'code' => currentCode($secret) === '000000' ? '111111' : '000000'],
            'headers' => ["X-CSRF-TOKEN: $csrf"],
        ]);
        check('MFA wrong code -> 422', $st === 422, "status=$st body=$body");

        [$st, , $body] = httpReq('POST', "$base/api/login/mfa", [
            'body' => ['mfa_token' => $mfaToken, 'code' => currentCode($secret)],
            'headers' => ["X-CSRF-TOKEN: $csrf"],
        ]);
        $j = json_decode((string) $body, true);
        check('MFA correct code completes login', $st === 200
            && ($j['success'] ?? false) === true
            && isset($j['data']['token']), "status=$st body=$body");

        // 5. Disable 2FA again (authenticated).
        $csrf = fetchCsrf($base, '/');
        [$st, , $body] = httpReq('POST', "$base/api/mfa/disable", [
            'body' => ['code' => currentCode($secret)],
            'headers' => ["X-CSRF-TOKEN: $csrf"],
        ]);
        check('mfa disable succeeds', $st === 200, "status=$st body=$body");
    } finally {
        // Always remove the throwaway user.
        Database::delete('users', 'id = ?', [$uid]);
        @unlink($cookieFile);
    }

    // 6. Anonymous access to a guarded report endpoint -> 401.
    $ch = curl_init("$base/api/centerwisedetails/45");
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30]);
    $anonBody = (string) curl_exec($ch);
    $anonStatus = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    check('anonymous report access -> 401', $anonStatus === 401, "status=$anonStatus body=$anonBody");

    // 6b. Same-origin (CORS) guard on state-changing requests.
    $originPost = function (string $url, ?string $origin): int {
        $ch = curl_init($url);
        $h = ['Content-Type: application/json', 'Accept: application/json', 'X-Requested-With: XMLHttpRequest'];
        if ($origin !== null) {
            $h[] = 'Origin: ' . $origin;
        }
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POSTFIELDS => '{}', CURLOPT_HTTPHEADER => $h]);
        curl_exec($ch);
        return (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    };
    check('foreign Origin POST -> 403 (CORS blocked)', $originPost("$base/api/login", 'https://evil.example') === 403);
    check('same-origin POST passes origin guard -> 419 (CSRF)', $originPost("$base/api/login", $base) === 419);
    check('no Origin (non-browser) -> 419 (CSRF)', $originPost("$base/api/login", null) === 419);

    // 7. Admin smoke: RLS guards must not block an admin.
    $cookieFile = tempnam(sys_get_temp_dir(), 'bk_sec_admin');
    try {
        $csrf = fetchCsrf($base);
        [$st, , $body] = httpReq('POST', "$base/api/login", [
            'body' => test_login_array(),
            'headers' => ["X-CSRF-TOKEN: $csrf"],
        ]);
        check('admin login ok', $st === 200, "status=$st body=$body");
        foreach (['/api/centerinfo/0', '/api/AllCenterDetailsInformationReport/0', '/api/TotalInstituteInformation/0'] as $path) {
            [$st2, $head2, $body2] = httpReq('GET', $base . $path);
            $ok = $st2 === 200 && str_contains($head2, 'text/html') && str_contains((string) $body2, 'BANGLADESH KINDERGARTEN ASSOCIATION');
            check("admin GET $path -> 200 sheet", $ok, "status=$st2 len=" . strlen((string) $body2));
        }
    } finally {
        @unlink($cookieFile);
    }
}

echo "\n" . ($failures === 0 ? 'All security hardening tests passed.' : "$failures security test(s) FAILED.") . "\n";
exit($failures === 0 ? 0 : 1);
