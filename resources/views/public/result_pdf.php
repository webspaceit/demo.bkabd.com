<?php
/**
 * Scholarship result sheet rendered as a real PDF (dompdf).
 *
 * Plain CSS only — dompdf does not run Tailwind — and image URLs are resolved
 * to filesystem paths inside public/ (dompdf's chroot) so the logo and the
 * uploaded signatures embed correctly.
 *
 * @var array      $student    joined row (ResultPortalService::findStudent)
 * @var array|null $fullMarks  session full-marks config (bangla/english/math/total)
 * @var string     $resultTime sessions.result_time of the published result
 */
$grades = [1 => 'Talent Pool', 2 => 'General Grade', 3 => 'School Grade', 4 => 'AB'];
$mls = [1 => '1st', 2 => '2nd', 3 => '3rd'];
$gradeText = ($grades[(int) $student['grade']] ?? '')
    . ((int) $student['ml'] !== 0 ? '(' . ($mls[(int) $student['ml']] ?? '') . ')' : '');

$pubDate = '';
if ($resultTime) {
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', (string) $resultTime);
    $pubDate = $dt ? $dt->format('d F Y') : '';
}
$fm = fn(string $subject): string => (string) (($fullMarks ?? [])[$subject] ?? '-');

// Public URL -> filesystem path (dompdf reads local files inside its chroot).
$img = static function (string $url): string {
    // Signatures carry a ?v= cache-buster; strip it to find the file on disk.
    $rel = strtok($url, '?');
    $path = BASE_PATH . '/public/' . ltrim((string) $rel, '/');
    return is_file($path) ? $path : $url;
};

