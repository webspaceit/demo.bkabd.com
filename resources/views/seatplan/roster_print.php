<?php
/** Seat-plan roster styled with the association admit-card branding. */
$printDate = date('d-m-Y');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Seat Plan') ?></title>
    <style>
        @page { size: 210mm 297mm; margin: 8mm; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; color: #111; }
        body { background: #e9eef4; }
        .toolbar { position: sticky; z-index: 2; top: 0; padding: 10px; text-align: center; background: #172033; }
        .toolbar button { border: 0; border-radius: 5px; padding: 9px 18px; color: #fff; background: #087443; font-weight: 700; cursor: pointer; }
        .sheet { position: relative; width: 194mm; min-height: 281mm; margin: 12px auto; padding: 3mm; border: 2px solid #111; background: url("<?= e(brand_asset('pattern_path', '/assets/img/result/pattern.png')) ?>") repeat; background-size: 92mm 92mm; box-shadow: 0 2px 10px #0003; }
        .inner { position: relative; min-height: 273mm; padding: 3mm; border: 2px solid #111; background: rgba(255,255,255,.42); }
        .watermark { position: absolute; z-index: 0; top: 50%; left: 50%; width: 100mm; height: 100mm; transform: translate(-50%, -50%); object-fit: contain; opacity: 1; pointer-events: none; }
        .content { position: relative; z-index: 1; }
        .banner { position: relative; display: flex; align-items: center; gap: 5mm; padding: 2mm 3mm 8px; border-bottom: 0; }
        .banner::after { content: ""; position: absolute; right: 0; bottom: 0; left: 0; height: 6px; background-image: linear-gradient(#065f46, #065f46), linear-gradient(#065f46, #065f46); background-repeat: no-repeat; background-size: 100% 2px; background-position: 0 0, 0 100%; pointer-events: none; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .logo { flex: 0 0 18mm; width: 18mm; height: 18mm; object-fit: contain; }
        .brand { flex: 1; min-width: 0; text-align: center; }
        .brand h1 { margin: 0; color: #0f172a; font-size: 17pt; line-height: 1.1; font-weight: 800; }
        .gov { margin-top: 1mm; font-size: 10pt; font-weight: 700; }
        .contact { margin-top: 1mm; font-size: 8pt; line-height: 1.3; }
        .exam-title { margin: 4mm 0 1mm; text-align: center; color: #0f172a; font-size: 15pt; font-weight: 800; }
        .badge { display: table; margin: 1mm auto 2mm; padding: 1.5mm 6mm; border-radius: 8mm; background: #050505; color: #fff; font-size: 10pt; font-weight: 700; }
        .summary { margin: 1mm 0 3mm; text-align: center; font-size: 10pt; }
        table { position: relative; width: 100%; border-collapse: collapse; background: transparent; font-size: 8.5pt; }
        thead { display: table-header-group; }
        tr { break-inside: avoid; page-break-inside: avoid; }
        th, td { border: 1px solid #334155; padding: 1.2mm 1mm; text-align: center; }
        th { background: rgba(226,232,240,.92); font-weight: 700; }
        td.name { text-align: left; }
        td.signature { min-width: 20mm; }
        .foot { display: flex; justify-content: space-between; margin: 3mm 1mm 0; font-size: 8pt; }
        @media screen { .sheet { background-color: #fff; } }
        @media print {
            body { background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .toolbar { display: none; }
            .sheet { width: auto; min-height: 0; margin: 0; padding: 3mm; box-shadow: none; }
            .inner { min-height: 0; }
            .banner .logo { display: block !important; visibility: visible !important; opacity: 1 !important; }
        }
    </style>
</head>
<body>
<div class="toolbar"><button type="button" onclick="window.print()">Print Seat Plan</button></div>
<main class="sheet">
    <div class="inner">
        <img class="watermark" src="<?= e(brand_asset('watermark_path', '/assets/img/result/for-registration.png')) ?>" alt="">
        <div class="content">
            <header class="banner">
                <img class="logo" src="<?= e(brand_asset('logo_path', '/assets/img/result/logo.png')) ?>" alt="<?= e(brand('short_name')) ?>">
                <div class="brand">
                    <h1><?= e(brand('authority_name')) ?></h1>
                    <div class="gov"><?= e(brand('registration_label')) ?>: <?= e(brand('registration_no')) ?></div>
                    <div class="contact">
                        <?= e(brand('address')) ?><?= brand('phone') !== '' ? ', Mobile: ' . e(brand('phone')) : '' ?><br>
                        <?= brand('email') !== '' ? 'Email: ' . e(brand('email')) : '' ?><?= brand('website') !== '' ? ', Web: ' . e(brand('website')) : '' ?>
                    </div>
                </div>
            </header>
                <h2 class="exam-title"><?= e(brand('exam_name')) ?> - <?= e($session) ?></h2>
            <div class="badge">Seat Plan</div>
            <div class="summary">Class: <?= e($className) ?> &nbsp; | &nbsp; Center: <?= e($centerName) ?> &nbsp; | &nbsp; Total: <?= count($students) ?></div>
            <table>
                <thead><tr>
                    <th style="width:5%">SL</th>
                    <th style="width:10%">Roll</th>
                    <th style="width:27%">Name</th>
                    <th style="width:13%">Building</th>
                    <th style="width:9%">Floor</th>
                    <th style="width:10%">Room</th>
                    <th style="width:8%">Seat</th>
                    <th style="width:18%">Signature</th>
                </tr></thead>
                <tbody>
                <?php foreach ($students as $index => $student): ?>
                    <?php $seat = $student['seat_assignment'] ?? []; ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= e($student['roll'] ?? '') ?></td>
                        <td class="name"><?= e($student['name'] ?? '') ?></td>
                        <td><?= e($seat['building_name'] ?? '') ?></td>
                        <td><?= e($seat['floor_number'] ?? '') ?></td>
                        <td><?= e($seat['room_number'] ?? '') ?></td>
                        <td><?= e($seat['seat_number'] ?? '') ?></td>
                        <td class="signature"></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div class="foot"><span>Print Date: <?= e($printDate) ?></span><span><?= e(brand('authority_name')) ?></span></div>
        </div>
    </div>
</main>
</body>
</html>
