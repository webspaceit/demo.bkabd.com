<?php
/**
 * Public scholarship result search test.
 *
 * Registration is unique -> searching by Registration No alone must work.
 * The (session, class, roll) fallback must still work. Captcha, validation
 * and not-found paths behave as before.
 *
 * Usage: php tools/test_result_search.php [base_url]
 */
require __DIR__ . '/_test_credentials.php';

use App\Core\Database;

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8090', '/');
$jar = tempnam(sys_get_temp_dir(), 'bk_res');
$failures = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $failures;
    echo ($ok ? 'PASS' : 'FAIL') . "  $label" . ($ok ? '' : "  -- $detail") . "\n";
    if (!$ok) {
        $failures++;
    }
}

function http(string $method, string $url, ?array $form = null): array
{
    global $jar;
    $ch = curl_init($url);
    $c = [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 30, CURLOPT_CUSTOMREQUEST => $method];
    if ($form !== null) {
        $c[CURLOPT_POSTFIELDS] = http_build_query($form);
    }
    curl_setopt_array($ch, $c);
    $raw = (string) curl_exec($ch);
    $st = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    return [$st, substr($raw, 0, $hs), substr($raw, $hs)];
}

/** GET a fresh form -> [csrf, captchaAnswer, html]. */
function freshForm(string $base): array
{
    [, , $html] = http('GET', "$base/scholarship-result");
    preg_match('/name="_token" value="([a-f0-9]+)"/', $html, $m);
    $csrf = $m[1] ?? '';
    $pos = strpos($html, 'Security check');
    $seg = $pos !== false ? substr($html, $pos, 500) : $html;
    preg_match('/(\d+)\s*\+\s*(\d+)/', $seg, $m2);
    $ans = isset($m2[1], $m2[2]) ? ((int) $m2[1] + (int) $m2[2]) : -1;
    return [$csrf, $ans, $html];
}

// A sample published student (session 2025).
$s = Database::selectOne(
    "SELECT `session`, `class`, `roll`, `registration` FROM `students`
      WHERE `session` = 2025 AND `registration` <> '' ORDER BY `id` LIMIT 1"
);
check('sample student found', is_array($s), 'no student for session 2025');
$reg = (string) ($s['registration'] ?? '');
$cls = (string) ($s['class'] ?? '');
$roll = (string) ($s['roll'] ?? '');

// --- form shape -----------------------------------------------------------
[$csrf, $ans, $html] = freshForm($base);
check('form has a single Registration No field', str_contains($html, 'id="registration"'));
check('form keeps the exam-detail fallback', str_contains($html, 'search by exam details'));
check('form no longer asks for center/division', !str_contains($html, 'name="center_id"') && !str_contains($html, 'name="division_id"'), 'old fields still present');

// --- search by registration ----------------------------------------------
[$csrf, $ans] = freshForm($base);
[$st, , $body] = http('POST', "$base/result/search", ['_token' => $csrf, 'registration' => $reg, 'captcha' => $ans]);
check('registration-only search renders the result sheet', $st === 200 && str_contains($body, 'SCHOLARSHIP RESULT'), "status=$st");
check('result sheet shows the searched registration', str_contains($body, $reg));

// --- fallback: session + class + roll ------------------------------------
[$csrf, $ans] = freshForm($base);
[$st, , $body] = http('POST', "$base/result/search", [
    '_token' => $csrf, 'session_id' => '2025', 'class' => $cls, 'roll' => $roll, 'captcha' => $ans,
]);
check('session/class/roll fallback renders the result sheet', $st === 200 && str_contains($body, 'SCHOLARSHIP RESULT'), "status=$st");

// --- wrong captcha -> redirect back --------------------------------------
[$csrf, $ans] = freshForm($base);
[$st] = http('POST', "$base/result/search", ['_token' => $csrf, 'registration' => $reg, 'captcha' => $ans + 1]);
check('wrong captcha -> 302 back to form', $st === 302, "status=$st");

// --- neither registration nor exam details -> validation redirect --------
[$csrf, $ans] = freshForm($base);
[$st] = http('POST', "$base/result/search", ['_token' => $csrf, 'registration' => '', 'captcha' => $ans]);
check('no registration and no exam details -> 302', $st === 302, "status=$st");

// --- unknown registration -> not found redirect --------------------------
[$csrf, $ans] = freshForm($base);
[$st] = http('POST', "$base/result/search", ['_token' => $csrf, 'registration' => '9999999999999', 'captcha' => $ans]);
check('unknown registration -> 302 not found', $st === 302, "status=$st");

@unlink($jar);
echo "\n" . ($failures === 0 ? 'All result-search tests passed.' : "$failures result-search test(s) FAILED.") . "\n";
exit($failures === 0 ? 0 : 1);
