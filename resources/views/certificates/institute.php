<?php
/**
 * Printable institute membership certificate.
 * Ported from the original pdf/InstituteCertificate.blade.php (browser print).
 * Rendered without a layout: this opens in its own window.
 */
$inst = $institute;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <link rel="icon" href="<?= e(brand_asset('favicon_path', '/assets/img/result/logo.png')) ?>">
    <title><?= e(brand('short_name')) ?> — Institute Certificate</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: "Monotype Corsiva", cursive; margin: 0; }
        u { border-bottom: 1px dotted #000; text-decoration: none; padding: 0 70px; font-size: 20px; }
        .main-body { text-align: justify; font-size: 20px; line-height: 1.6; margin: 310.8px 55.4px 0 97.4px; }
        .date { margin-right: 70px; margin-top: 0.08px; float: right; font-family: Tahoma; }
        .clear { clear: both; }
        .no-print { text-align: right; }
        .page { height: 646.8px; page-break-after: always; padding: 20px 0 0 0; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
<div class="no-print"><button type="button" onclick="window.print()">Click to Print</button></div>
<div class="page">
    <div class="date"><?= e(date('d.m.Y')) ?></div>
    <div class="clear"></div>
    <div class="main-body">
        This is to certify that <u><b><?= e($inst['name']) ?></b></u> address is
        <u><?= e($inst['address'] ?? '') ?></u> is the Registered member of <?= e(brand('authority_name')) ?>
        <u><?= e($inst['director_name'] ?? '') ?></u>
        is the Founder/Director /Principal/Headmaster of this institution. It is established in
        <u><?= e($inst['established_date'] ?? '') ?></u>.
        The Education System, Syllabus, Curriculum &amp; Management system of this institution is controlled by
        <?= e(brand('authority_name')) ?>.
        The membership registration number of this institution is <u><?= e($inst['certificate_no'] ?? '') ?></u>.
        The Validity time of this certificate is <u><?= e(date('d-m-Y', strtotime($inst['validity_date']))) ?></u>
        and the last renewal date is <u><?= e(empty($inst['last_renewal_date']) ? '' : date('d-m-Y', strtotime($inst['last_renewal_date']))) ?></u>.<br>
        We wish all the progress of this institution.<br>
    </div>
</div>
</body>
</html>
