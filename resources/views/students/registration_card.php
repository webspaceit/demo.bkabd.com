<?php
/**
 * Printable student registration card — GET /api/registrationpdf/{registration}
 * Styled after the association's card format (attached reference): light card,
 * banner header (logo + bilingual association name), Reg No / Exam Roll tag
 * boxes on the left, exam heading + card pill centred, student photo top
 * right, and a two-column details grid over a light watermark.
 * Renders inside layouts.guest (same shell as /scholarship-result).
 *
 * @var array $student  row from students joined with institute/center/geo names
 */
$cardMode    = ($cardMode ?? 'registration') === 'admit' ? 'admit' : 'registration';
$twoPerPage  = !empty($twoPerPage);
$isAdmitCard = $cardMode === 'admit';
$cardStudents = $students ?? (isset($student) ? [$student] : []);
$cardCount = count($cardStudents);
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

// The QR does not link back to this staff-only page; it carries the
// association's identity and contact details printed in the banner.
$qrContent = implode("\n", [
    'BANGLADESH KINDERGARTEN ASSOCIATION',
    'Govt. Reg. No.: S-1028/98',
    '59/3, Brammonchiron, Jatrabari, Dhaka-1203, Bangladesh, Mobile: +88-02-224458009',
    'Email: infobkabd@gmail.com, info@bkabd.com, Web: www.bkabd.com, www.bka.com.bd, www.bka.org.bd',
]);

/** A "Label : value" details row. */
$row = static function (string $label, $value): string {
    return '<div class="rc-row"><span class="rc-lbl">' . e($label) . '</span>'
        . '<span class="rc-sep">:</span>'
        . '<span class="rc-val">' . e((string) ($value ?? '')) . '</span></div>';
};

$resolveStudentPhoto = static function (array $student) use ($resolve): ?string {
    $photoSrc = $resolve((string) ($student['photo'] ?? ''));
    if ($photoSrc === null) {
        $placeholder = ((int) ($student['gender'] ?? 0) === 2) ? 'female.jpeg' : 'male.jpeg';
        $photoSrc = $resolve($placeholder);
    }
    return $photoSrc;
};

$className = static function (int $class): string {
    return match ($class) {
        6 => 'KG', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
        default => (string) $class,
    };
};

