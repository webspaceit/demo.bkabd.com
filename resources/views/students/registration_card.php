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

// The QR opens this student's registration card (public route, keyed by the
// registration number) — the card-side counterpart of the result sheet's
// QR-to-PDF deep link. Scanning the card returns the card itself.
$host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
$https = (($_SERVER['HTTPS'] ?? '') === 'on')
    || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https')
    || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
$qrContent = ($https ? 'https://' : 'http://') . $host
    . '/api/registrationpdf/' . rawurlencode((string) ($student['registration'] ?? ''));

/** A "Label : value" details row. */
$row = static function (string $label, $value): string {
    return '<div class="rc-row"><span class="rc-lbl">' . e($label) . '</span>'
        . '<span class="rc-sep">:</span>'
        . '<span class="rc-val">' . e((string) ($value ?? '')) . '</span></div>';
};
?>
<style>
    /* ---- Card format (landscape, like the association's card) ----
       Same sheet design as the scholarship result sheet (result_show.php):
       the user-supplied repeating pattern fills the sheet; the watermark logo
       is painted on the inner frame so neither hides the other. */
    #reg-card {
        max-width: 820px;
        margin: 0 auto;
        padding: .4125rem;
        background-image: url("/assets/img/result/pattern.png");
        background-repeat: repeat;
        background-position: 0 0;
        background-size: 424px 424px;
    }

    /* Watermark logo, centred and painted over the pattern (same as the
       result sheet's .rs-inner). */
    .rc-inner {
        background-image: url("/assets/img/result/for-registration.png");
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
    .rc-qr { text-align: center; margin-left: auto; align-self: center; flex-shrink: 0; }
    .rc-qr canvas, .rc-qr img, .rc-qr table { width: 88px !important; height: 88px !important; }

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
    .sig-img img { max-width: 100%; height: auto; margin: 0 auto; display: block; }
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

    #reg-card {
        max-width: none;
        margin: 0;
        break-inside: avoid;
        page-break-inside: avoid;
    }
    #reg-card, #reg-card * {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    /* Compact print scale (applies everywhere so screens match the PDF).
       Header text scaled so the contact lines fill the space up to the QR. */
    .rc-banner { padding: .5rem 1rem .4rem; margin-bottom: 1rem; }
    .rc-logo img { width: 68px; height: 68px; }
    .rc-banner h1 { font-size: 2rem; }
    .rc-reg { font-size: .85rem; }
    .rc-banner .rc-gov { font-size: .9rem; line-height: 1.6; white-space: nowrap; }
    .rc-top { padding: .45rem 1rem .1rem; }
    .rc-tag { padding: .15rem .75rem; font-size: .85rem; }
    .rc-exam-head { font-size: 1.5rem; margin-bottom: 1rem; }
    .rc-center .rc-pill { padding: .2rem 1.2rem; font-size: .95rem; background: #000; color: #fff; }
    .rc-photo img { width: 74px; height: 84px; }
    .rc-body { padding: .35rem 1rem 0; column-gap: 1.8rem; row-gap: 0; }
    .rc-row { font-size: .8rem; padding: .06rem 0; grid-template-columns: 8.2rem 1rem 1fr; }
    .rc-qr canvas, .rc-qr img, .rc-qr table { width: 60px !important; height: 60px !important; }
    .sig-tbl { margin-top: .2rem !important; }
    .sig-name { font-size: .95em; }
    .rc-welcome { font-size: .7rem; padding-top: .25rem; }
    .rc-note { font-size: .8rem; padding-top: .25rem; }
    .rc-foot { padding: .25rem 1rem .45rem; font-size: .68rem; }

    @media print {
        html, body { background: #fff !important; }
        body > header, body > footer, #toast-stack { display: none !important; }
        .no-print { display: none !important; }
        /* The thank-you line is screen-only; with it hidden the Scholarship
           Exam heading below flows up to where the sentence was. */
        .rc-welcome { display: none !important; }

        /* The sheet becomes the paper itself — no shadow, no extra padding
           (the @page margin already provides the 0.15in). */
        .rc-page { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
    }
</style>

<div class="py-6 print:py-0">
    <div class="rc-page">
    <div id="reg-card" class="border-2 border-black bg-white">
        <div class="rc-inner border-2 border-black">
        <div class="rc-banner">
            <span class="rc-logo"><img src="/assets/img/result/logo.png" alt="BKABD" onerror="this.style.display='none'"></span>
            <div>
                <h1>BANGLADESH KINDERGARTEN ASSOCIATION</h1>
                <p class="rc-reg">Govt. Reg. No.: S-1028/98</p>
                <p class="rc-gov">
                    59/3, Brammonchiron, Jatrabari, Dhaka-1203, Bangladesh, Mobile: +88-02-224458009<br>
                    Email: infobkabd@gmail.com, info@bkabd.com, Web: www.bkabd.com, www.bka.com.bd, www.bka.org.bd
                </p>
            </div>
            <div class="rc-qr">
                <div id="reg-qr" class="inline-block" data-qr-content="<?= e($qrContent) ?>"></div>
            </div>
        </div>

        <p class="rc-welcome">Thank you. Your Registration has been completed successfully. You must save this Card. It will be required to participate in the next Scholarship Examination.</p>

        <p class="rc-exam-head">Scholarship Exam - <?= e($student['session'] ?? '') ?></p>

        <div class="rc-top">
            <div class="rc-tags">
                <div class="rc-tag">Reg No.&nbsp;: <?= e($student['registration'] ?? '') ?></div>
            </div>
            <div class="rc-center">
                <div class="rc-pill">Student Registration Card</div>
            </div>
            <div class="rc-tag rc-roll">Exam Roll&nbsp;: <?= e($student['roll'] ?? '') ?></div>
        </div>

        <div class="rc-body">
            <div class="rc-col">
                <?= $row('Name', $student['name'] ?? '') ?>
                <?= $row("Father's Name", $student['father_name'] ?? '') ?>
                <?= $row("Mother's Name", $student['mother_name'] ?? '') ?>
                <?= $row('Exam Subject', 'Bangla, English, Mathematics') ?>
                <?= $row('Institute Name', $student['institute_name'] ?? '') ?>
                <?= $row('Exam Center', $student['center_name'] ?? '') ?>
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
            <div class="rc-photo">
                <?php if ($photoSrc !== null): ?>
                    <img src="<?= $photoSrc ?>" alt="Student photo">
                <?php endif; ?>
            </div>
        </div>

        <!-- <div class="rc-body rc-body-wide">
            <?= $row('Institute Address', $student['institute_address'] ?? '') ?>
        </div> -->

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
            <b>Note:</b> This registration card is valid upto this session. Examinee must bring this card in the examination hall.<br>
            <span class="text-xs">&copy; Copyright 1984 - <?= date('Y') ?> Bangladesh Kindergarten Association. All Rights Reserved. Developed by Web Space IT</span>
        </p>

        <div class="rc-foot">
            <div>Date of Registration &amp; Time: <?= e(DateFormat($student['created_at'] ?? '', 'd-m-Y, h:i A')) ?></div>
            <div>Print Date: <?= e(date('d-m-Y')) ?></div>
        </div>
        </div>
    </div>
    </div>

    <div class="mt-4 flex justify-center gap-3 print:hidden">
        <a href="javascript:history.back()" class="btn bg-slate-500 text-white hover:bg-slate-600">Back</a>
        <button type="button" onclick="window.print();" class="btn bg-emerald-500 text-white hover:bg-emerald-600">Print</button>
    </div>
</div>

<script src="/assets/js/vendor/qrcode.min.js"></script>
<script>
(function () {
    var box = document.getElementById('reg-qr');
    if (!box || typeof QRCode === 'undefined') { return; }
    new QRCode(box, {
        text: box.getAttribute('data-qr-content'),
        width: 256,
        height: 256,
        correctLevel: QRCode.CorrectLevel.M
    });
    // Drawn at 256px for a crisp print; qrcodejs emits a canvas and/or an
    // <img>, so size every render target to a 100x100 thumbnail.
    Array.prototype.forEach.call(box.querySelectorAll('canvas, img, table'), function (el) {
        el.style.width = '100px';
        el.style.height = '100px';
    });
})();
</script>
