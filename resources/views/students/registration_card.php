<?php
/**
 * Printable student registration card.
 * Port of the original pdf/student_reg_info.blade.php — the browser prints it
 * (no layout: opens in its own window). The original streamed a dompdf PDF;
 * this clone renders printable HTML consistently with the certificates.
 *
 * @var array $student  row from students joined with institute/center/geo names
 */
$photo = (string) ($student['photo'] ?? '');
$resolve = static function (string $file): ?string {
    if ($file === '') {
        return null;
    }
    $candidates = str_contains($file, '/')
        ? [$file]
        : ['images/students/' . $file, 'uploads/students/' . $file];
    foreach ($candidates as $rel) {
        $abs = BASE_PATH . '/public/' . $rel;
        if (is_file($abs)) {
            $mime = @mime_content_type($abs) ?: 'image/jpeg';
            return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($abs));
        }
    }
    return null;
};
$photoSrc = $resolve($photo);
if ($photoSrc === null) {
    $placeholder = ((int) ($student['gender'] ?? 0) === 2) ? 'female.jpeg' : 'male.jpeg';
    $photoSrc = $resolve($placeholder);
}
$row = static function (string $label, $value): string {
    return '<tr><th align="left" width="30%">' . e($label) . '</th><th width="1%">:</th>'
        . '<th align="left" class="nonbold">' . e($value) . '</th></tr>';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BKABD — Student Registration Card</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; margin: 0; }
        .content { border: 2px solid #333; }
        .content1 { border: 1px solid #333; margin: 10px; padding: 12px; }
        .date { text-align: center; }
        .student img { display: block; width: 146px; height: 146px; padding: 2px; }
        .card3 { text-align: center; background: #000; color: #fff; width: 45%;
                 padding: 7px 0; border-radius: 15px; margin: 0 auto; }
        .card2 { text-align: center; }
        .head { text-align: center; font-size: 170%; margin: 4px 0; }
        .head2 { text-align: center; margin: 0; }
        .head3 { text-align: center; font-size: 13px; margin: 4px 0 12px; }
        .left { float: left; width: 70%; }
        .right { float: right; width: 160px; }
        .clear { clear: both; }
        table { font-size: 15px; width: 100%; border-collapse: collapse; }
        th { padding: 2px 4px; font-weight: bold; }
        th.nonbold { font-weight: normal; }
        hr { border: none; border-top: 2px solid #333; margin: 10px 0; }
        .note { text-align: center; font-size: 13px; }
        .copy { text-align: center; font-size: 10px; }
        .no-print { text-align: right; margin: 8px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
<div class="no-print"><button type="button" onclick="window.print()">Click to Print</button></div>
<div class="content">
    <div class="content1">
        <div class="date">Date of Registration : <?= e(DateFormat($student['created_at'] ?? '', 'd-m-Y')) ?><br><br>
            <span>Thank You, Your Registration has been completed successfully. You must print and save the
            Registration Card. It will be required to participate in the next Scholarship Examination.</span>
        </div>
        <h1 class="head">BANGLADESH KINDERGARTEN ASSOCIATION</h1>
        <h4 class="head2">Govt. Reg. No.: S-1028/98</h4>
        <p class="head3">59/3, Brammonchiron, Jatrabari, Dhaka-1203, Bangladesh, Mobile: +88-02-224458009<br>
            Email: infobkabd@gmail.com, info@bkabd.com, Web: www.bkabd.com, www.bka.com.bd, www.bka.org.bd</p>

        <h3 class="card3">Student Registration Card</h3>
        <h3 class="card2">Scholarship Examination - <?= e($student['session'] ?? '') ?></h3>

        <div>
            <div class="left">
                <table>
                    <?= $row('Registration No.', $student['registration'] ?? '') ?>
                    <?= $row('Student Name', $student['name'] ?? '') ?>
                    <?= $row("Father's Name", $student['father_name'] ?? '') ?>
                    <?= $row("Mother's Name", $student['mother_name'] ?? '') ?>
                    <?= $row('Date of Birth', !empty($student['dob']) ? DateFormat($student['dob'], 'd-m-Y') : '') ?>
                    <?= $row('Class', ClassName($student['class'] ?? 0)) ?>
                    <?= $row('Division', $student['division_name'] ?? '') ?>
                    <?= $row('District', $student['district_name'] ?? '') ?>
                    <?= $row('Thana/Upzila', $student['upazila_name'] ?? '') ?>
                    <?= $row('Name of Center', $student['center_name'] ?? '') ?>
                    <?= $row('Name of Institute', $student['institute_name'] ?? '') ?>
                    <?= $row('Institute Address', $student['institute_address'] ?? '') ?>
                    <?= $row('Exam Subject', 'Bangla, English, Mathematics') ?>
                </table>
            </div>
            <div class="right">
                <div class="student">
                    <?php if ($photoSrc !== null): ?>
                        <img src="<?= $photoSrc ?>" alt="Student photo">
                    <?php endif; ?>
                </div>
            </div>
            <div class="clear"></div>
            <hr>
        </div>

        <p class="note"><b>Note:</b> This registration card is valid upto this session. Examinee must bring this card in the examination hall.</p>
        <span class="copy">&copy; Copyright 1984 - <?= e(date('Y')) ?> Bangladesh Kindergarten Association. All Rights Reserved. Developed by BKABD</span>
    </div>
</div>
</body>
</html>