$printDate = date('d-m-Y');
$copyrightYear = date('Y');
$title = $title ?? ($isAdmitCard ? 'Admit Card' : 'Registration Card');
$cardLabel = $isAdmitCard ? 'Admit Card' : 'Student Registration Card';
$qrContentEscaped = e($qrContent);
?>
<style>
    /* ---- Card format (landscape, like the association's card) ----
       Same sheet design as the scholarship result sheet (result_show.php):
       the user-supplied repeating pattern fills the sheet; the watermark logo
       is painted on the inner frame so neither hides the other. */
    .reg-card {
        max-width: 820px;
        margin: 0 auto;
        padding: .4125rem;
        background-image: url("<?= asset_v('/assets/img/result/pattern.png') ?>");
        background-repeat: repeat;
        background-position: 0 0;
        background-size: 424px 424px;
    }

    /* Watermark logo, centred and painted over the pattern (same as the
       result sheet's .rs-inner). */
    .rc-inner {
        background-image: url("<?= asset_v('/assets/img/result/for-registration.png') ?>");
        background-repeat: no-repeat;
        background-position: center center;
        background-size: 220px 220px;
    }

    /* ---- Banner header (transparent so the pattern + watermark show through) ---- */
    .rc-banner {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: .8rem 1.2rem .7rem;
        border-bottom: double 3px #065f46;
    }
    /* Header logo — natural size (the asset is 90x90), no frame. */
    .rc-logo {
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .rc-logo img { width: 90px; height: 90px; object-fit: contain; }
    .rc-banner h1 {
        margin: 0;
        font-size: 1.5rem;
        line-height: 1.15;
        font-weight: 800;
        color: #0f172a;
        letter-spacing: .01em;
        white-space: nowrap;
    }
    .rc-banner .rc-gov {
        margin-top: .15rem;
        font-size: .8rem;
        line-height: 1.45;
        font-weight: 500;
        color: #000;
        text-align: center;
    }

    /* ---- Top row: Reg No tag | heading + pill | Exam Roll tag ---- */
    .rc-top {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem 1.2rem .4rem;
    }
    .rc-tags { display: flex; flex-direction: column; gap: .55rem; min-width: 13rem; }
    .rc-tag {
        border: 2px solid #0f172a;
        border-radius: 9999px;
        background:transparent;
        padding: .3rem .9rem;
        font-size: .95rem;
        font-weight: 700;
        color: #0f172a;
        white-space: nowrap;
    }
    .rc-roll { margin-left: auto; align-self: center; min-width: 13rem; text-align: left; }
    /* Scholarship Exam heading sits on its own line above the tag row, so the
       Reg No tag, the Registration Card pill and the Exam Roll tag all share
       one horizontal line. */
    .rc-exam-head {
        margin: .5rem 0 .2rem;
        text-align: center;
        font-size: 1.45rem;
        font-weight: 800;
        color: #0f172a;
    }
    .rc-center { flex: 1; text-align: center; min-width: 0; }
    .rc-center .rc-pill {
        display: inline-block;
        border: 2px solid #0f172a;
        border-radius: 9999px;
        background: transparent;
        padding: .35rem 1.6rem;
        font-size: 1.05rem;
        font-weight: 700;
        color: #0f172a;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .15);
    }
    /* Student photo — third block of the details row, spanning its rows.
       64x74px = the 80x93 size reduced by 20%. */
    .rc-photo {
        flex: 0 0 auto;
        align-self: center;
    }
    .rc-photo img {
        display: block;
        width: 64px;
        height: 74px;
        object-fit: cover;
        border: 2px solid #94a3b8;
        background: #fff;
        border: double;
    }

    /* ---- Details row: columns size to their content and spread across the
       full width (dynamic, like the wide Institute Address line) ---- */
    .rc-body {
        display: flex;
        align-items: stretch;
        justify-content: space-between;
        gap: 1.5rem;
        padding: .9rem 1.2rem 0;
        margin-top: .5rem;
        margin-bottom: .6rem;
    }
    /* The two text columns use one identical standard line pitch — no
       per-column stretching, so both blocks read at the same rhythm. */
    .rc-col {
        flex: 0 0 auto;
        display: grid;
        row-gap: .3rem;
        align-content: start;
    }
    .rc-row {
        display: grid;
        grid-template-columns: 9.5rem 1rem 1fr;
        align-items: baseline;
        padding: .18rem 0;
        font-size: .95rem;
        line-height: 1.35;
    }

    /* Wide row: spans under both text columns so long values (Institute
       Address) stay on a single line. */
    .rc-body-wide { display: block; padding: 0 1.2rem .4rem; }
    .rc-body-wide .rc-row { grid-column: 1 / span 2; }
    .rc-body-wide .rc-val { white-space: nowrap; }
    .rc-lbl { font-weight: 700; color: #0f172a; }
    .rc-sep { color: #0f172a; text-align: center; }
    .rc-val { font-weight: 600; color: #111827; word-break: break-word; }

    /* ---- QR (banner right, on the blank space) ---- */
/* The QR payload is the whole contact block (~236 bytes -> 61 modules), so
        it needs ~75px to stay scannable on paper (~0.38mm per module). */
    .rc-qr { text-align: center; margin-left: auto; align-self: center; flex-shrink: 0; }
    .rc-qr canvas, .rc-qr img, .rc-qr table { width: 75px !important; height: 75px !important; }

    .rc-reg {
        margin: 0;
        padding: 0;
        text-align: center;
        font-size: .9rem;
        font-weight: 700;
        color: #0f172a;
    }

    .rc-welcome {
        text-align: center;
        font-size: .82rem;
        line-height: 1.4;
        color: #000;
        padding: .4rem 1.2rem 0;
    }

    /* ---- Signature block (natural image size, same signatories as the result sheet) ---- */
    .sig-tbl td { vertical-align: bottom; text-align: center; padding: 0 .75rem; }
    .sig-img img { max-width: 90px; height: auto; margin: 0 auto; display: block; }
    .sig-line { border-top: 1px solid #000; margin-top: .25rem; padding-top: .25rem; width: 82%; margin-left: auto; margin-right: auto; }
    .sig-name { font-weight: 600; font-size: 1.05em; line-height: 1.2; color: #000; }
    .sig-role { color: #000; font-size: 95%; line-height: 1.2; }

    /* ---- Footer ---- */
    .rc-note {
        clear: both;
        position: relative;
        text-align: center;
        font-size: .92rem;
        color:#000;
        padding: .6rem 1.2rem 0;
    }
    .rc-foot {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        font-size: .78rem;
        color: #000;
        padding: .5rem 1.2rem .8rem;
    }

    /* Portrait A4 sheet, 0.15in (~3.8mm) margins on all sides. */
    @page { size: A4 portrait; margin: 0.15in; }

    /* Root font scale — rem-based sizes use the print scale on screen too,
       so the sheet matches the PDF/print output exactly. */
    html { font-size: 12.5px; }

    /* A4 paper canvas: the card renders on a real A4 portrait sheet with the
       same 0.15in page margin the PDF gets. */
    .rc-page {
        width: 210mm;
        min-height: 297mm;
        margin: 0 auto .75rem;
        background: #fff;
        padding: 0.15in;
        box-sizing: border-box;
        box-shadow: 0 2px 10px rgba(15, 23, 42, .35), 0 0 1px rgba(15, 23, 42, .4);
    }

    .reg-card {
        max-width: none;
        margin: 0;
        break-inside: avoid;
        page-break-inside: avoid;
    }
    .reg-card, .reg-card * {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

/* Compact print scale (applies everywhere so screens match the PDF).
        Header text scaled so the contact lines fill the space up to the QR;
        the QR keeps the 75px size set by the .rc-qr rule (its payload is the
        whole contact block, so it needs the room to stay scannable). */
    .rc-banner { padding: .5rem 1rem .4rem; margin-bottom: 1rem; }
    .rc-logo img { width: 68px; height: 68px; }
    /* The QR now carries the whole contact block, so it is sized to 75px
        (scannable on paper). The heading and the contact line are scaled down a
        notch so the row (logo + text + QR) still fits inside the 210mm sheet. */
    .rc-banner h1 { font-size: 1.8rem; }
    .rc-reg { font-size: .85rem; }
    .rc-banner .rc-gov { font-size: .8rem; line-height: 1.6; white-space: nowrap; }
    .rc-top { padding: .45rem 1rem .1rem; }
    .rc-tag { padding: .15rem .75rem; font-size: .85rem; }
    .rc-exam-head { font-size: 1.5rem; margin-bottom: 1rem; }
    .rc-center .rc-pill { padding: .2rem 1.2rem; font-size: .95rem; background: #000; color: #fff; }
    .rc-photo img { width: 74px; height: 84px; }
    .rc-body { padding: .35rem 1rem 0; column-gap: 1.8rem; row-gap: 0; }
    .rc-row { font-size: .8rem; padding: .06rem 0; grid-template-columns: 8.2rem 1rem 1fr; }
    .rc-qr canvas, .rc-qr img, .rc-qr table { width: 75px !important; height: 75px !important; }
    .sig-tbl { margin-top: .2rem !important; }
    .sig-name { font-size: .95em; }
    .rc-welcome { font-size: .7rem; padding-top: .25rem; }
    .rc-note { font-size: .8rem; padding-top: .25rem; }
    .rc-foot { padding: .25rem 1rem .45rem; font-size: .68rem; }

    /* ---- Phones / small tablets ----
       The sheet is 210mm wide so print and desktop match the reference, but
       that forces a horizontal scrollbar on a phone. Below 900px the layout
       reflows to the viewport width instead: the banner heading wraps, the
       Reg No / Exam Roll tags stack, the two detail columns become one, and
       the four signatures go 2 x 2. (Paper is unaffected — the @media print
       block below carries the 210mm sheet through to the printer.) */
    @media screen and (max-width: 900px) {
        .rc-page {
            width: 100%;
            min-height: 0;
            padding: .5rem;
            box-shadow: 0 1px 4px rgba(15, 23, 42, .25);
        }

        .rc-banner { flex-wrap: wrap; gap: .5rem; padding: .5rem .5rem .4rem; margin-bottom: .6rem; }
        .rc-logo img { width: 52px; height: 52px; }
        .rc-banner h1 { font-size: 1.05rem; line-height: 1.2; white-space: normal; }
        .rc-banner .rc-gov { font-size: .68rem; line-height: 1.5; white-space: normal; overflow-wrap: anywhere; }
        .rc-reg { font-size: .72rem; }
        .rc-qr canvas, .rc-qr img, .rc-qr table { width: 75px !important; height: 75px !important; }

        .rc-welcome { font-size: .7rem; padding: .4rem .5rem 0; }
        .rc-exam-head { font-size: 1.05rem; margin: .5rem 0 .2rem; }

        .rc-top { flex-direction: column; align-items: stretch; gap: .5rem; padding: .5rem .5rem .2rem; }
        .rc-tags, .rc-roll { min-width: 0; }
        .rc-roll { margin-left: 0; }
        .rc-tag { white-space: normal; text-align: center; }
        .rc-center { width: 100%; }
        .rc-center .rc-pill { font-size: .95rem; padding: .3rem 1rem; }

        .rc-body { flex-direction: column; gap: .75rem; padding: .5rem .5rem 0; margin-top: .25rem; }
        .rc-col { width: 100%; }
        .rc-row { grid-template-columns: 8rem .75rem 1fr; font-size: .82rem; }
        .rc-body-wide { padding: 0 .5rem .4rem; }
        .rc-body-wide .rc-val { white-space: normal; }
        .rc-photo { align-self: center; }
        .rc-photo img { width: 78px; height: 90px; }

        /* Four signatures across would be cramped: 2 x 2. The tbody has to
           follow the table into block flow, otherwise the browser wraps the
           grid in an anonymous inline-table that shrink-wraps (the row came
           out 234px inside a 353px sheet). Bottom-aligning each cell's
           content reproduces the table's `vertical-align: bottom`, so the
           four signature lines share a baseline as they do on desktop. */
        .sig-tbl, .sig-tbl tbody { display: block; }
        .sig-tbl tr { display: grid; grid-template-columns: 1fr 1fr; column-gap: .5rem; row-gap: .6rem; }
        .sig-tbl td { display: flex; flex-direction: column; justify-content: flex-end; padding: 0 .25rem; }

        .rc-note { font-size: .72rem; padding: .5rem .5rem 0; }
        .rc-foot { flex-direction: column; align-items: center; gap: .15rem; text-align: center; font-size: .65rem; padding: .25rem .5rem .45rem; }
    }

    @media print {
        html, body { background: #fff !important; }
        body > header, body > footer, #toast-stack { display: none !important; }
        .no-print { display: none !important; }
        .rc-pages.multiple > .py-6:not(:last-child) .rc-page { break-after: page; page-break-after: always; }
        body.print-one-admit-card .rc-page:not(.print-target) { display: none !important; }
        body.print-one-admit-card .rc-page.print-target { break-after: auto !important; page-break-after: auto !important; }
        /* The thank-you line is screen-only; with it hidden the Scholarship
           Exam heading below flows up to where the sentence was. */
        .rc-welcome { display: none !important; }

        /* The sheet becomes the paper itself — no shadow, no extra padding
           (the @page margin already provides the 0.15in). */
        .rc-page { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
    }

    /* ---- Two-per-page mode (center-wide admit card print) ---- */
    <?php if ($twoPerPage): ?>
    .rc-pages { display: flex; flex-direction: column; gap: 0; }
    .rc-pages > .py-6 { padding: 0 !important; }
    .rc-page {
        width: 210mm !important;
        height: 148.5mm !important;
        min-height: 148.5mm !important;
        max-height: 148.5mm !important;
        overflow: hidden;
        margin: 0 !important;
        box-shadow: none !important;
        border-bottom: 1.5px dashed #94a3b8 !important;
    }
    .rc-pages > .py-6:nth-child(even) .rc-page { border-bottom: none !important; }
    .rc-pages > .py-6:nth-child(2n) { break-after: page; page-break-after: always; }
    .rc-pages > .py-6:last-child    { break-after: auto; page-break-after: auto; }
    /* Compress spacing to fit half-A4 */
    .rc-banner { padding: .3rem .8rem; margin-bottom: .3rem; }
    .rc-logo img { width: 50px; height: 50px; }
    .rc-banner h1 { font-size: 1.1rem; }
    .rc-banner .rc-gov { font-size: .62rem; }
    .rc-exam-head { font-size: 1rem; margin: .25rem 0 .15rem; }
    .rc-top { padding: .15rem .8rem .05rem; }
    .rc-tag { font-size: .72rem; padding: .08rem .55rem; }
    .rc-center .rc-pill { font-size: .78rem; padding: .12rem .85rem; }
    .rc-body { padding: .15rem .8rem 0; gap: 1rem; }
    .rc-row { font-size: .7rem; padding: .03rem 0; grid-template-columns: 7.5rem .8rem 1fr; }
    .rc-photo img { width: 54px; height: 62px; }
    .rc-rules { margin: .2rem 1rem; }
    .rc-rules li, .rc-rules p { font-size: .58rem; line-height: 1.4; }
    .sig-tbl { margin-top: .15rem !important; }
    .sig-img img { max-width: 70px; }
    .sig-name { font-size: .72em; }
    .sig-role { font-size: .68em; }
    .rc-note { font-size: .6rem; padding: .15rem .8rem 0; }
    .rc-foot { font-size: .55rem; padding: .1rem .8rem .2rem; }
    [data-print-card], .mt-4.flex.justify-center { display: none !important; }
    @media print {
        @page { size: A4 portrait; margin: 0; }
        html { font-size: 11.5px; }
        .rc-page {
            width: 210mm !important;
            height: 148.5mm !important;
            min-height: 148.5mm !important;
            max-height: 148.5mm !important;
            padding: 2mm 4mm !important;
            overflow: hidden !important;
            box-shadow: none !important;
        }
        /* Override the default one-per-page break */
        .rc-pages.multiple > .py-6:not(:last-child) .rc-page { break-after: avoid !important; page-break-after: avoid !important; }
        /* Break after every 2nd card wrapper */
        .rc-pages > .py-6:nth-child(2n) { break-after: page; page-break-after: always; }
        .rc-pages > .py-6:last-child    { break-after: auto; page-break-after: auto; }
    }
    <?php endif; ?>
</style>

<div class="rc-pages<?= $cardCount > 1 ? ' multiple' : '' ?>">
<?php foreach ($cardStudents as $student): ?>
<?php $photoSrc = $resolveStudentPhoto((array) $student); ?>
<div class="py-6 print:py-0">
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

        <?php if (!$isAdmitCard): ?>
        <p class="rc-welcome">Thank you. Your Registration has been completed successfully. You must save this Card. It will be required to participate in the next Scholarship Examination.</p>
        <?php endif; ?>

        <p class="rc-exam-head">Scholarship Exam - <?= e($student['session'] ?? '') ?></p>

        <div class="rc-top">
            <div class="rc-tags">
                <div class="rc-tag">Reg No.&nbsp;: <?= e($student['registration'] ?? '') ?></div>
            </div>
            <div class="rc-center">
                <div class="rc-pill"><?= e($cardLabel) ?></div>
            </div>
            <div class="rc-tag rc-roll">Exam Roll&nbsp;: <?= e($student['roll'] ?? '') ?></div>
        </div>

        <div class="rc-body">
            <?php if ($isAdmitCard): ?>
            <div class="rc-col">
                <?= $row('Name', $student['name'] ?? '') ?>
                <?= $row("Father's Name", $student['father_name'] ?? '') ?>
                <?= $row("Mother's Name", $student['mother_name'] ?? '') ?>
                <?= $row('Date of Birth', !empty($student['dob']) ? DateFormat($student['dob'], 'd-m-Y') : '') ?>
                <?= $row('Registration No.', $student['registration'] ?? '') ?>
            </div>
            <div class="rc-col">
                <?= $row('Roll No.', $student['roll'] ?? '') ?>
                <?= $row('Exam Class', $className((int) ($student['class'] ?? 0))) ?>
                <?= $row('Exam Date & Time', 'As per routine') ?>
                <?= $row('Exam Center', $student['center_name'] ?? '') ?>
                <?= $row('Exam Venue', $student['institute_name'] ?? '') ?>
            </div>
            <?php else: ?>
            <div class="rc-col">
                <?= $row('Name', $student['name'] ?? '') ?>
                <?= $row("Father's Name", $student['father_name'] ?? '') ?>
                <?= $row("Mother's Name", $student['mother_name'] ?? '') ?>
                <?= $row('Exam Subject', 'Bangla, English, Mathematics') ?>
                <?= $row('Exam Center', $student['center_name'] ?? '') ?>
                <?= $row('Institute Name', $student['institute_name'] ?? '') ?>            
                <?= $row('Institute Address', $student['institute_address'] ?? '') ?>
            </div>
            <div class="rc-col">
                <?= $row('Gender', GenderName((int) ($student['gender'] ?? 0))) ?>
                <?= $row('Class', ClassName((int) ($student['class'] ?? 0))) ?>
                <?= $row('Mobile', $student['phone'] ?? '') ?>
                <?= $row('Date of Birth', !empty($student['dob']) ? DateFormat($student['dob'], 'd-m-Y') : '') ?>
                <?= $row('Division', $student['division_name'] ?? '') ?>
                <?= $row('District', $student['district_name'] ?? '') ?>
                <?= $row('Thana/Upazila', $student['upazila_name'] ?? '') ?>
            </div>
            <?php endif; ?>
            <div class="rc-photo">
                <?php if ($photoSrc !== null): ?>
                    <img src="<?= $photoSrc ?>" alt="Student photo">
                <?php endif; ?>
            </div>
        </div>

        <!-- <div class="rc-body rc-body-wide">
            <?= $row('Institute Address', $student['institute_address'] ?? '') ?>
        </div> -->

        <?php if ($isAdmitCard): ?>
        <div class="rc-rules ml-4 my-3 text-[11px] sm:text-xs leading-relaxed">
            <p class="font-bold mb-1 text-slate-800">নিয়মাবলী:</p>
            <ol class="list-decimal list-inside space-y-0.5">
                <li>পরীক্ষা শুরুর কম পক্ষে ১৫ মিনিট আগে পরীক্ষা হলে উপস্থিত থাকতে হবে।</li>
                <li>পরীক্ষা হলে কোনো ধরনের কাগজপত্র বা বই নিয়ে প্রবেশ করা যাবে না।</li>
                <li>পরীক্ষা প্রবেশ পত্র ছাড়া কোনো শিক্ষার্থী পরীক্ষায় অংশগ্রহণ করতে পারবে না।</li>
                <li>পরীক্ষা হলে কোনো অনৈতিক বা অনাকাঙ্ক্ষিত ঘটনা ঘটানো যাবে না।</li>
                <li>পরীক্ষা সময় মোবাইল ফোন ব্যবহার করা যাবে না।</li>
            </ol>
        </div>
        <?php endif; ?>

        <div class="overflow-x-auto">
        <table class="sig-tbl mt-4 w-full text-[10px] sm:text-xs">
            <tr>
                <?php foreach ([
                    ['chairman',           'MONOWARA BHUIYAN',   'Chairman'],
                    ['general_secretary',  'MD. MIZANUR RAHMAN', 'General Secretary'],
                    ['controller',         'MD. FARUK HOSSEN',  'Controller of Exam'],
                    ['education_secretary','MD. ABDUL ALIM',    'Education Secretary'],
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
            <?php if ($isAdmitCard): ?>
            <b>Note:</b> Examinee must bring this admit card to the examination hall.
            <?php else: ?>
            <b>Note:</b> This registration card is valid upto this session. Examinee must bring this card in the examination hall.
            <?php endif; ?><br>
            <span class="text-xs">&copy; Copyright 1984 - <?= e($copyrightYear) ?> Bangladesh Kindergarten Association. All Rights Reserved. Developed by Web Space IT</span>
        </p>

        <div class="rc-foot">
            <?php if (!$isAdmitCard): ?>
            <div>Date of Registration &amp; Time: <?= e(DateFormat($student['created_at'] ?? '', 'd-m-Y, h:i A')) ?></div>
            <?php endif; ?>
            <div>Print Date: <?= e($printDate) ?></div>
        </div>
        </div>
    </div>
    </div>
    <?php if ($isAdmitCard): ?>
    <div class="mt-2 flex justify-center print:hidden">
        <button type="button" class="btn btn-primary" data-print-card
                data-session="<?= e($student['session'] ?? '') ?>"
                data-registration="<?= e($student['registration'] ?? '') ?>">Print / Save this card as PDF</button>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
</div>

<div class="mt-4 flex justify-center gap-3 print:hidden">
    <a href="javascript:history.back()" class="btn bg-slate-500 text-white hover:bg-slate-600">Back</a>
    <button type="button" onclick="window.print();" class="btn bg-emerald-500 text-white hover:bg-emerald-600">Print</button>
</div>

<script src="/assets/js/vendor/qrcode.min.js"></script>
<script>
(function () {
    if (typeof QRCode === 'undefined') { return; }
    Array.prototype.forEach.call(document.querySelectorAll('.reg-qr'), function (box) {
        new QRCode(box, {
            text: box.getAttribute('data-qr-content'),
            width: 384,
            height: 384,
            correctLevel: QRCode.CorrectLevel.M
        });
        // qrcodejs emits a canvas and/or an <img>; size every render target.
        Array.prototype.forEach.call(box.querySelectorAll('canvas, img, table'), function (el) {
            el.style.width = '75px';
            el.style.height = '75px';
        });
    });
})();

(function () {
    var buttons = document.querySelectorAll('[data-print-card]');
    var cleanSegment = function (value) {
        return String(value || '').trim().replace(/[^A-Za-z0-9_-]+/g, '_').replace(/^_+|_+$/g, '') || 'unknown';
    };

    Array.prototype.forEach.call(buttons, function (button) {
        button.addEventListener('click', function () {
            var wrapper = button.closest('.py-6');
            var page = wrapper && wrapper.querySelector('.rc-page');
            if (!page) { return; }

            var originalTitle = document.title;
            document.querySelectorAll('.rc-page.print-target').forEach(function (target) {
                target.classList.remove('print-target');
            });
            page.classList.add('print-target');
            document.body.classList.add('print-one-admit-card');
            document.title = 'admin_card_' + cleanSegment(button.dataset.session)
                + '_' + cleanSegment(button.dataset.registration);

            var restore = function () {
                document.body.classList.remove('print-one-admit-card');
                page.classList.remove('print-target');
                document.title = originalTitle;
                window.removeEventListener('afterprint', restore);
            };
            window.addEventListener('afterprint', restore);
            window.print();
        });
    });
})();
</script>
