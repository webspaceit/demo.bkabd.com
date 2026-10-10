<?php
/**
 * Printable institute membership registration card.
 * Port of the original pdf/institute_reg_info.blade.php — the browser prints it.
 *
 * @var array $institute  joined row from InstituteService::registrationCard()
 */
$educationType = ['' => '', '1' => 'Bangla Medium', '2' => 'English Medium', '3' => 'Arabic Medium'];
$membershipType = ['' => '', '1' => 'Temporary (1500 BDT)', '2' => 'Life Time (3000 BDT)'];
$validityNote = (string) ($institute['membership_type'] ?? '') === '1'
    ? 'This registration card is valid upto this session, Only for 01 Year Membership Card.'
    : 'This registration card is valid for life time membership with this Association.';
$row = static function (string $label, $value): string {
    return '<tr><th>' . e($label) . '</th><td>:</td><td class="value">' . e($value) . '</td></tr>';
};
$declaration = "I here by declare that, If this/My educational institution recognized as a "
    . "recognized as a member according to by the laws of association. I'll abide by all these "
    . "rules and regulations and take part all activities of the association.";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($institute['name'] ?? 'Institute Registration Card') ?> — BKABD</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #111; margin: 0; }
        .content { border: 2px solid #333; margin: 10px; }
        .inner { border: 1px solid #333; margin: 10px; padding: 14px; }
        .date { text-align: center; font-size: 14px; margin-bottom: 12px; }
        h1 { font-size: 22px; text-align: center; margin: 6px 0 2px; }
        h2 { font-size: 15px; text-align: center; font-weight: normal; margin: 0 0 4px; }
        .contact { text-align: center; font-size: 12px; margin-bottom: 12px; }
        .banner { background: #000; color: #fff; text-align: center; padding: 7px;
                  border-radius: 14px; font-size: 16px; width: 60%; margin: 0 auto; }
        .certno { text-align: center; font-size: 16px; margin: 6px 0 12px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { padding: 4px 6px; vertical-align: top; }
        th { text-align: left; width: 180px; font-weight: bold; }
        td.value { text-align: justify; }
        .declaration { border: 1px solid #333; margin-top: 12px; }
        .declaration th, .declaration td { border: 1px solid #333; }
        hr { border: none; border-top: 3px solid #333; margin: 14px 0; }
        .note { text-align: center; font-size: 13px; margin-top: 10px; }
        .copy { text-align: center; font-size: 9px; margin-top: 8px; }
        .no-print { text-align: right; margin: 8px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
<div class="no-print"><button type="button" onclick="window.print()">Click to Print</button></div>
<div class="content">
    <div class="inner">
        <div class="date">Date of Registration : <?= e(DateFormat($institute['created_at'] ?? '', 'd-m-Y')) ?><br><br>
            <span>Thank You, Your Membership Registration has been completed successfully.<br>
            You must print and save the Membership Registration Card.</span>
        </div>
        <h1>BANGLADESH KINDERGARTEN ASSOCIATION</h1>
        <h2>Govt. Reg. No.: S-1028/98</h2>
        <p class="contact">59/3, Brammonchiron, Jatrabari, Dhaka-1203, Bangladesh, Mobile: +88-02-224458009<br>
            Email: infobkabd@gmail.com, info@bkabd.com, Web: www.bkabd.com, www.bka.com.bd, www.bka.org.bd</p>
        <div class="banner">Membership/Institute Registration Card</div>
        <div class="certno">Membership Certificate No. - <?= e($institute['certificate_no'] ?? '') ?></div>
        <table>
            <?= $row('Name of Institute', $institute['name'] ?? '') ?>
            <?= $row('Name of Center', $institute['center_name'] ?? '') ?>
            <?= $row('Representative Name', $institute['director_name'] ?? '') ?>
            <?= $row('Mobile Number', $institute['phone'] ?? '') ?>
            <?= $row('Email Address', $institute['email'] ?? '') ?>
            <?= $row('Division', $institute['division_name'] ?? '') ?>
            <?= $row('District', $institute['district_name'] ?? '') ?>
            <?= $row('Thana/Upzila', $institute['upazila_name'] ?? '') ?>
            <?= $row('Education Type', $educationType[(string) ($institute['type'] ?? '')] ?? '') ?>
            <?= $row('Institute Address', $institute['address'] ?? '') ?>
            <?= $row('Membership Type', $membershipType[(string) ($institute['membership_type'] ?? '')] ?? '') ?>
        </table>
        <table class="declaration">
            <tr><th>My Declaration</th><td>:</td><td><?= e($declaration) ?></td></tr>
        </table>
        <hr>
        <p class="note"><b>Note:</b> <?= e($validityNote) ?></p>
        <span class="copy">&copy; Copyright 1984 - <?= e(date('Y')) ?> Bangladesh Kindergarten Association. All Rights Reserved. Developed by BKABD</span>
    </div>
</div>
</body>
</html>
