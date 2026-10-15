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
        max-width: 1020px;
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
        background-image: url("/assets/img/result/water-mark.webp");
        background-repeat: no-repeat;
        background-position: center center;
        background-size: 400px 400px;
    }

    /* ---- Banner header (transparent so the pattern + watermark show through) ---- */
    .rc-banner {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: .8rem 1.2rem .7rem;
        border-bottom: 3px solid #065f46;
    }
    .rc-logo {
        width: 84px;
        height: 84px;
        border-radius: 9999px;
        background: #fff;
        border: 3px solid #065f46;
        box-shadow: 0 0 0 3px #d1fae5;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        flex-shrink: 0;
    }
    .rc-logo img { width: 74px; height: 74px; object-fit: contain; }
    .rc-banner h1 {
        margin: 0;
        font-size: 1.75rem;
        line-height: 1.15;
        font-weight: 800;
        color: #0f172a;
        letter-spacing: .01em;
    }
    .rc-banner .rc-bn {
        margin-top: .1rem;
        font-size: 1.25rem;
        line-height: 1.4;
        font-weight: 700;
        color: #00a550;
    }
    .rc-banner .rc-gov {
        margin-top: .15rem;
        font-size: .8rem;
        font-weight: 600;
        color: #475569;
    }

    /* ---- Top row: tag boxes | heading + pill | photo ---- */
    .rc-top {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem 1.2rem .4rem;
    }
    .rc-tags { display: flex; flex-direction: column; gap: .55rem; min-width: 210px; }
    .rc-tag {
        border: 2px solid #0f172a;
        border-radius: 9999px;
        background: #fff;
        padding: .3rem .9rem;
        font-size: .95rem;
        font-weight: 700;
        color: #0f172a;
        white-space: nowrap;
    }
    .rc-center { flex: 1; text-align: center; min-width: 0; }
    .rc-center .rc-exam {
        font-size: 1.45rem;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: .45rem;
    }
    .rc-center .rc-pill {
        display: inline-block;
        border: 2px solid #0f172a;
        border-radius: 9999px;
        background: #fff;
        padding: .35rem 1.6rem;
        font-size: 1.05rem;
        font-weight: 700;
        color: #0f172a;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .15);
    }
    .rc-photo { flex-shrink: 0; }
    .rc-photo img {
        display: block;
        width: 110px;
        height: 128px;
        object-fit: cover;
        border: 2px solid #94a3b8;
        background: #fff;
    }

    /* ---- Details grid over a light watermark ---- */
    .rc-body {
        display: grid;
        grid-template-columns: 1fr 1fr;
        column-gap: 2.5rem;
        row-gap: .15rem;
        padding: .9rem 1.2rem .4rem;
    }
    .rc-row {
        display: grid;
        grid-template-columns: 9.5rem 1rem 1fr;
        align-items: baseline;
        padding: .18rem 0;
        font-size: .95rem;
        line-height: 1.35;
    }
    .rc-lbl { font-weight: 700; color: #0f172a; }
    .rc-sep { color: #0f172a; text-align: center; }
    .rc-val { font-weight: 600; color: #111827; word-break: break-word; }

    /* ---- Footer ---- */
    .rc-note {
        clear: both;
        position: relative;
        text-align: center;
        font-size: .8rem;
        color: #334155;
        padding: .6rem 1.2rem 0;
    }
    .rc-foot {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        font-size: .78rem;
        color: #475569;
        padding: .5rem 1.2rem .8rem;
    }

    @page { size: A4 landscape; margin: 10mm; }

    @media print {
        html, body { background: #fff !important; }
        body > header, body > footer, #toast-stack { display: none !important; }

        #reg-card {
            max-width: none;
            margin: 0;
            box-shadow: none;
            break-inside: avoid;
            page-break-inside: avoid;
        }
        #reg-card, #reg-card * {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .no-print { display: none !important; }
    }
</style>

<div class="py-6 print:py-0">
    <div id="reg-card" class="border-2 border-black bg-white">
        <div class="rc-inner border-2 border-black">
        <div class="rc-banner">
            <span class="rc-logo"><img src="/assets/img/result/logo.png" alt="BKABD" onerror="this.style.display='none'"></span>
            <div>
                <h1>BANGLADESH KINDERGARTEN ASSOCIATION</h1>
                <div class="rc-bn" lang="bn">বাংলাদেশ কিন্ডারগার্টেন অ্যাসোসিয়েশন</div>
                <div class="rc-gov">Govt. Reg. No.: S-1028/98 &nbsp;·&nbsp; 59/3, Brammonchiron, Jatrabari, Dhaka-1203, Bangladesh</div>
            </div>
        </div>

        <div class="rc-top">
            <div class="rc-tags">
                <div class="rc-tag">Reg No&nbsp;: <?= e($student['registration'] ?? '') ?></div>
                <div class="rc-tag">Exam Roll&nbsp;: <?= e($student['roll'] ?? '') ?></div>
            </div>
            <div class="rc-center">
                <div class="rc-exam">Exam - <?= e($student['session'] ?? '') ?></div>
                <div class="rc-pill">Registration Card</div>
            </div>
            <div class="rc-photo">
                <?php if ($photoSrc !== null): ?>
                    <img src="<?= $photoSrc ?>" alt="Student photo">
                <?php endif; ?>
            </div>
        </div>

        <div class="rc-body">
            <div>
                <?= $row('Name', $student['name'] ?? '') ?>
                <?= $row("Father's Name", $student['father_name'] ?? '') ?>
                <?= $row("Mother's Name", $student['mother_name'] ?? '') ?>
                <?= $row('Institute Name', $student['institute_name'] ?? '') ?>
                <?= $row('Exam Center', $student['center_name'] ?? '') ?>
                <?= $row('Institute Address', $student['institute_address'] ?? '') ?>
            </div>
            <div>
                <?= $row('Gender', GenderName((int) ($student['gender'] ?? 0))) ?>
                <?= $row('Class', ClassName((int) ($student['class'] ?? 0))) ?>
                <?= $row('Mobile', $student['phone'] ?? '') ?>
                <?= $row('Date of Birth', !empty($student['dob']) ? DateFormat($student['dob'], 'd-m-Y') : '') ?>
                <?= $row('Division', $student['division_name'] ?? '') ?>
                <?= $row('District', $student['district_name'] ?? '') ?>
                <?= $row('Thana/Upazila', $student['upazila_name'] ?? '') ?>
            </div>
        </div>

        <p class="rc-note">
            <b>Note:</b> Thank you — your registration has been completed successfully. You must print and save this
            Registration Card; it will be required to participate in the scholarship examination and is valid up to this session.<br>
            &copy; Copyright 1984 - <?= date('Y') ?> Bangladesh Kindergarten Association. All Rights Reserved. Developed by BKABD
        </p>

        <div class="rc-foot">
            <div>Date of Registration: <b><?= e(DateFormat($student['created_at'] ?? '', 'd-m-Y')) ?></b></div>
            <div>Print Date &amp; Time: <?= e(date('d-m-Y, h:i A')) ?></div>
        </div>
        </div>
    </div>

    <div class="mt-4 flex justify-center gap-3 print:hidden">
        <a href="javascript:history.back()" class="btn bg-slate-500 text-white hover:bg-slate-600">Back</a>
        <button type="button" onclick="window.print();" class="btn bg-emerald-500 text-white hover:bg-emerald-600">Print</button>
    </div>
</div>
