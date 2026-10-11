<?php
/** Authenticated sidebar link check: prints status for every sidebar path. */
require __DIR__ . '/_test_credentials.php';

$base = 'http://127.0.0.1:8090';
$paths = ['/', '/student-registration', '/students', '/institute-registration', '/institutes',
    '/centers', '/divisions', '/districts', '/upazilas', '/unions', '/sms', '/markentry', '/gradeassign',
    '/admitcards', '/seatplan', '/student-certificate', '/institute-certificate', '/reports',
    '/notices', '/user', '/routines', '/sessions', '/userrole', '/settings', '/mark-distributions',
    '/role-place', '/instructions', '/passchange'];

$cookie = tempnam(sys_get_temp_dir(), 'ck');

function req(string $method, string $url, array $post = [], string $cookie = ''): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $cookie, CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_HEADER => false, CURLOPT_TIMEOUT => 20,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [(int) $code, (string) $body];
}

// login (same cookie jar for GET token + POST login so the CSRF token matches the session)
[$s, $loginPage] = req('GET', "$base/login", [], $cookie);
preg_match('/name="_token" value="([^"]+)"/', $loginPage, $m);
$token = $m[1] ?? '';
[$s] = req('POST', "$base/api/login",
    test_login_array() + ['_token' => $token, 'remember' => ''], $cookie);

$ok = 0; $bad = [];
foreach ($paths as $p) {
    $ch = curl_init($base . $p);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_NOBODY => false,
        CURLOPT_COOKIEFILE => $cookie, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 20]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $marker = '';
    if ($code === 200) {
        $marker = str_contains((string) $body, 'Page not found') ? ' (404-BODY!)' : '';
    }
    $flag = ($code === 200 && $marker === '') ? 'OK  ' : 'FAIL';
    if ($flag === 'OK  ') { $ok++; } else { $bad[] = "$p -> $code$marker"; }
    printf("%s %-28s %d%s\n", $flag, $p, $code, $marker);
}
echo "\n$ok/" . count($paths) . " pages OK\n";
if ($bad) { echo "Failing:\n  " . implode("\n  ", $bad) . "\n"; }
@unlink($cookie);
