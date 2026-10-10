<?php
/**
 * Benchmark the PUBLIC result-search path — the hot path for result day
 * (potentially ~10k concurrent lookups).
 *
 * Measures:
 *   1. EXPLAIN plan for the actual find-student JOIN query
 *   2. Raw DB query latency  (prepared statement, 50 runs)
 *   3. Service layer latency: cold (DB, cache empty) vs warm (memo) ×20
 *   4. gate() + fullMarks() — the two other per-search queries
 *   5. Full HTTP round trip: GET form (+captcha) → POST /result/search,
 *      average of 3 runs (wall-clock through the web server)
 *
 * Usage: php tools/bench_result.php [httpBase] [iterations]
 * Default httpBase = http://127.0.0.1:8090
 */
require __DIR__ . '/../bootstrap/app.php';

use App\Core\Cache;
use App\Core\Config;
use App\Core\Database;
use App\Services\ResultPortalService;

$base = $argv[1] ?? 'http://127.0.0.1:8090';
$N = isset($argv[2]) ? max(5, (int) $argv[2]) : 50;

// ---- fixture: a real published-student (session 2025, center 45, div 3) ----
$P = [
    'session_id' => '2025',
    'division_id' => '3',
    'center_id' => '45',
    'class' => '6',
    'registration' => '2504504806067',
    'roll' => '67216',
];

$SQL = 'SELECT s.`session`, s.`roll`, s.`registration`, s.`class`, s.`name`, '
    . 's.`father_name`, s.`mother_name`, s.`dob`, '
    . 's.`bangla`, s.`english`, s.`math`, s.`total`, s.`percentage`, s.`grade`, s.`ml`, '
    . 'd.`name` AS division_name, dt.`name` AS district_name, uz.`name` AS upazila_name, '
    . 'cu.`name` AS center_name, i.`name` AS institute_name '
    . 'FROM `students` s '
    . 'LEFT JOIN `divisions` d ON d.`id` = s.`division_id` '
    . 'LEFT JOIN `districts` dt ON dt.`id` = s.`district_id` '
    . 'LEFT JOIN `upozilas` uz ON uz.`id` = s.`upazila_id` '
    . 'LEFT JOIN `centers` c ON c.`id` = s.`center_id` '
    . 'LEFT JOIN `users` cu ON cu.`id` = c.`user_id` '
    . 'LEFT JOIN `institutes` i ON i.`id` = s.`institute_id` '
    . 'WHERE s.`session` = ? AND s.`center_id` = ? AND s.`division_id` = ? '
    . 'AND s.`class` = ? AND s.`registration` = ? AND s.`roll` = ? '
    . 'LIMIT 1';
$Q = [$P['session_id'], $P['center_id'], $P['division_id'], $P['class'], $P['registration'], $P['roll']];

function t_avg(array $ms): string
{
    $n = count($ms);
    $avg = array_sum($ms) / $n;
    $min = min($ms);
    $max = max($ms);
    return sprintf('avg %.2f ms  (min %.2f / max %.2f)  over %d runs', $avg, $min, $max, $n);
}

function hrt(): float
{
    return hrtime(true) / 1e6;
}

echo "── Environment ─────────────────────────────────────────────\n";
echo 'PHP ' . PHP_VERSION . '  |  ' . php_sapi_name() . "\n";
echo 'MySQL ' . Database::pdo()->query('SELECT VERSION()')->fetchColumn() . "\n";
$studentsTotal = (int) Database::pdo()->query('SELECT COUNT(*) FROM students')->fetchColumn();
echo "students rows: {$studentsTotal}\n";
echo 'cache driver: ' . Config::get('CACHE_DRIVER', 'file') . ($function_exists = function_exists('apcu_fetch') && extension_loaded('apcu') ? ' (apcu loaded)' : ' (apcu unavailable)') . "\n";
echo "fixture: session={$P['session_id']} center={$P['center_id']} class={$P['class']} roll={$P['roll']} reg={$P['registration']}\n\n";

echo "── 1. EXPLAIN (find-student query) ─────────────────────────\n";
foreach (Database::select('EXPLAIN ' . $SQL, $Q) as $row) {
    printf("  table=%s  key=%s  rows=%s  extra=%s\n",
        $row['table'] ?? '?', $row['key'] ?? 'NULL', $row['rows'] ?? '?', $row['Extra'] ?? '');
}
echo "\n";

