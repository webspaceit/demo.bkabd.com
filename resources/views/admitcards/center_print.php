<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title ?? 'Admit Cards') ?></title>

<?php
// ----------------------------------------------------------------
// Resolve helpers identical to registration_card.php
// ----------------------------------------------------------------
$resolve = static function (string $file): ?string {
    if ($file === '') { return null; }
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

$resolveStudentPhoto = static function (array $student) use ($resolve): ?string {
    $src = $resolve((string) ($student['photo'] ?? ''));
    if ($src === null) {
        $placeholder = ((int) ($student['gender'] ?? 0) === 2) ? 'female.jpeg' : 'male.jpeg';
        $src = $resolve($placeholder);
    }
    return $src;
};

$className = static function (int $class): string {
    return match ($class) {
        6=>'KG', 1=>'One', 2=>'Two', 3=>'Three', 4=>'Four', 5=>'Five', default=>(string)$class,
    };
};

$row = static function (string $label, $value): string {
    return '<div class="rc-row"><span class="rc-lbl">' . e($label) . '</span>'
        . '<span class="rc-sep">:</span>'
        . '<span class="rc-val">' . e((string)($value ?? '')) . '</span></div>';
};

$qrContent = implode("\n", [
    'BANGLADESH KINDERGARTEN ASSOCIATION',
    'Govt. Reg. No.: S-1028/98',
    '59/3, Brammonchiron, Jatrabari, Dhaka-1203, Bangladesh, Mobile: +88-02-224458009',
    'Email: infobkabd@gmail.com, info@bkabd.com, Web: www.bkabd.com, www.bka.com.bd, www.bka.org.bd',
]);
$qrContentEscaped = e($qrContent);
$printDate     = date('d-m-Y');
$copyrightYear = date('Y');
$session       = $activeSession ?? '';
$singleStudent = !empty($singleStudent);
$pairs         = array_chunk($students, $singleStudent ? 1 : 2);
$total         = count($students);
?>

<style>
/* ---- Load all the original registration_card styles ---- */
<?php
// Inline the original card CSS by reading it directly
$cssFile = BASE_PATH . '/resources/views/students/registration_card.php';
$content = file_get_contents($cssFile);
// Extract only what's between the first <style> and </style>
if (preg_match('/<style>(.*?)<\/style>/s', $content, $m)) {
    $css = str_replace(
        [
            "<?= asset_v('/assets/img/result/pattern.png') ?>",
            "<?= asset_v('/assets/img/result/for-registration.png') ?>",
        ],
        ['/assets/img/result/pattern.png', '/assets/img/result/for-registration.png'],
        $m[1]
    );
    // The source view's only PHP inside <style> controls its optional layout.
    // Strip that template logic after resolving its image URLs into real CSS.
    $css = preg_replace('/<\?(?:php|=).*?\?>/s', '', $css);
    echo $css;
}
?>

/* ---- Center-print overrides ---- */
@page { size: A4 portrait; margin: 0; }
<?php if ($singleStudent): ?>@page { size: A5 landscape; margin: 0; }<?php endif; ?>

html, body { background: #e5e7eb; margin: 0; padding: 0; }

/* Toolbar */
.cp-toolbar {
    background: #064e3b; color: #fff;
    padding: .5rem 1.2rem;
    display: flex; align-items: center; justify-content: space-between;
    position: sticky; top: 0; z-index: 100;
    font-family: Arial, sans-serif;
}
.cp-toolbar h2 { font-size: .9rem; font-weight: 600; }
.cp-toolbar small { font-size: .75rem; opacity: .75; }
.cp-toolbar button {
    background: #fff; color: #064e3b; border: none;
    border-radius: 5px; padding: .35rem 1rem;
    font-size: .82rem; font-weight: 700; cursor: pointer;
}

/* One A4 page = 2 card slots */
.cp-page {
    width: 210mm;
    height: 297mm;
    margin: 8px auto;
    background: #fff;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 2px 12px rgba(0,0,0,.25);
}
.cp-page.single { width: 210mm; height: 148.5mm; margin: 0 auto; }

/* Each card slot = exactly half A4 */
.cp-slot {
    width: 210mm;
    height: 148.5mm;
    overflow: hidden;
    flex-shrink: 0;
    position: relative;
}
.cp-slot + .cp-slot {
    border-top: 2px dashed #999;
}

.cp-slot .rc-page {
    width: 210mm !important;
    height: 148.5mm !important;
    min-height: 0 !important;
    margin: 0 !important;
    box-shadow: none !important;
    padding: 0.15in !important;
    overflow: hidden;
    box-sizing: border-box;
}
.cp-slot .reg-card,
.cp-slot .rc-inner { height: 100%; box-sizing: border-box; }
.cp-slot .rc-inner { display: flex; flex-direction: column; justify-content: space-between; }
.cp-slot .rc-banner { gap: .55rem; padding: .3rem .7rem .25rem; margin-bottom: .35rem; }
.cp-slot .rc-logo img { width: 90px; height: 90px; object-fit: contain; }
.cp-slot .rc-banner > div:not(.rc-qr) { flex: 1 1 auto; min-width: 0; text-align: center; }
.cp-slot .rc-banner h1 { font-size: 1.75rem; }
.cp-slot .rc-reg { font-size: .95rem; }
.cp-slot .rc-banner .rc-gov { font-size: .82rem; line-height: 1.35; }
.cp-slot .rc-qr canvas,
.cp-slot .rc-qr img,
.cp-slot .rc-qr table { width: 75px !important; height: 75px !important; }
.cp-slot .rc-exam-head { font-size: 1.5rem; margin: .25rem 0 .35rem; }
.cp-slot .rc-top { padding: .15rem .7rem .15rem; }
.cp-slot .rc-tag { padding: .1rem .55rem; font-size: .8rem; }
.cp-slot .rc-center .rc-pill { padding: .15rem .8rem; font-size: .95rem; }
.cp-slot .rc-body { padding: .25rem .7rem 0; gap: .6rem; margin: 0 0 .25rem !important; }
.cp-slot .rc-col { flex: 1 1 0; min-width: 0; }
.cp-slot .rc-row { grid-template-columns: 9.5rem 1rem 1fr; font-size: .95rem; line-height: 1.2; padding: .03rem 0; }
.cp-slot .rc-photo img { width: 74px; height: 84px; }
/* Full-width rules, styled like the individual admit card. */
.cp-slot .rc-rules { width: auto; margin: .25rem 0 .25rem .7rem !important; font-size: 1rem !important; line-height: 1.22 !important; }
.cp-slot .rc-rules p { margin: 0 0 .15rem; }
.cp-slot .rc-rules p:last-child { margin-bottom: 0; white-space: nowrap; font-size: 1rem; }
.cp-slot .rc-rules ol { margin: 0; padding-left: .8rem; }
.cp-slot .sig-tbl { margin-top: .25rem !important; }
.cp-slot .overflow-x-auto { margin-top: 0; }
.cp-slot .sig-tbl { width: 100%; table-layout: fixed; border-collapse: collapse; }
.cp-slot .overflow-x-auto { width: 100%; }
.cp-slot .sig-img img { max-width: 90px; max-height: 36px; }
.cp-slot .sig-name { font-size: .82rem; }
.cp-slot .sig-role { font-size: .68rem; }
.cp-slot .rc-note { font-size: .75rem; padding: .12rem .7rem 0; line-height: 1.15; }
.cp-slot .rc-foot { padding: .1rem .7rem .15rem; font-size: .75rem; }

/* The card keeps its print proportions in the browser's narrow viewport. */
@media screen and (max-width: 900px) {
    .cp-page { zoom: .68; }
    .cp-slot .rc-banner { flex-wrap: nowrap; }
    .cp-slot .rc-banner h1 { font-size: 1.75rem; white-space: nowrap; }
    .cp-slot .rc-banner .rc-gov { white-space: nowrap; overflow-wrap: normal; }
    .cp-slot .rc-top { flex-direction: row; align-items: center; }
    .cp-slot .rc-tags { min-width: 0; }
    .cp-slot .rc-roll { min-width: 0; margin-left: auto; }
    .cp-slot .rc-tag { white-space: nowrap; text-align: left; }
    .cp-slot .rc-body { flex-direction: row; gap: .6rem; }
    .cp-slot .rc-col { width: auto; }
    .cp-slot .rc-photo { align-self: flex-start; }
    .cp-slot .sig-tbl, .cp-slot .sig-tbl tbody { display: table; }
    .cp-slot .sig-tbl tr { display: table-row; }
    .cp-slot .sig-tbl td { display: table-cell; padding: 0 .2rem; }
}

/* Hide py-6 wrapper padding */
.cp-slot .py-6 { padding: 0 !important; }

@media print {
    html, body { background: #fff; }
    .cp-toolbar { display: none; }
    .cp-page {
        margin: 0;
        box-shadow: none;
        break-after: auto;
        page-break-after: auto;
    }
    .cp-page + .cp-page {
        break-before: page;
        page-break-before: always;
    }
    .cp-slot .rc-page { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>
</head>
<body>

<div class="cp-toolbar">
    <div>
        <h2>Admit Cards — <?= e($centerName ?? '') ?> &nbsp;|&nbsp; Session <?= e($session) ?></h2>
        <small><?= $total ?> students &nbsp;·&nbsp; <?= ceil($total / 2) ?> pages</small>
    </div>
    <button onclick="window.print()">&#128424; Print / Save as PDF</button>
</div>

<?php foreach ($pairs as $pair): ?>
<div class="cp-page<?= $singleStudent ? ' single' : '' ?>">
    <?php for ($slot = 0; $slot < ($singleStudent ? 1 : 2); $slot++):
        $s = $pair[$slot] ?? null;
        $photoSrc = $s ? $resolveStudentPhoto((array)$s) : null;
    ?>
    <div class="cp-slot">
      <?php if ($s): ?>
        <div class="rc-page">
        <div class="reg-card border-2 border-black bg-white">
            <div class="rc-inner border-2 border-black">
            <div class="rc-banner">
                <span class="rc-logo"><img src="<?= e(asset_v('/assets/img/result/logo.png')) ?>" alt="BKABD" onerror="this.style.display='none'"></span>
                <div>
                    <h1>BANGLADESH KINDERGARTEN ASSOCIATION</h1>
                    <p class="rc-reg">Govt. Reg. No.: S-1028/98</p>
                    <p class="rc-gov">
                        59/3, Brammonchiron, Jatrabari, Dhaka-1203, Bangladesh, Mobile: +88-02-224458009<br>
                        Email: infobkabd@gmail.com, info@bkabd.com, Web: www.bkabd.com, www.bka.com.bd, www.bka.org.bd
                    </p>
                </div>
                <div class="rc-qr">
                    <div class="reg-qr inline-block" data-qr-content="<?= $qrContentEscaped ?>"></div>
                </div>
            </div>

            <p class="rc-exam-head">Scholarship Exam - <?= e($s['session'] ?? $session) ?></p>

            <div class="rc-top">
                <div class="rc-tags">
                    <div class="rc-tag">Reg No.&nbsp;: <?= e($s['registration'] ?? '') ?></div>
                </div>
                <div class="rc-center">
                    <div class="rc-pill">Admit Card</div>
                </div>
                <div class="rc-tag rc-roll">Exam Roll&nbsp;: <?= e($s['roll'] ?? '') ?></div>
            </div>

            <div class="rc-body">
                <div class="rc-col">
                    <?= $row('Name', $s['name'] ?? '') ?>
                    <?= $row("Father's Name", $s['father_name'] ?? '') ?>
                    <?= $row("Mother's Name", $s['mother_name'] ?? '') ?>
                    <?= $row('Date of Birth', !empty($s['dob']) ? DateFormat($s['dob'], 'd-m-Y') : '') ?>
                </div>
                <div class="rc-col">
                <?= $row('Exam Class', $className((int)($s['class'] ?? 0))) ?>
                <?= $row('Exam Date & Time', 'As per routine') ?>
                <?= $row('Exam Center', $s['center_name'] ?? '') ?>
                <?= $row('Exam Venue', $s['institute_name'] ?? '') ?>
                </div>
                <div class="rc-photo">
                    <?php if ($photoSrc): ?><img src="<?= $photoSrc ?>" alt="Student photo"><?php endif; ?>
                </div>
            </div>

            <div class="rc-rules">
                <p class="font-bold mb-1 text-slate-800">নিয়মাবলী:</p>
                <p>১. প্রবেশপত্র (অ্যাডমিট কার্ড) ছাড়া কোনো পরীক্ষার্থীকে পরীক্ষা কক্ষে প্রবেশ করতে দেওয়া হবে না।</p>
                <p>২. প্রবেশপত্র ছাড়া কোনো পরীক্ষার্থী লিখিত পরীক্ষার কাগজ বা বই সঙ্গে রাখতে পারবে না।</p>
                <p>৩. পরীক্ষা শুরুর দুই ঘণ্টার আগে কোনো পরীক্ষার্থী পরীক্ষা কক্ষ থেকে বাইরে যেতে পারবেন না।</p>
                <p>৪. প্রত্যেক পরীক্ষার্থীকে অ্যাসোসিয়েশনের সকল নিয়ম মেনে চলতে হবে। অন্যথায়, তাকে পরীক্ষায় অংশগ্রহণের অনুমতি দেওয়া হবে না।</p>
            </div>

            <div class="overflow-x-auto">
            <table class="sig-tbl mt-4 w-full text-[10px] sm:text-xs">
                <tr>
                    <?php foreach ([
                        ['chairman',            'MONOWARA BHUIYAN',   'Chairman'],
                        ['general_secretary',   'MD. MIZANUR RAHMAN', 'General Secretary'],
                        ['controller',          'MD. FARUK HOSSEN',   'Controller of Exam'],
                        ['education_secretary', 'MD. ABDUL ALIM',     'Education Secretary'],
                    ] as [$signKey, $signName, $signRole]): ?>
                    <td>
                        <div class="sig-img"><img src="<?= e(result_signature($signKey)) ?>" alt=""></div>
                        <div class="sig-line">
                            <div class="sig-name"><?= e($signName) ?></div>
                            <div class="sig-role"><?= e($signRole) ?></div>
                        </div>
                    </td>
                    <?php endforeach; ?>
                </tr>
            </table>
            </div>

            <p class="rc-note">
                <b>Note:</b> Examinee must bring this admit card to the examination hall.<br>
                <span class="text-xs">&copy; Copyright 1984 - <?= e($copyrightYear) ?> Bangladesh Kindergarten Association. All Rights Reserved. Developed by Web Space IT</span>
            </p>

            <div class="rc-foot">
                <div>Print Date: <?= e($printDate) ?></div>
            </div>
            </div>
        </div>
        </div>
      <?php endif; ?>
    </div>
    <?php endfor; ?>
</div>
<?php endforeach; ?>

<script src="/assets/js/vendor/qrcode.min.js"></script>
<script>
(function () {
    if (typeof QRCode === 'undefined') { return; }
    document.querySelectorAll('.reg-qr').forEach(function (box) {
        new QRCode(box, {
            text: box.getAttribute('data-qr-content'),
            width: 384, height: 384,
            correctLevel: QRCode.CorrectLevel.M
        });
        box.querySelectorAll('canvas, img, table').forEach(function (el) {
            el.style.width = '75px';
            el.style.height = '75px';
        });
    });
})();
</script>
</body>
</html>
