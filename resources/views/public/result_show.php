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
    <div id="result-sheet" class="border-2 border-black bg-white p-3 sm:p-4">
        <img src="/assets/img/result/logo.png" alt="BKABD" class="mx-auto block w-20 rounded-full">

        <h1 class="mt-2 text-center text-2xl font-bold">BANGLADESH KINDERGARTEN ASSOCIATION</h1>
        <p class="text-center font-semibold">Govt. Reg. No.: S-1028/98</p>
        <p class="my-3 text-center text-xs leading-relaxed mt-1 mb-1">
            59/3, Brammonchiron, Jatrabari, Dhaka-1203, Bangladesh, Mobile: +88-02-224458009<br>
            Email: infobkabd@gmail.com, info@bkabd.com, Web: www.bkabd.com, www.bka.com.bd, www.bka.org.bd
        </p>

        <h2 class="text-center text-xl font-extrabold">SCHOLARSHIP RESULT - <?= e($student['session']) ?></h2>

        <div class="mx-auto my-3 max-w-xs rounded-full text-center text-sm font-semibold text-white"
             style="background:linear-gradient(90deg,#1DB601,#138000);padding:.4rem 1rem;box-shadow:0 1px 3px rgba(19,128,0,.35)">
            Student Details Information
        </div>

        <div class="overflow-x-auto">
        <table class="w-full border-collapse text-[10px] sm:text-sm">
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

        <div class="mx-auto my-5 max-w-xs rounded-full text-center text-sm font-semibold text-white"
             style="background:linear-gradient(90deg,#1DB601,#138000);padding:.4rem 1rem;box-shadow:0 1px 3px rgba(19,128,0,.35)">
            Exam Marks &amp; Grade Sheet
        </div>

        <div class="overflow-x-auto">
        <table class="w-full border-collapse text-center text-[10px] sm:text-sm">
            <thead>
                <tr class="bg-[#30b34b] text-white">
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
                    <td class="<?= $mTh ?>" rowspan="3"><?= e($student['total']) ?></td>
                    <td class="<?= $mTh ?>" rowspan="3"><?= e($student['percentage']) ?>%</td>
                    <td class="<?= $mTh ?>" rowspan="3"><?= e($gradeText) ?></td>
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
                <img src="/assets/img/result/qrcode.png" alt="QR code" class="mx-auto w-24">
            <?php endif; ?>
        </div>

        <div class="overflow-x-auto">
        <table class="mt-1 w-full text-center text-[10px] sm:text-xs">
            <tr>
                <td class="p-1 align-top sm:p-2">
                    <img src="<?= e(result_signature('chairman')) ?>" alt="" class="mx-auto h-10 w-auto"><br>
                    MONOWARA BHUIYAN<br>Chairman
                </td>
                <td class="p-1 align-top sm:p-2">
                    <img src="<?= e(result_signature('general_secretary')) ?>" alt="" class="mx-auto h-10 w-auto"><br>
                    MD. MIZANUR RAHMAN<br>General Secretary
                </td>
                <td class="p-1 align-top sm:p-2">
                    <img src="<?= e(result_signature('controller')) ?>" alt="" class="mx-auto h-10 w-auto"><br>
                    MD. FARUK HOSSEN<br>Controller of Exam
                </td>
                <td class="p-1 align-top sm:p-2">
                    <img src="<?= e(result_signature('education_secretary')) ?>" alt="" class="mx-auto h-10 w-auto"><br>
                    MD. ABDUL ALIM<br>Education Secretary
                </td>
            </tr>
        </table>
        </div>

        <div class="mt-4 text-center text-[13px] leading-6">
            <span class="text-sm"><b>Note:</b> Any kind of objection this result should be reported to the authority within 7 days of published.</span><br>
            <span class="text-xs">&copy; Copyright 1984 - <?= date('Y') ?> Bangladesh Kindergarten Association. All Rights Reserved. Developed by BKABD</span>
        </div>

        <div class="mt-2 flex w-full justify-between text-[11px]">
            <div><?php if ($pubDate): ?>Result Publication Date: <?= e($pubDate) ?><?php endif; ?></div>
            <div>Print Date &amp; Time: <?= e(date('d F Y, h:i A')) ?></div>
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
