<?php
/** Full-page seat-plan cards using the association admit-card visual style. */
$printDate = date('d-m-Y');
$copyrightYear = date('Y');
$pages = array_chunk($students, 2);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Seat Plan') ?></title>
    <style>
        @page { size: A4 portrait; margin: 4mm; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; color: #111; }
        body { background: #e9eef4; }
        .toolbar { position: sticky; z-index: 2; top: 0; padding: 10px; text-align: center; background: #172033; }
        .toolbar button { border: 0; border-radius: 5px; padding: 9px 18px; color: #fff; background: #087443; font-weight: 700; cursor: pointer; }
        .seat-sheet { width: 202mm; height: 289mm; margin: 12px auto; display: grid; grid-template-rows: repeat(2, minmax(0, 1fr)); gap: 3mm; page-break-after: always; break-after: page; background: #fff; box-shadow: 0 2px 10px #0003; }
        .seat-sheet:last-child { page-break-after: auto; break-after: auto; }
        .seat-card { position: relative; width: 100%; height: 100%; padding: 2mm; border: 2px solid #111; background: url("<?= e(asset_v('/assets/img/result/pattern.png')) ?>") repeat; background-size: 90mm 90mm; overflow: hidden; }
        .inner { position: relative; display: flex; flex-direction: column; width: 100%; height: 100%; border: 2px solid #111; overflow: hidden; background: url("<?= e(asset_v('/assets/img/result/for-registration.png')) ?>") no-repeat center 56%; background-size: 62mm 62mm; }
        .banner { position: relative; display: flex; align-items: center; gap: 3mm; padding: 2mm 3mm 8px; border-bottom: 0; }
        .banner::after { content: ""; position: absolute; right: 0; bottom: 0; left: 0; height: 6px; background-image: linear-gradient(#065f46, #065f46), linear-gradient(#065f46, #065f46); background-repeat: no-repeat; background-size: 100% 2px; background-position: 0 0, 0 100%; pointer-events: none; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .logo { flex: 0 0 15mm; width: 15mm; height: 15mm; object-fit: contain; }
        .brand { flex: 1; min-width: 0; text-align: center; }
        .brand h1 { margin: 0; font-size: 15pt; line-height: 1.1; font-weight: 800; color: #0f172a; }
        .gov { margin-top: .7mm; font-size: 9pt; font-weight: 700; }
        .contact { margin-top: .7mm; font-size: 7.5pt; line-height: 1.25; }
        .exam-title { margin: 3mm 0 2mm; text-align: center; color: #0f172a; font-size: 16pt; font-weight: 800; }
        .topline { display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; gap: 3mm; margin: 0 3mm 3mm; }
        .tag { padding: 1mm 2.5mm; border: 1.5px solid #0f172a; border-radius: 8mm; font-size: 9pt; font-weight: 700; }
        .tag:last-child { text-align: right; }
        .pill { padding: 1mm 5mm; border: 2px solid #0f172a; border-radius: 8mm; background: #050505; color: #fff; font-size: 11pt; font-weight: 700; white-space: nowrap; }
        .details { display: grid; grid-template-columns: 1fr 1fr; gap: 1mm 6mm; padding: 0 4mm; font-size: 11pt; }
        .field { display: grid; grid-template-columns: 30mm 4mm minmax(0,1fr); align-items: baseline; line-height: 1.3; }
        .label { font-weight: 700; }
        .value { overflow-wrap: anywhere; }
        .place { margin: 4mm 4mm 0; padding: 3mm; border: 1px solid #334155; border-radius: 2mm; background: #ffffffbd; }
        .place h2 { margin: 0 0 2mm; color: #0f172a; font-size: 11pt; }
        .place-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 3mm; }
        .place-item { text-align: center; }
        .place-item b, .place-item span { display: block; }
        .place-item b { font-size: 9pt; }
        .place-item span { margin-top: 1mm; font-size: 11pt; font-weight: 700; }
        .signatures { display: grid; grid-template-columns: repeat(4, 1fr); gap: 4mm; margin: auto 4mm 0; align-items: end; }
        .sign { text-align: center; font-size: 8pt; }
        .sign img { display: block; width: 100%; height: 12mm; object-fit: contain; object-position: bottom center; }
        .sign-name { border-top: 1px solid #333; padding-top: 1mm; font-weight: 700; }
        .sign-role { margin-top: .5mm; font-size: 7pt; }
        .note { margin: 3mm 4mm 1mm; text-align: center; font-size: 8pt; }
        .copyright { text-align: center; font-size: 7pt; }
        .foot { display: flex; justify-content: space-between; margin: auto 3mm 1.5mm; font-size: 7pt; }
        @media print {
            body { background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .toolbar { display: none; }
            .seat-sheet { margin: 0; box-shadow: none; }
        }
        @media screen and (max-width: 760px) {
            .seat-sheet { width: 100%; height: auto; margin: 0; padding: 8px; grid-template-rows: none; }
            .seat-card { min-height: 48vh; }
            .banner { gap: 8px; padding: 8px; }
            .logo { flex-basis: 56px; width: 56px; height: 56px; }
            .brand h1 { font-size: 15px; }
            .contact { font-size: 8px; }
            .details { font-size: 12px; grid-template-columns: 1fr; }
            .place-grid { grid-template-columns: 1fr 1fr; }
            .signatures { gap: 5px; }
        }
    </style>
</head>
<body>
<div class="toolbar"><button type="button" onclick="window.print()">Print Seat Plan</button></div>
<?php foreach ($pages as $page): ?>
    <main class="seat-sheet">
    <?php foreach ($page as $student): ?>
        <?php $seat = $student['seat_assignment'] ?? null; ?>
        <article class="seat-card">
            <div class="inner">
                <header class="banner">
                    <img class="logo" src="<?= e(asset_v('/assets/img/result/logo.png')) ?>" alt="BKABD">
                    <div class="brand">
                        <h1>BANGLADESH KINDERGARTEN ASSOCIATION</h1>
                        <div class="gov">Govt. Reg. No.: S-1028/98</div>
                        <div class="contact">
                            59/3, Brammonchiron, Jatrabari, Dhaka-1203, Bangladesh, Mobile: +88-02-224458009<br>
                            Email: infobkabd@gmail.com, info@bkabd.com, Web: www.bkabd.com, www.bka.com.bd, www.bka.org.bd
                        </div>
                    </div>
                </header>
                <h2 class="exam-title">Scholarship Examination - <?= e($student['session'] ?? $session) ?></h2>
                <div class="topline">
                    <div class="tag">Roll No.: <?= e($student['roll'] ?? '') ?></div>
                    <div class="pill">Seat Plan</div>
                    <div class="tag">Class: <?= e($className) ?></div>
                </div>
                <section class="details">
                    <div class="field"><span class="label">Name</span><span>:</span><span class="value"><?= e($student['name'] ?? '') ?></span></div>
                    <div class="field"><span class="label">Center</span><span>:</span><span class="value"><?= e($centerName) ?></span></div>
                </section>
                <section class="place">
                    <h2>Seat Location</h2>
                    <div class="place-grid">
                        <div class="place-item"><b>Building</b><span><?= e($seat['building_name'] ?? 'Not assigned') ?></span></div>
                        <div class="place-item"><b>Floor</b><span><?= e($seat['floor_number'] ?? '—') ?></span></div>
                        <div class="place-item"><b>Room</b><span><?= e($seat['room_number'] ?? '—') ?></span></div>
                        <div class="place-item"><b>Seat No.</b><span><?= e($seat['seat_number'] ?? '—') ?></span></div>
                    </div>
                </section>
                <div class="signatures">
                    <?php foreach ([
                        ['chairman', 'MONOWARA BHUIYAN', 'Chairman'],
                        ['general_secretary', 'MD. MIZANUR RAHMAN', 'General Secretary'],
                        ['controller', 'MD. FARUK HOSSEN', 'Controller of Exam'],
                        ['education_secretary', 'MD. ABDUL ALIM', 'Education Secretary'],
                    ] as [$role, $name, $label]): ?>
                        <div class="sign">
                            <img src="<?= e(result_signature($role)) ?>" alt="">
                            <div class="sign-name"><?= e($name) ?></div>
                            <div class="sign-role"><?= e($label) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="note"><b>Note:</b> Please bring this seat plan card to the examination hall.</div>
                <div class="copyright">&copy; Copyright 1984 - <?= e($copyrightYear) ?> Bangladesh Kindergarten Association. All Rights Reserved. Developed by Web Space IT</div>
                <div class="foot"><span>Print Date: <?= e($printDate) ?></span><span><?= e($className) ?> | <?= count($students) ?> students</span></div>
            </div>
        </article>
    <?php endforeach; ?>
    </main>
<?php endforeach; ?>
</body>
</html>
