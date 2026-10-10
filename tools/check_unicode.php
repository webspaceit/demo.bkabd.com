<?php
/**
 * Round-trip a Bangla string through POST /api/notices and compare the stored
 * bytes with the expected UTF-8. Removes the test notice afterwards.
 */
require __DIR__ . '/../bootstrap/app.php';

$base = 'http://127.0.0.1:8090';
$jar = tempnam(sys_get_temp_dir(), 'bk_uni');
$expected = "\u{0986}\u{099C}\u{0995}\u{09C7}\u{09B0} \u{09AC}\u{09BF}\u{099C}\u{09CD}\u{099E}\u{09AA}\u{09CD}\u{09A4}\u{09BF}";

function call(string $method, string $url, string $jar, ?array $json = null, array $headers = []): array
{
    $ch = curl_init($url);
    $h = array_merge(['X-Requested-With: XMLHttpRequest', 'Accept: application/json'], $headers);
    $opts = [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $h, CURLOPT_TIMEOUT => 15,
    ];
    if ($json !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($json, JSON_UNESCAPED_UNICODE);
        $opts[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json; charset=utf-8';
    }
    curl_setopt_array($ch, $opts);
    $body = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $body];
}

$page = call('GET', "$base/login", $jar);
preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $page[1], $m);
$csrf = $m[1] ?? '';
call('POST', "$base/api/login", $jar, ['email' => 'mehedi@gmail.com', 'password' => 'admin123'], ["X-CSRF-TOKEN: $csrf"]);

[$code, $body] = call('POST', "$base/api/notices", $jar,
    ['center_id' => 0, 'title' => $expected, 'description' => 'unicode check'], ["X-CSRF-TOKEN: $csrf"]);
echo "POST notices -> $code\n";

$pdo = App\Core\Database::pdo();
$row = $pdo->query("SELECT id, title FROM notices WHERE description = 'unicode check' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    echo "no row stored\n";
    exit(1);
}
echo 'stored bytes match UTF-8: ' . ($row['title'] === $expected ? 'YES' : 'NO') . "\n";
echo 'stored hex: ' . bin2hex((string) $row['title']) . "\n";
echo 'expected hex: ' . bin2hex($expected) . "\n";

call('DELETE', "$base/api/notices/" . (int) $row['id'], $jar, null, ["X-CSRF-TOKEN: $csrf"]);
@unlink($jar);
