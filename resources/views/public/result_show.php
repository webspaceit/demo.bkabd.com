<?php
/**
 * Public result sheet — port of original resources/views/result_show.blade.php
 * (details table, marks & grade sheet, signatures, publication/print dates).
 * Printable: action buttons + site chrome are hidden via print: styles.
 *
 * @var array $student    joined row (ResultPortalService::findStudent)
 * @var array|null $fullMarks session full-marks config (bangla/english/math/total)
 * @var string $resultTime sessions.result_time of the published result
 */
$grades = [1 => 'Talent Pool', 2 => 'General Grade', 3 => 'School Grade', 4 => 'AB'];
$mls = [1 => '1st', 2 => '2nd', 3 => '3rd'];
$gradeText = ($grades[(int) $student['grade']] ?? '')
    . ((int) $student['ml'] !== 0 ? '(' . ($mls[(int) $student['ml']] ?? '') . ')' : '');

$pubDate = '';
if ($resultTime) {
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', (string) $resultTime);
    $pubDate = $dt ? $dt->format('d F Y') : '';
}
$fm = fn(string $subject): string => (string) (($fullMarks ?? [])[$subject] ?? '-');

// Cells are compact on phones (dense tables cannot shrink below their content
// width) and full-size from the sm breakpoint up, so the print/PDF layout is
// unchanged.
$th = 'border border-black px-1 py-0.5 text-left sm:px-2 sm:py-1';
$td = 'border border-black px-1 py-0.5 font-normal sm:px-2 sm:py-1';
$mTh = 'border border-black px-0.5 py-0.5 sm:px-2 sm:py-1';
?>
<style>
    /* Always print on A4, portrait — regardless of the printer default. */
    @page { size: A4 portrait; margin: 12mm; }

    /* Page frame: a slightly wider gap between the outer and inner black rules
       (Tailwind p-1 was .25rem/4px). rem-based so print scales the same way.
       The user-supplied repeating pattern fills the sheet; the watermark logo is
       painted on top of it (see .rs-inner) so neither hides the other. */
    #result-sheet {
        padding: .4125rem;
        background-image: url("<?= brand_asset('pattern_path', '/assets/img/result/pattern.png') ?>");
        background-repeat: repeat;
        background-position: 0 0;
        background-size: 424px 424px;
    }

    /* Watermark logo, centred and painted over the pattern. */
    .rs-inner {
        background-image: url("<?= brand_asset('watermark_path', '/assets/img/result/for-registration.png') ?>");
        background-repeat: no-repeat;
        background-position: center center;
        background-size: 400px 400px;
    }

    /* Association name — larger, stands out as the certificate heading. */
    .rs-org     { font-size: 2rem;    line-height: 1.2; }   /* 24px -> 32px   */

    /* Header / title text +20% (rem-based so print/PDF scale too). */
    .rs-regno   { font-size: 1.05rem; line-height: 1.3; }   /* 14px -> 16.8px */
    .rs-contact { font-size: 0.9rem;  line-height: 1.35; }  /* 12px -> 14.4px */
    .rs-h2      { font-size: 1.5rem;  line-height: 1.2; }   /* 20px -> 24px   */
    .rs-bar     { font-size: 1.05rem; line-height: 1.3; }   /* 14px -> 16.8px */

    /* Green divider under the address block, separating it from the title
       (mirrors the rule in the PDF). */
    .rs-rule {
        height: 1px;
        border: 0;
        border-radius: 1px;
        background: #000;
        margin: .65rem 0 .5rem;
    }

    /* Student Details table: label columns (1st & 3rd) get a light-green tint
       so the sheet matches the PDF. nth-child is per-row, so the label+colspan
       rows (which have no 3rd cell) are unaffected. */
    .rs-details th:nth-child(1),
    .rs-details th:nth-child(3) { background: #EEF7EA; }

    /* Exam Marks table: columns 5-7 (Total / Percentage / Grade) use the same tint. */
    .rm-fill { background: #EEF7EA; }

    /* Signature block */
    .sig-tbl td { vertical-align: bottom; text-align: center; padding: 0 1rem; }
    .sig-img { height: 2.5rem; }
    .sig-img img { height: 2.5rem; width: auto; margin: 0 auto; }
    .sig-line { border-top: 1px solid #000; margin-top: .25rem; padding-top: .25rem; }
    .sig-name { font-weight: 600; font-size: 1.1em; line-height: 1.2; color: #000; }
    .sig-role { color: #000; font-size: 99%; line-height: 1.2; }

    @media print {
        /* rem-based Tailwind sizes scale with the root, so a 13px root makes
           the printed sheet land on a single A4 page and match the PDF
           (h1 19.5px, h2 16px, tables 11.4px) almost exactly. */
        html { font-size: 13px; }

        html, body { background: #fff !important; }
        body > header, body > footer, #toast-stack { display: none !important; }

        /* Keep the whole certificate together on one sheet. */
        #result-sheet { break-inside: avoid; page-break-inside: avoid; }

        /* Print the green bars / header exactly as shown. */
        #result-sheet,
        #result-sheet * {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }
</style>

<div class="mx-auto max-w-4xl px-3 py-6 print:max-w-none print:px-0 print:py-0">
    <div id="result-sheet" class="border-2 border-black bg-white">
        <div class="rs-inner border-2 border-black p-3 sm:p-4">
        <img src="<?= e(brand_asset('logo_path', '/assets/img/result/logo.png')) ?>" alt="<?= e(brand('short_name')) ?>" class="mx-auto block w-20 rounded-full">

        <h1 class="rs-org mt-2 text-center font-bold"><?= e(brand('authority_name')) ?></h1>
        <p class="rs-regno text-center font-semibold"><?= e(brand('registration_label')) ?>: <?= e(brand('registration_no')) ?></p>
        <p class="rs-contact my-3 text-center leading-relaxed mt-1 mb-1">
            <?= e(brand('address')) ?><?= brand('phone') !== '' ? ', Mobile: ' . e(brand('phone')) : '' ?><br>
            <?= brand('email') !== '' ? 'Email: ' . e(brand('email')) : '' ?><?= brand('website') !== '' ? ', Web: ' . e(brand('website')) : '' ?>
        </p>

        <hr class="rs-rule">

        <h2 class="rs-h2 text-center mt-4 font-extrabold">SCHOLARSHIP RESULT - <?= e($student['session']) ?></h2>

        <div class="mx-auto my-3  mb-5 max-w-xs rounded-full text-center rs-bar font-semibold text-white"
             style="background:#00A550;padding:.4rem 1rem;box-shadow:0 1px 3px rgba(0,110,54,.35)">
            Student Details Information
        </div>

        <div class="overflow-x-auto">
        <table class="rs-details w-full border-collapse text-[10px] sm:text-sm">
            <tr>
                <th class="<?= $th ?>" width="20%">Division</th>
                <th class="<?= $th ?>" width="20%"><span class="font-normal"><?= e($student['division_name']) ?></span></th>
                <th class="<?= $th ?>" width="20%">District</th>
                <th class="<?= $th ?>" width="40%"><span class="font-normal"><?= e($student['district_name']) ?></span></th>
            </tr>
            <tr>
                <th class="<?= $th ?>">Thana/Upazila</th>
                <th class="<?= $th ?>"><span class="font-normal"><?= e($student['upazila_name']) ?></span></th>
                <th class="<?= $th ?>">Name of Center</th>
                <th class="<?= $th ?>"><span class="font-normal"><?= e($student['center_name']) ?></span></th>
            </tr>
            <tr>
                <th class="<?= $th ?>">Class</th>
                <th class="<?= $th ?>"><span class="font-normal"><?= e(ClassName($student['class'])) ?></span></th>
                <th class="<?= $th ?>">Date of Birth</th>
                <th class="<?= $th ?>"><span class="font-normal"><?= e(DateFormat($student['dob'], 'd-m-Y')) ?></span></th>
            </tr>
            <tr>
                <th class="<?= $th ?>">Registration No.</th>
                <th class="<?= $th ?>"><span class="font-normal"><?= e($student['registration']) ?></span></th>
                <th class="<?= $th ?>">Roll No.</th>
                <th class="<?= $th ?>"><span class="font-normal"><?= e($student['roll']) ?></span></th>
            </tr>
            <tr>
                <th class="<?= $th ?>">Name of Student</th>
                <th class="<?= $th ?>" colspan="3"><span class="font-normal"><?= e($student['name']) ?></span></th>
            </tr>
            <tr>
                <th class="<?= $th ?>">Father's Name</th>
                <th class="<?= $th ?>" colspan="3"><span class="font-normal"><?= e($student['father_name']) ?></span></th>
            </tr>
            <tr>
                <th class="<?= $th ?>">Mother's Name</th>
                <th class="<?= $th ?>" colspan="3"><span class="font-normal"><?= e($student['mother_name']) ?></span></th>
            </tr>
            <tr>
                <th class="<?= $th ?>">Name of Institute</th>
                <th class="<?= $th ?>" colspan="3"><span class="font-normal"><?= e($student['institute_name']) ?></span></th>
            </tr>
        </table>
        </div>

        <div class="mx-auto my-5 max-w-xs rounded-full text-center rs-bar font-semibold text-white"
             style="background:#00A550;padding:.4rem 1rem;box-shadow:0 1px 3px rgba(0,110,54,.35)">
            Exam Marks &amp; Grade Sheet
        </div>

        <div class="overflow-x-auto">
        <table class="w-full border-collapse text-center text-[10px] sm:text-sm">
            <thead>
                <tr class="text-white" style="background:#00A550">
                    <th class="<?= $mTh ?>" width="4%">Sl.</th>
                    <th class="<?= $mTh ?>" width="15%">Subject Name</th>
                    <th class="<?= $mTh ?>" width="15%">Full Marks</th>
                    <th class="<?= $mTh ?>" width="17%">Marks Obtained</th>
                    <th class="<?= $mTh ?>" width="15%">Total Marks</th>
                    <th class="<?= $mTh ?>" width="19%">Percentage Mark</th>
                    <th class="<?= $mTh ?>" width="15%">Grade</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="<?= $mTh ?>">01</td>
                    <td class="<?= $mTh ?>">Bangla</td>
                    <td class="<?= $mTh ?>"><?= e($fm('bangla')) ?></td>
                    <td class="<?= $mTh ?>"><?= e($student['bangla']) ?></td>
                    <td class="<?= $mTh ?> rm-fill" rowspan="3"><?= e($student['total']) ?></td>
                    <td class="<?= $mTh ?> rm-fill" rowspan="3"><?= e($student['percentage']) ?>%</td>
                    <td class="<?= $mTh ?> rm-fill" rowspan="3"><?= e($gradeText) ?></td>
                </tr>
                <tr>
                    <td class="<?= $mTh ?>">02</td>
                    <td class="<?= $mTh ?>">English</td>
                    <td class="<?= $mTh ?>"><?= e($fm('english')) ?></td>
                    <td class="<?= $mTh ?>"><?= e($student['english']) ?></td>
                </tr>
                <tr>
                    <td class="<?= $mTh ?>">03</td>
                    <td class="<?= $mTh ?>">Mathematics</td>
                    <td class="<?= $mTh ?>"><?= e($fm('math')) ?></td>
                    <td class="<?= $mTh ?>"><?= e($student['math']) ?></td>
                </tr>
            </tbody>
        </table>
        </div>

        <div class="mt-6 text-center">
            <?php if (!empty($qrContent)): ?>
                <div id="result-qr" class="inline-block" data-qr-content="<?= e($qrContent) ?>"></div>
            <?php else: ?>
                <img src="<?= e(asset_v('/assets/img/result/qrcode.png')) ?>" alt="QR code" class="mx-auto w-24">
            <?php endif; ?>
        </div>

        <div class="overflow-x-auto">
        <table class="sig-tbl mt-6 w-full text-[10px] sm:text-xs">
            <tr>
                <?php foreach ([
                    ['chairman',           brand('chairman_name'), brand('chairman_title')],
                    ['general_secretary',  brand('general_secretary_name'), brand('general_secretary_title')],
                    ['controller',         brand('controller_name'), brand('controller_title')],
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

        <div class="mt-4 text-center text-[13px] leading-6">
            <span class="text-sm"><b>Note:</b> Any kind of objection this result should be reported to the authority within 7 days of published.</span><br>
            <span class="text-xs">&copy; <?= date('Y') ?> <?= e(brand('authority_name')) ?>. <?= e(brand('copyright_text')) ?> <?= e(brand('developer_credit')) ?></span>
        </div>

        <div class="mt-2 flex w-full justify-between text-[11px]">
            <div><?php if ($pubDate): ?>Result Publication Date: <?= e($pubDate) ?><?php endif; ?></div>
            <div>Print Date &amp; Time: <?= e(date('d F Y, h:i A')) ?></div>
        </div>
        </div>
    </div>

    <div class="mt-4 flex justify-center gap-3 print:hidden">
        <a href="/scholarship-result" class="btn bg-slate-500 text-white hover:bg-slate-600">Search Result Again</a>
        <button type="button" onclick="window.print();" class="btn bg-emerald-500 text-white hover:bg-emerald-600">Print</button>
    </div>
</div>

<?php if (!empty($qrContent)): ?>
<script src="/assets/js/vendor/qrcode.min.js"></script>
<script>
(function () {
    var box = document.getElementById('result-qr');
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
<?php endif; ?>
