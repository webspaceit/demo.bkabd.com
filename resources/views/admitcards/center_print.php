<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<link rel="icon" href="<?= e(brand_asset('favicon_path', '/assets/img/result/logo.png')) ?>">
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

$examRoutines = $examRoutines ?? [];
$admitCardRulesTitle = brand('admit_card_rules_title');
$admitCardRules = preg_split('/\\R/u', brand('admit_card_rules'), -1, PREG_SPLIT_NO_EMPTY) ?: [];
$admitCardRules = array_values(array_filter(array_map('trim', $admitCardRules), static fn(string $line): bool => $line !== ''));
$groupRoutineRows = static function (array $rows): array {
    $groups = [];
    foreach ($rows as $routine) {
        $date = trim((string) ($routine['date'] ?? ''));
        $time = trim((string) ($routine['time'] ?? ''));
        $key = strtolower(preg_replace('/\s+/u', ' ', $date)) . "\0" . strtolower(preg_replace('/\s+/u', ' ', $time));
        if (!isset($groups[$key])) {
            $groups[$key] = $routine;
            $groups[$key]['_subjects'] = [];
        }
        $subjects = preg_split('/\s*,\s*/u', trim((string) ($routine['subject'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($subjects as $subject) {
            $subjectKey = strtolower(trim($subject));
            if ($subjectKey !== '' && !isset($groups[$key]['_subject_keys'][$subjectKey])) {
                $groups[$key]['_subject_keys'][$subjectKey] = true;
                $groups[$key]['_subjects'][] = trim($subject);
            }
        }
    }
    foreach ($groups as &$group) {
        $group['subject'] = implode(', ', $group['_subjects']);
        unset($group['_subjects'], $group['_subject_keys']);
    }
    unset($group);
    return array_values($groups);
};
$examRoutineRows = static function (string|int $session, int $class) use ($examRoutines, $groupRoutineRows): array {
    $sessionRows = array_values(array_filter($examRoutines, static function (array $routine) use ($session): bool {
        return (string) ($routine['session'] ?? '') === (string) $session;
    }));
    $matchesClass = static function (array $rows) use ($class): array {
        return array_values(array_filter($rows, static function (array $routine) use ($class): bool {
            $routineClass = trim((string) ($routine['class'] ?? ''));
            return $routineClass !== '' && (int) $routineClass === $class;
        }));
    };
    $matchesAllClasses = static function (array $rows): array {
        return array_values(array_filter($rows, static function (array $routine): bool {
            return trim((string) ($routine['class'] ?? '')) === '';
        }));
    };
    $rows = $matchesClass($sessionRows);
    if ($rows === []) { $rows = $matchesAllClasses($sessionRows); }
    return $groupRoutineRows($rows);
};
$row = static function (string $label, $value): string {
    return '<div class="rc-row"><span class="rc-lbl">' . e($label) . '</span>'
        . '<span class="rc-sep">:</span>'
        . '<span class="rc-val">' . e((string)($value ?? '')) . '</span></div>';
};

$brandPhone = brand('phone');
$brandEmail = brand('email');
$brandWebsite = brand('website');
$qrContent = implode("\n", [
    brand('authority_name'),
    brand('registration_label') . ': ' . brand('registration_no'),
    brand('address') . ($brandPhone !== '' ? ', Mobile: ' . $brandPhone : ''),
    ($brandEmail !== '' ? 'Email: ' . $brandEmail : '') . ($brandWebsite !== '' ? ', Web: ' . $brandWebsite : ''),
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
            "<?= brand_asset('pattern_path', '/assets/img/result/pattern.png') ?>",
            "<?= brand_asset('watermark_path', '/assets/img/result/for-registration.png') ?>",
        ],
        [brand_asset('pattern_path', '/assets/img/result/pattern.png'), brand_asset('watermark_path', '/assets/img/result/for-registration.png')],
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
    background: linear-gradient(110deg, #064e3b 0%, #065f46 100%); color: #fff;
    padding: .65rem 0;
    position: sticky; top: 0; z-index: 100;
    font-family: Arial, sans-serif;
    box-shadow: 0 2px 10px rgba(15, 23, 42, .18);
}
.cp-toolbar-inner {
    width: 100%; max-width: 72rem; margin: 0 auto; padding: 0 1rem;
    box-sizing: border-box; display: flex; align-items: center; justify-content: space-between; gap: 1rem;
}
.cp-toolbar h2 { margin: 0 0 .15rem; font-size: .95rem; line-height: 1.25; font-weight: 700; letter-spacing: .01em; }
.cp-toolbar small { display: inline-block; font-size: .72rem; line-height: 1.2; color: #d1fae5; }
.cp-toolbar-actions { display: flex; align-items: center; gap: .55rem; flex-shrink: 0; }
.cp-toolbar button {
    background: #fff; color: #064e3b; border: 1px solid #fff;
    border-radius: 7px; padding: .5rem .85rem;
    font-size: .8rem; font-weight: 700; cursor: pointer;
    box-shadow: 0 1px 2px rgba(0,0,0,.12); transition: background .15s ease, transform .15s ease;
}
.cp-toolbar button:hover { background: #ecfdf5; transform: translateY(-1px); }
.cp-toolbar a {
    display: inline-flex; align-items: center; gap: .4rem;
    padding: .5rem .75rem; border: 1px solid rgba(255,255,255,.5);
    border-radius: 7px; color: #fff; text-decoration: none;
    font-size: .8rem; font-weight: 700; white-space: nowrap; transition: background .15s ease;
}
.cp-toolbar a:hover { background: rgba(255,255,255,.12); }
.cp-toolbar a:focus-visible, .cp-toolbar button:focus-visible { outline: 2px solid #a7f3d0; outline-offset: 2px; }
.cp-page-counter {
    position: fixed; right: 1.25rem; bottom: 1.25rem; z-index: 110;
    padding: .55rem .85rem; border: 1px solid rgba(255,255,255,.2); border-radius: 999px;
    background: #064e3b; color: #fff; box-shadow: 0 4px 14px rgba(15,23,42,.22);
    font: 700 .8rem/1 Arial, sans-serif; letter-spacing: .02em;
}

/* One A4 page = 2 card slots */
.cp-page {
    width: 210mm;
    min-height: 297mm;
    height: auto;
    margin: 8px auto;
    background: #fff;
    overflow: visible;
    display: flex;
    flex-direction: column;
    box-shadow: 0 2px 12px rgba(0,0,0,.25);
}
.cp-page.single { width: 210mm; min-height: 148.5mm; height: auto; margin: 0 auto; }

/* Each card slot = exactly half A4 */
.cp-slot {
    width: 210mm;
    min-height: 148.5mm;
    height: auto;
    overflow: visible;
    flex-shrink: 0;
    position: relative;
}
.cp-slot + .cp-slot {
    border-top: 2px dashed #999;
}

.cp-slot .rc-page {
    width: 210mm !important;
    height: auto !important;
    min-height: 148.5mm !important;
    margin: 0 !important;
    box-shadow: none !important;
    padding: 0.15in !important;
    overflow: visible;
    box-sizing: border-box;
}
.cp-slot .reg-card { height: auto; min-height: 129.6mm; display: flex; flex-direction: column; box-sizing: border-box; }
.cp-slot .rc-inner { height: auto; min-height: 125.8mm; flex: 1 0 auto; box-sizing: border-box; }
.cp-slot .rc-inner { display: flex; flex-direction: column; justify-content: flex-start; }
.cp-slot .rc-banner { gap: .55rem; padding: .1rem .7rem 2px; margin-bottom: .35rem; }
.cp-slot .rc-banner { border-bottom: 1px solid #065f46; }
.cp-slot .rc-banner::after { display: none; }
.cp-slot .rc-qr { display: flex; align-items: center; justify-content: center; align-self: center; height: 70px; }
.cp-slot .rc-logo img { width: 70px; height: 70px; object-fit: contain; }
.cp-slot .rc-banner > div:not(.rc-qr) { flex: 1 1 auto; min-width: 0; text-align: center; }
.cp-slot .rc-banner h1 { font-size: 1.75rem; }
.cp-slot .rc-reg { font-size: .95rem; }
.cp-slot .rc-banner .rc-gov { font-size: .85rem; line-height: 1.35; }
.cp-slot .rc-qr canvas,
.cp-slot .rc-qr img,
.cp-slot .rc-qr table { width: 70px !important; height: 70px !important; }
.cp-slot .rc-exam-head { font-size: 1.7rem; margin: .2rem 0 .6rem; }
.cp-slot .rc-top { padding: .15rem .7rem .15rem; margin-bottom: .5rem; }
.cp-slot .rc-tag { padding: .1rem .55rem; font-size: .8rem; }
.cp-slot .rc-center .rc-pill { padding: .15rem .8rem; font-size: 1.5rem; }
.cp-slot .rc-body { padding: .25rem .7rem 0; gap: .6rem; margin: 0 0 .25rem !important; }
.cp-slot .rc-col { flex: 1 1 0; min-width: 0; }
.cp-slot .rc-row { grid-template-columns: 9.5rem 1rem 1fr; font-size: .95rem; line-height: 1.2; padding: .03rem 0; }
.cp-slot .rc-photo img { width: 60px; height: 70px; }
/* Full-width rules, styled like the individual admit card. */
.cp-slot .rc-routine { width: calc(100% - 1.4rem); margin: .1rem .7rem .3rem; }
.cp-slot .rc-routine-table { font-size: .82rem; line-height: 1.2; }
.cp-slot .rc-routine-table th,
.cp-slot .rc-routine-table td { padding: .15rem .3rem; }
.cp-slot .rc-rules { width: auto; margin: .25rem 0 .25rem .7rem !important; font-size: 1rem !important; line-height: 1.22 !important; }
.cp-slot .rc-routine-title { font-size: .95rem; font-weight: 700; color: #0f172a; }
.cp-slot .rc-rules p { margin: 0 0 .15rem; }
.cp-slot .rc-rules > p:first-child { font-size: .95rem !important; line-height: 1.2; font-weight: 700; color: #0f172a; }
.cp-slot .rc-rules p:last-child { margin-bottom: 0; white-space: nowrap; font-size: 1rem; }
.cp-slot .rc-rules ol { margin: 0; padding-left: .8rem; }
.cp-slot .sig-tbl { margin-top: .25rem !important; }
.cp-slot .overflow-x-auto { margin-top: 0; }
.cp-slot .sig-tbl { width: 100%; table-layout: fixed; border-collapse: collapse; }
.cp-slot .overflow-x-auto { width: 100%; }
.cp-slot .sig-img img { max-width: 90px; height: auto; margin: 0 auto; display: block; }
.cp-slot .sig-name { font-size: .82rem; }
.cp-slot .sig-role { font-size: .68rem; }
.cp-slot .rc-note { font-size: .75rem; padding: .12rem .7rem 0; line-height: 1.15; }
.cp-slot .rc-foot { margin-top: auto; padding: .1rem .7rem .15rem; font-size: .75rem; }

/* The card keeps its print proportions in the browser's narrow viewport. */
@media screen and (max-width: 900px) {
    .cp-page { zoom: .68; }
    .cp-toolbar-inner { flex-wrap: wrap; }
    .cp-toolbar-actions { flex-wrap: wrap; }
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

/* On phones, reflow the preview cards to the viewport instead of shrinking a
   fixed-width A4 sheet. The print stylesheet below keeps the paper layout. */
@media screen and (max-width: 600px) {
    .cp-page,
    .cp-page.single {
        width: 100%;
        min-height: 0;
        height: auto;
        margin: 8px 0;
        zoom: 1;
    }
    .cp-slot {
        width: 100%;
        min-height: 0;
        height: auto;
        padding: 8px;
        box-sizing: border-box;
        overflow: visible;
    }
    .cp-slot .rc-page {
        width: 100% !important;
        height: auto !important;
        min-height: 0 !important;
        padding: 0 !important;
    }
    .cp-slot .reg-card,
    .cp-slot .rc-inner {
        height: auto;
        min-height: 0;
    }
    .cp-slot .rc-banner {
        flex-wrap: wrap;
        justify-content: center;
        white-space: normal;
    }
    .cp-slot .rc-banner > div:not(.rc-qr) {
        flex: 1 1 150px;
        min-width: 0;
    }
    .cp-slot .rc-banner h1,
    .cp-slot .rc-banner .rc-gov {
        white-space: normal;
        overflow-wrap: anywhere;
    }
    .cp-slot .rc-top {
        flex-direction: column;
        align-items: stretch;
    }
    .cp-slot .rc-tags,
    .cp-slot .rc-roll {
        min-width: 0;
        width: 100%;
        margin-left: 0;
    }
    .cp-slot .rc-body {
        flex-direction: column;
    }
    .cp-slot .rc-body-admit {
        display: flex;
        flex-direction: column;
    }
    .cp-slot .rc-body-admit .rc-col {
        width: 100%;
    }
    .cp-slot .rc-body-admit .rc-row {
        grid-template-columns: minmax(0, 7rem) .75rem minmax(0, 1fr);
    }
    .cp-slot .rc-rules p:last-child {
        white-space: normal;
        overflow-wrap: anywhere;
    }
    .cp-slot .sig-tbl,
    .cp-slot .sig-tbl tbody {
        display: block;
    }
    .cp-slot .sig-tbl tr {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: .6rem .5rem;
    }
    .cp-slot .sig-tbl td {
        display: flex;
        padding: 0 .2rem;
    }
}

/* Hide py-6 wrapper padding */
.cp-slot .py-6 { padding: 0 !important; }

@media print {
    html, body { background: #fff; }
    .cp-toolbar { display: none; }
    .cp-page-counter { display: none !important; }
    .cp-page {
        height: 297mm;
        min-height: 297mm;
        margin: 0;
        box-shadow: none;
        overflow: hidden;
        break-after: auto;
        page-break-after: auto;
    }
    .cp-slot {
        height: 148.5mm;
        min-height: 0;
        flex: 0 0 148.5mm;
        box-sizing: border-box;
        /* Keep neighbouring card frames from sharing a rule. Flush 148.5mm
           slots stacked the first card's bottom border on the next header. */
        padding: 2mm 2.5mm;
        overflow: hidden;
    }
    .cp-slot + .cp-slot { border-top: 0; }
    .cp-page + .cp-page {
        break-before: page;
        page-break-before: always;
    }
    .cp-slot .rc-page {
        width: 100% !important;
        height: 100% !important;
        min-height: 0 !important;
        padding: 0 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .cp-slot .rc-banner { border-bottom: 1px solid #065f46; }
}
</style>
</head>
<body>

<div class="cp-toolbar">
    <div class="cp-toolbar-inner">
        <div>
            <h2><?= e($printTitle ?? ('Admit Cards — ' . ($centerName ?? ''))) ?> &nbsp;|&nbsp; Session <?= e($session) ?></h2>
            <small><?= $total ?> students &nbsp;·&nbsp; <?= ceil($total / 2) ?> pages</small>
        </div>
        <div class="cp-toolbar-actions">
            <a href="/admitcards" onclick="if (history.length > 1 && document.referrer && new URL(document.referrer).origin === location.origin) { history.back(); return false; }">&#8592; Back</a>
            <button onclick="window.print()">&#128424; Print / Save as PDF</button>
        </div>
    </div>
</div>

<?php foreach ($pairs as $pageIndex => $pair): ?>
<div class="cp-page<?= $singleStudent ? ' single' : '' ?>" data-page="<?= $pageIndex + 1 ?>">
    <?php for ($slot = 0; $slot < ($singleStudent ? 1 : 2); $slot++):
        $s = $pair[$slot] ?? null;
        $photoSrc = $s ? $resolveStudentPhoto((array)$s) : null;
    ?>
    <div class="cp-slot">
      <?php if ($s): ?>
        <div class="rc-page">
        <div class="reg-card border-2 border-black bg-white">
            <div class="rc-inner border-2 border-black">
            <?php if (brand('header_image_path') !== ''): ?>
                <img class="rc-brand-art rc-brand-art-header" src="<?= e(brand_asset('header_image_path', '')) ?>" alt="">
            <?php endif; ?>
            <div class="rc-banner">
                <span class="rc-logo"><img src="<?= e(brand_asset('logo_path', '/assets/img/result/logo.png')) ?>" alt="<?= e(brand('short_name')) ?>" onerror="this.style.display='none'"></span>
                <div>
                    <h1><?= e(brand('authority_name')) ?></h1>
                    <p class="rc-reg"><?= e(brand('registration_label')) ?>: <?= e(brand('registration_no')) ?></p>
                    <p class="rc-gov">
                        <?= e(brand('address')) ?><?= $brandPhone !== '' ? ', Mobile: ' . e($brandPhone) : '' ?><br>
                        <?= $brandEmail !== '' ? 'Email: ' . e($brandEmail) : '' ?><?= $brandWebsite !== '' ? ', Web: ' . e($brandWebsite) : '' ?>
                    </p>
                </div>
                <div class="rc-qr">
                    <div class="reg-qr inline-block" data-qr-content="<?= $qrContentEscaped ?>"></div>
                </div>
            </div>

            <p class="rc-exam-head"><?= e(brand('exam_name')) ?> - <?= e($s['session'] ?? $session) ?></p>

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
                <?= $row('Exam Center', $s['center_name'] ?? '') ?>
                <?= $row('Institute Name', $s['institute_name'] ?? '') ?>
                <?php if (!empty($s['seat_assignment']['room_label'])): ?>
                    <?= $row('Seat Location', $s['seat_assignment']['room_label']) ?>
                <?php endif; ?>
                </div>
                <div class="rc-photo">
                    <?php if ($photoSrc): ?><img src="<?= $photoSrc ?>" alt="Student photo"><?php endif; ?>
                </div>
            </div>

            <div class="rc-routine">
                <div class="rc-routine-title">Exam Routine</div>
                <table class="rc-routine-table">
                    <thead><tr><th>Subject</th><th>Exam Date</th><th>Day</th><th>Time</th></tr></thead>
                    <tbody>
                    <?php $studentRoutineRows = $examRoutineRows($s['session'] ?? $session, (int) ($s['class'] ?? 0)); ?>
                    <?php if ($studentRoutineRows === []): ?>
                        <tr><td colspan="4">Not scheduled</td></tr>
                    <?php else: foreach ($studentRoutineRows as $routineItem): ?>
                        <tr>
                            <td><?= e($routineItem['subject'] ?? '') ?></td>
                            <td><?= e($routineItem['date'] ?? '') ?></td>
                            <td><?= e($routineItem['day'] ?? '') ?></td>
                            <td><?= e($routineItem['time'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="rc-rules">
                <?php if (trim($admitCardRulesTitle) !== ''): ?><p class="font-bold mb-1 text-slate-800"><?= e($admitCardRulesTitle) ?></p><?php endif; ?>
                <?php foreach ($admitCardRules as $instruction): ?><p><?= e($instruction) ?></p><?php endforeach; ?>
            </div>

            <div class="overflow-x-auto">
            <table class="sig-tbl mt-4 w-full text-[10px] sm:text-xs">
                <tr>
                    <?php foreach ([
                        ['chairman',            brand('chairman_name'), brand('chairman_title')],
                        ['general_secretary',   brand('general_secretary_name'), brand('general_secretary_title')],
                        ['controller',          brand('controller_name'), brand('controller_title')],
                        ['education_secretary', brand('education_secretary_name'), brand('education_secretary_title')],
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
                <b>Note:</b> This admit card is valid upto this session. Examinee must bring this card in the examination hall.<br>
                <span class="text-xs">&copy; <?= e($copyrightYear) ?> <?= e(brand('authority_name')) ?>. <?= e(brand('copyright_text')) ?> <?= e(brand('developer_credit')) ?></span>
            </p>

            <?php if (brand('footer_image_path') !== ''): ?><img class="rc-brand-art rc-brand-art-footer" src="<?= e(brand_asset('footer_image_path', '')) ?>" alt=""><?php endif; ?>
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

<?php if (count($pairs) > 1): ?>
<div class="cp-page-counter" role="status" aria-live="polite">Page 1 of <?= count($pairs) ?></div>
<script>
(function () {
    var pages = Array.from(document.querySelectorAll('.cp-page[data-page]'));
    var counter = document.querySelector('.cp-page-counter');
    if (!counter || pages.length < 2) return;

    var framePending = false;
    function updatePageNumber() {
        var viewportCenter = window.innerHeight / 2;
        var currentPage = 1;
        var nearestDistance = Infinity;
        pages.forEach(function (page) {
            var rect = page.getBoundingClientRect();
            var distance = rect.top <= viewportCenter && rect.bottom >= viewportCenter
                ? 0
                : Math.min(Math.abs(rect.top - viewportCenter), Math.abs(rect.bottom - viewportCenter));
            if (distance < nearestDistance) {
                nearestDistance = distance;
                currentPage = Number(page.dataset.page);
            }
        });
        counter.textContent = 'Page ' + currentPage + ' of ' + pages.length;
        framePending = false;
    }
    window.addEventListener('scroll', function () {
        if (!framePending) {
            framePending = true;
            window.requestAnimationFrame(updatePageNumber);
        }
    }, { passive: true });
    updatePageNumber();
})();
</script>
<?php endif; ?>

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
            el.style.width = '70px';
            el.style.height = '70px';
        });
    });
})();
</script>
</body>
</html>
