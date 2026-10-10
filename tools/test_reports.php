<?php
/**
 * Report endpoints test — exercises all 26 report sheets from the original
 * routes/api.php and checks the output (printable HTML sheet / XLSX download)
 * or the no-data page.
 *
 * Usage: php tools/test_reports.php [base_url]
 */

require __DIR__ . '/_test_credentials.php';

$base = $argv[1] ?? 'http://127.0.0.1:8090';
$cookieFile = tempnam(sys_get_temp_dir(), 'bk_reports');
$failures = 0;

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
        CURLOPT_TIMEOUT => 180,
        CURLOPT_CUSTOMREQUEST => $method,
    ];
    if (isset($opts['body'])) {
        $opts2[CURLOPT_POSTFIELDS] = $opts['body'];
        $headers[] = 'Content-Type: application/json';
    }
    $headers[] = 'X-Requested-With: XMLHttpRequest';
    $headers[] = 'Accept: application/json';
    $opts2[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($ch, $opts2);
    $raw = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $head = substr($raw, 0, $headerSize);
    return [$status, $head, substr($raw, $headerSize)];
}

function check(string $label, bool $ok, string $detail = ''): void
{
    global $failures;
    echo ($ok ? 'PASS' : 'FAIL') . "  $label" . ($ok ? '' : "  -- $detail") . "\n";
    if (!$ok) {
        $failures++;
    }
}

function expectSheet(string $label, string $url): void
{
    [$st, $head, $body] = req('GET', $url);
    $isHtml = str_contains($head, 'text/html') && str_contains($body, 'BANGLADESH KINDERGARTEN ASSOCIATION');
    check($label, $st === 200 && $isHtml, "status=$st len=" . strlen($body));
}

function expectXlsx(string $label, string $url): void
{
    [$st, $head, $body] = req('GET', $url);
    $isZip = str_starts_with($body, "PK\x03\x04");
    check($label, $st === 200 && $isZip, "status=$st sheet=" . (str_contains($head, 'spreadsheetml') ? 'xlsx' : 'other') . " len=" . strlen($body));
}

// 1. Login
[$st, , $body] = req('GET', "$base/login");
preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $body, $m);
$csrf = $m[1] ?? '';
check('CSRF token present', $csrf !== '', 'no token');
[$st] = req('POST', "$base/api/login", [
    'body' => test_login_json(),
    'headers' => ["X-CSRF-TOKEN: $csrf"],
]);
check('POST /api/login ok', $st === 200, "status=$st");

// Report hub page.
[$st, , $hub] = req('GET', "$base/reports");
check('GET /reports hub', $st === 200 && str_contains($hub, 'Center Reports') && str_contains($hub, '/api/centerwisedetails/{center}'), "status=$st");

// Anonymous request must be rejected.
$anon = tempnam(sys_get_temp_dir(), 'bk_anon');
$ch = curl_init("$base/api/centerwisedetails/45");
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]);
$anonBody = (string) curl_exec($ch);
$anonStatus = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);
@unlink($anon);
check('anonymous report request -> 401', $anonStatus === 401, "status=$anonStatus");

// Use session 2025 (row id 3) — it has grade/merit data for the result sheets.
[$st] = req('GET', "$base/api/updateSession/3");
check('switch to session 2025', $st === 200, "status=$st");

$center = 45;       // JHENAIDAH — 1816 students in 2025
$inst = 2470;       // YWCA JUNIOR GIRL'S HIGH SCHOOL
$instCenter = 181;

// 2. Excel exports
expectXlsx('GET /api/institutestudentdetailsexport/{id}', "$base/api/institutestudentdetailsexport/$inst");
expectXlsx('GET /api/centerstudentdetailsexport/{id}', "$base/api/centerstudentdetailsexport/$center");
expectXlsx('GET /api/centerstudentdetailsexportwithoutimage/{id}', "$base/api/centerstudentdetailsexportwithoutimage/$center");

// 3. Center info
expectSheet('GET /api/centerinfo/{id}', "$base/api/centerinfo/$center");
expectSheet('GET /api/centerinfo/0', "$base/api/centerinfo/0");