// Pre-faded logo watermark, as a forward-slash path (CSS url() treats "\" as an escape).
$wmUrl = str_replace('\\', '/', $img('/assets/img/result/water-mark.webp'));
$patUrl = str_replace('\\', '/', $img('/assets/img/result/pattern.png'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
    /* Always render on A4, portrait. */
    @page { size: A4 portrait; margin: 12mm; }
    body { font-family: "DejaVu Sans", sans-serif; color: #000; font-size: 11px; }
    /* Double-ruled frame: 2px line, 6.6px gap, 2px line (matches the on-screen sheet).
       The user-supplied repeating pattern fills the sheet; the watermark logo is
       painted on top of it (see .inner) so neither hides the other. */
    .sheet { border: 2px solid #000; padding: 6.6px;
             background-image: url("<?= e($patUrl) ?>");
             background-repeat: repeat;
             background-position: 0 0;
             background-size: 424px 424px; }
    .inner { border: 2px solid #000; padding: 10px;
             background-image: url("<?= e($wmUrl) ?>");
             background-repeat: no-repeat;
             background-position: center center;
             background-size: 400px 400px; }
    .center { text-align: center; }
    .logo { width: 68px; }
    h1 { font-size: 24px; margin: 6px 0 2px; }
    .regno { font-size: 14.4px; font-weight: bold; margin-bottom: 4px; }
    .contact { font-size: 11.4px; line-height: 1.5; margin: 4px 0; }
    /* Green divider under the address block (matches the on-screen sheet). */
    .rule { height: 1px; background: #000; margin: 10px 0 4px; font-size: 0; line-height: 0; }
    h2 { font-size: 19.2px; margin: 8px 0 10px; }
    .bar { background: #00A550; color: #fff; font-weight: bold; font-size: 14.4px;
           text-align: center; padding: 5px 12px; margin: 12px auto; width: 45%;
           border-radius: 999px; }
    table { width: 100%; border-collapse: collapse; }
    table.details th, table.details td { border: 1px solid #000; padding: 4px; font-size: 11px; }
    table.details th { width: 20%; text-align: left; background: #EEF7EA; }
    table.marks th, table.marks td { border: 1px solid #000; padding: 5px; font-size: 11px; text-align: center; }
    table.marks th { background: #00A550; color: #fff; }
    /* Columns 5-7 (Total / Percentage / Grade) share the details-table tint. */
    table.marks td.fill { background: #EEF7EA; }
    table.sig { margin-top: 18px; }
    table.sig td { border: 0; text-align: center; font-size: 10px; vertical-align: bottom; padding: 0 18px; }
    .sig-img { height: 34px; }
    .sig-img img { height: 34px; }
    .sig-line { border-top: 1px solid #000; margin-top: 4px; padding-top: 3px; }
    .sig-name { font-weight: bold; font-size: 11.55px; }
    .sig-role { color: #000; font-size: 9.9px; }
    .note { text-align: center; font-size: 10px; line-height: 1.6; margin-top: 12px; }
    table.dates { margin-top: 8px; }
    table.dates td { border: 0; font-size: 9px; padding: 0; }
</style>
</head>
<body>
<div class="sheet">
<div class="inner">
    <div class="center"><img class="logo" src="<?= e($img('/assets/img/result/logo.png')) ?>" alt="BKABD"></div>
    <h1 class="center">BANGLADESH KINDERGARTEN ASSOCIATION</h1>
    <div class="center regno">Govt. Reg. No.: S-1028/98</div>
    <div class="center contact">
        59/3, Brammonchiron, Jatrabari, Dhaka-1203, Bangladesh, Mobile: +88-02-224458009<br>
        Email: infobkabd@gmail.com, info@bkabd.com, Web: www.bkabd.com, www.bka.com.bd, www.bka.org.bd
    </div>

    <div class="rule"></div>
    <h2 class="center">SCHOLARSHIP RESULT - <?= e($student['session']) ?></h2>

    <div class="bar">Student Details Information</div>

    <table class="details">
        <tr>
            <th>Division</th><td><?= e($student['division_name']) ?></td>
            <th>District</th><td><?= e($student['district_name']) ?></td>
        </tr>
        <tr>
            <th>Thana/Upazila</th><td><?= e($student['upazila_name']) ?></td>
            <th>Name of Center</th><td><?= e($student['center_name']) ?></td>
        </tr>
        <tr>
            <th>Class</th><td><?= e(ClassName($student['class'])) ?></td>
            <th>Date of Birth</th><td><?= e(DateFormat($student['dob'], 'd-m-Y')) ?></td>
        </tr>
        <tr>
            <th>Registration No.</th><td><?= e($student['registration']) ?></td>
            <th>Roll No.</th><td><?= e($student['roll']) ?></td>
        </tr>
        <tr><th>Name of Student</th><td colspan="3"><?= e($student['name']) ?></td></tr>
        <tr><th>Father's Name</th><td colspan="3"><?= e($student['father_name']) ?></td></tr>
        <tr><th>Mother's Name</th><td colspan="3"><?= e($student['mother_name']) ?></td></tr>
        <tr><th>Name of Institute</th><td colspan="3"><?= e($student['institute_name']) ?></td></tr>
    </table>

    <div class="bar">Exam Marks &amp; Grade Sheet</div>

    <table class="marks">
        <thead>
            <tr>
                <th width="6%">Sl.</th>
                <th width="20%">Subject Name</th>
                <th width="15%">Full Marks</th>
                <th width="16%">Marks Obtained</th>
                <th width="15%">Total Marks</th>
                <th width="16%">Percentage Mark</th>
                <th width="12%">Grade</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>01</td><td>Bangla</td>
                <td><?= e($fm('bangla')) ?></td><td><?= e($student['bangla']) ?></td>
                <td rowspan="3" class="fill"><?= e($student['total']) ?></td>
                <td rowspan="3" class="fill"><?= e($student['percentage']) ?>%</td>
                <td rowspan="3" class="fill"><?= e($gradeText) ?></td>
            </tr>
            <tr><td>02</td><td>English</td><td><?= e($fm('english')) ?></td><td><?= e($student['english']) ?></td></tr>
            <tr><td>03</td><td>Mathematics</td><td><?= e($fm('math')) ?></td><td><?= e($student['math']) ?></td></tr>
        </tbody>
    </table>

    <table class="sig">
        <tr>
            <?php foreach ([
                ['chairman',           'MONOWARA BHUIYAN',   'Chairman'],
                ['general_secretary',  'MD. MIZANUR RAHMAN', 'General Secretary'],
                ['controller',         'MD. FARUK HOSSEN',  'Controller of Exam'],
                ['education_secretary','MD. ABDUL ALIM',    'Education Secretary'],
            ] as [$signKey, $signName, $signRole]): ?>
            <td>
                <div class="sig-img"><img src="<?= e($img(result_signature($signKey))) ?>" alt=""></div>
                <div class="sig-line">
                    <div class="sig-name"><?= e($signName) ?></div>
                    <div class="sig-role"><?= e($signRole) ?></div>
                </div>
            </td>
            <?php endforeach; ?>
        </tr>
    </table>

    <div class="note">
        <b>Note:</b> Any kind of objection this result should be reported to the authority within 7 days of published.<br>
        &copy; Copyright 1984 - <?= date('Y') ?> Bangladesh Kindergarten Association. All Rights Reserved. Developed by BKABD
    </div>

    <table class="dates">
        <tr>
            <td style="text-align:left"><?php if ($pubDate): ?>Result Publication Date: <?= e($pubDate) ?><?php endif; ?></td>
            <td style="text-align:right">Print Date &amp; Time: <?= e(date('d F Y, h:i A')) ?></td>
        </tr>
    </table>
</div>
</div>
</body>
</html>