echo "── 2. Raw DB query latency (prepared statement) ─────────────\n";
$stmt = Database::pdo()->prepare($SQL);
for ($i = 0; $i < 3; $i++) { $stmt->execute($Q); }         // warm-up
$dbTimes = [];
for ($i = 0; $i < $N; $i++) {
    $t0 = hrt();
    $stmt->execute($Q);
    $stmt->fetchAll();
    $dbTimes[] = hrt() - $t0;
}
echo '  ' . t_avg($dbTimes) . "\n\n";

echo "── 3. Service layer — cold (DB, cache misses) vs warm (memo) ─\n";
$key = 'result_student:' . sha1(implode('|', [$P['session_id'], $P['division_id'], $P['center_id'], $P['class'], $P['registration'], $P['roll']]));
Cache::forget($key);
$cold = [];
for ($i = 0; $i < $N; $i++) {
    Cache::forget($key);                                   // simulate a fresh request
    $t0 = hrt();
    ResultPortalService::findStudent($P);
    $cold[] = hrt() - $t0;
}
echo '  cold  ' . t_avg($cold) . "\n";
$warm = [];
for ($i = 0; $i < $N; $i++) {
    $t0 = hrt();
    ResultPortalService::findStudent($P);                  // per-request memo hit
    $warm[] = hrt() - $t0;
}
echo '  warm  ' . t_avg($warm) . "\n\n";

echo "── 4. Other per-search queries ─────────────────────────────\n";
$gateTimes = [];
for ($i = 0; $i < $N; $i++) {
    $t0 = hrt();
    ResultPortalService::gate($P['session_id']);
    $gateTimes[] = hrt() - $t0;
}
echo '  gate()      ' . t_avg($gateTimes) . "\n";
Cache::forget('result_fullmarks:2025');
$fmTimes = [];
for ($i = 0; $i < 20; $i++) {
    Cache::forget('result_fullmarks:2025');
    $t0 = hrt();
    ResultPortalService::fullMarks('2025');
    $fmTimes[] = hrt() - $t0;
}
echo '  fullMarks() ' . t_avg($fmTimes) . " (cold DB)\n";
$fmWarm = [];
for ($i = 0; $i < 20; $i++) {
    $t0 = hrt();
    ResultPortalService::fullMarks('2025');
    $fmWarm[] = hrt() - $t0;
}
echo '  fullMarks() ' . t_avg($fmWarm) . " (warm memo)\n\n";

echo "── 5. Full HTTP round trip (GET form + captcha, POST search) ─\n";
if (!function_exists('curl_init')) {
    echo "  (php curl ext not available — skipping HTTP timing)\n";
} else {
    $httpTimes = [];
    $httpGetTimes = [];
    for ($run = 1; $run <= 3; $run++) {
        $jar = tempnam(sys_get_temp_dir(), 'bkjar');
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $base . '/scholarship-result',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEJAR => $jar,
            CURLOPT_COOKIEFILE => $jar,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 15,
        ]);
        $t0 = hrt();
        $html = curl_exec($ch);
        $httpGetTimes[] = hrt() - $t0;
        $getSt = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if (!preg_match('/Calculate:\s*(\d+)\+(\d+)\s*=/', $html, $m)
            || !preg_match('/name="_token"\s+value="([^"]+)"/', $html, $mToken)) {
            echo "  run {$run}: could not parse form (status {$getSt}) — skipping HTTP loop\n";
            curl_close($ch);
            @unlink($jar);
            break;
        }
        $answer = (int) $m[1] + (int) $m[2];
        $t0 = hrt();
        curl_setopt_array($ch, [
            CURLOPT_URL => $base . '/result/search',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                '_token' => $mToken[1],
                'session_id' => $P['session_id'],
                'division_id' => $P['division_id'],
                'center_id' => $P['center_id'],
                'class' => $P['class'],
                'registration' => $P['registration'],
                'roll' => $P['roll'],
                'captcha' => $answer,
            ]),
        ]);
        $body = curl_exec($ch);
        $elapsed = hrt() - $t0;
        $st = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $httpTimes[] = $elapsed;
        $kind = $st === 200 ? (str_contains($body, 'Scholarship Result') ? 'result sheet 200' : 'page 200') : "http {$st}";
        echo sprintf("  run %d: GET form %.2f ms | POST /result/search → %s  %.2f ms\n", $run, $httpGetTimes[$run - 1], $kind, $elapsed);
        curl_close($ch);
        @unlink($jar);
        usleep(500000); // mild spacing, stay under the 30/min per-IP throttle
    }
    if ($httpTimes) {
        echo '  POST avg  ' . t_avg($httpTimes) . " (server wall time incl. session + captcha + queries)\n";
        echo '  GET  avg  ' . t_avg($httpGetTimes) . " (form page incl. 3 cached dropdown queries + captcha)\n";
    }
}
echo "\nDone.\n";