// 4. Student detail sheets
expectSheet('GET /api/centerwisedetails/{id}', "$base/api/centerwisedetails/$center");
expectSheet('GET /api/centerwisedetailswithoutimage/{id}', "$base/api/centerwisedetailswithoutimage/$center");
expectSheet('GET /api/institutewisedetails/{id}', "$base/api/institutewisedetails/$inst");

// 5. Attendance sheets
expectSheet('GET /api/ClassWiseExamAttendSheetReport/{id}', "$base/api/ClassWiseExamAttendSheetReport/$center");
expectSheet('GET /api/ClassWiseExamAttendSheetReportWithPhoto/{id}', "$base/api/ClassWiseExamAttendSheetReportWithPhoto/$center");
expectSheet('GET /api/ClassWiseExamAttendSheetReport2/{id}', "$base/api/ClassWiseExamAttendSheetReport2/$center");
expectSheet('GET /api/ClassWiseExamAttendSheetReportWithPhoto2/{id}', "$base/api/ClassWiseExamAttendSheetReportWithPhoto2/$center");

// 6. Institute membership / information
expectSheet('GET /api/TotalInstituteInformation/0', "$base/api/TotalInstituteInformation/0");
expectSheet('GET /api/TotalInstituteInformation/{id}', "$base/api/TotalInstituteInformation/$center");
expectSheet('GET /api/LifeTimeMembershipInstituteReport/0', "$base/api/LifeTimeMembershipInstituteReport/0");
expectSheet('GET /api/TemporaryMembershipInstituteReport/0', "$base/api/TemporaryMembershipInstituteReport/0");

// 7. Aggregate reports
expectSheet('GET /api/DocumentsDistributionReport/0', "$base/api/DocumentsDistributionReport/0");
expectSheet('GET /api/EducationalTypeStudentCountReport/0', "$base/api/EducationalTypeStudentCountReport/0");
expectSheet('GET /api/AllCenterDetailsInformationReport/0', "$base/api/AllCenterDetailsInformationReport/0");

// 8. Marks reports
expectSheet('GET /api/CenterWiseStudentDetailsReportIssuedAdmitCard/{id}', "$base/api/CenterWiseStudentDetailsReportIssuedAdmitCard/$center");
expectSheet('GET /api/CenterWiseHighestMarksReport/{id}', "$base/api/CenterWiseHighestMarksReport/$center");
expectSheet('GET /api/InstituteWiseHighestMarksReport/{id}/{inst}', "$base/api/InstituteWiseHighestMarksReport/$instCenter/$inst");
expectSheet('GET /api/InstituteWiseHighestMarksReport/{id}/0', "$base/api/InstituteWiseHighestMarksReport/$instCenter/0");
expectSheet('GET /api/CenterWiseTabulationSheet/{id}', "$base/api/CenterWiseTabulationSheet/$center");
expectSheet('GET /api/CenterWiseGradeSheetCountReport/{id}', "$base/api/CenterWiseGradeSheetCountReport/$center");
expectSheet('GET /api/CenterWiseGradeListMeritPositionCountReport/0', "$base/api/CenterWiseGradeListMeritPositionCountReport/0");
expectSheet('GET /api/CenterWiseGradeSheetResultDetails/{id}', "$base/api/CenterWiseGradeSheetResultDetails/$center");
expectSheet('GET /api/InstituteWiseGradeSheetResultDetails/{id}/{inst}', "$base/api/InstituteWiseGradeSheetResultDetails/$instCenter/$inst");
expectSheet('GET /api/MeritListResultwithPosition/{id}', "$base/api/MeritListResultwithPosition/$center");

// 9. No-data path (unknown center) -> self-closing page, HTTP 404
[$st, , $body] = req('GET', "$base/api/centerwisedetails/99999999");
check('unknown center -> no-data page', $st === 404 && str_contains($body, 'No Data Found'), "status=$st");

// 10. Restore the default active session (2026, row id 4)
[$st] = req('GET', "$base/api/updateSession/4");
check('restore session 2026', $st === 200, "status=$st");

@unlink($cookieFile);
echo "\n" . ($failures === 0 ? 'All report tests passed.' : "$failures report test(s) FAILED.") . "\n";
exit($failures === 0 ? 0 : 1);
