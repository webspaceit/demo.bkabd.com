<?php
/** Eight-up student seat-arrangement cards for A4 printing. */
$pages = array_chunk($students, 8);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Seat Arrangement Cards') ?></title>
    <style>
        @page { size: A4 portrait; margin: 4mm; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; color: #111; }
        body { background: #e8edf2; }
        .toolbar { position: sticky; top: 0; z-index: 2; padding: 10px; text-align: center; background: #172033; }
        .toolbar button { border: 0; border-radius: 5px; padding: 9px 18px; color: #fff; background: #087443; font-weight: 700; cursor: pointer; }
        .sheet { width: 202mm; height: 288mm; margin: 12px auto; padding: 0; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); grid-template-rows: repeat(4, minmax(0, 1fr)); gap: 2.5mm; background: #fff; }
        .sheet.page-break { page-break-after: always; break-after: page; }
        .seat-card { position: relative; isolation: isolate; min-width: 0; min-height: 0; padding: 2mm 2.5mm 1.7mm; border: .65mm solid #111; display: flex; flex-direction: column; overflow: hidden; background: url("<?= e(brand_asset('pattern_path', '/assets/img/result/pattern.png')) ?>") repeat; background-size: 88mm 88mm; }
        .seat-card::before { content: ""; position: absolute; z-index: 0; inset: 0; background: url("<?= e(brand_asset('watermark_path', '/assets/img/result/for-registration.png')) ?>") no-repeat center center / 43.2mm 43.2mm; opacity: .35; pointer-events: none; }
        .seat-card > * { position: relative; z-index: 1; }
        .card-head { position: relative; min-height: 13mm; padding: 0 1mm 1mm 13mm; text-align: center; border-bottom: .25mm solid #065f46; }
        .card-head img { position: absolute; left: 0; top: .5mm; width: 11mm; height: 11mm; object-fit: contain; }
        .association { margin: 0; font-size: 8.8pt; line-height: 1.05; font-weight: 800; white-space: normal; overflow-wrap: anywhere; }
        .reg { margin-top: .5mm; font-size: 7.5pt; line-height: 1; font-weight: 700; }
        .session { margin-top: .6mm; font-size: 8pt; line-height: 1; font-weight: 700; }
        .pill { display: table; margin: 1.5mm auto 1.4mm; padding: 1.3mm 5mm; border-radius: 7mm; background: #050505; color: white; font-size: 8.5pt; line-height: 1; font-weight: 700; }
        .details { display: grid; grid-template-columns: 19mm 3mm minmax(0, 1fr); gap: 1.1mm .8mm; font-size: 9.5pt; line-height: 1.15; }
        .label { font-weight: 700; }
        .value { min-width: 0; overflow-wrap: anywhere; }
        .location { font-size: 8.2pt; }
        .signatures { display: flex; justify-content: space-between; align-items: flex-end; gap: 3mm; margin-top: auto; padding-top: 1mm; }
        .sign { width: 48%; text-align: center; font-size: 7.3pt; line-height: 1.05; }
        .sign img { display: block; width: 60%; height: 6mm; margin: 0 auto; object-fit: contain; object-position: bottom center; }
        .sign span { display: block; border-top: .2mm solid #333; padding-top: .6mm; }
        @media screen {
            .sheet { box-shadow: 0 2px 10px #0002; }
        }
        @media print {
            body { background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .toolbar { display: none; }
            .sheet { margin: 0; box-shadow: none; }
        }
    </style>
</head>
<body>
<div class="toolbar"><button type="button" onclick="window.print()">Print Seat Arrangement Cards</button></div>
<?php foreach ($pages as $pageIndex => $page): ?>
    <main class="sheet<?= $pageIndex < count($pages) - 1 ? ' page-break' : '' ?>">
        <?php foreach ($page as $student): ?>
            <article class="seat-card">
                <header class="card-head">
                    <img src="<?= e(brand_asset('logo_path', '/assets/img/result/logo.png')) ?>" alt="<?= e(brand('short_name')) ?> logo">
                    <h1 class="association"><?= e(brand('authority_name')) ?></h1>
                    <div class="reg"><?= e(brand('registration_label')) ?>: <?= e(brand('registration_no')) ?></div>
                    <div class="session"><?= e(brand('exam_name')) ?> - <?= e($session) ?></div>
                </header>
                <div class="pill">Seat Arrangement</div>
                <div class="details">
                    <div class="label">Roll No.</div><div>:</div><div class="value"><?= e($student['roll'] ?? '') ?></div>
                    <div class="label">Name</div><div>:</div><div class="value"><?= e($student['name'] ?? '') ?></div>
                    <div class="label">Class</div><div>:</div><div class="value"><?= e($className) ?></div>
                    <div class="label">Center</div><div>:</div><div class="value"><?= e($student['center_name'] ?? '') ?></div>
                    <?php if (!empty($student['seat_assignment']['room_label'])): ?>
                        <div class="label">Seat Place</div><div>:</div>
                        <div class="value location"><?= e($student['seat_assignment']['room_label']) ?>, Seat <?= e($student['seat_assignment']['seat_number'] ?? '') ?></div>
                    <?php endif; ?>
                </div>
                <footer class="signatures">
                    <div class="sign">
                        <img src="<?= e(result_signature('controller')) ?>" alt="">
                        <span><?= e(brand('controller_title')) ?></span>
                    </div>
                    <div class="sign">
                        <img src="<?= e(result_signature('education_secretary')) ?>" alt="">
                        <span><?= e(brand('education_secretary_title')) ?></span>
                    </div>
                </footer>
            </article>
        <?php endforeach; ?>
    </main>
<?php endforeach; ?>
</body>
</html>
