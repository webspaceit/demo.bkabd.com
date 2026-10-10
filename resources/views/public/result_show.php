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

$th = 'border border-black px-2 py-1 text-left';
$td = 'border border-black px-2 py-1 font-normal';
$mTh = 'border border-black px-2 py-1';
?>
<style>
    @media print {
        @page { size: A4; margin: 8mm; }
        body > header, body > footer, #toast-stack { display: none !important; }
    }
</style>

<div class="mx-auto max-w-4xl px-4 py-6 print:max-w-none print:px-0 print:py-0">
    <div class="border-2 border-black bg-white p-4">
        <img src="/assets/img/result/logo.png" alt="BKABD" class="mx-auto block w-20 rounded-full">

        <h1 class="mt-2 text-center text-2xl font-bold">BANGLADESH KINDERGARTEN ASSOCIATION</h1>
        <p class="text-center font-semibold">Govt. Reg. No.: S-1028/98</p>
        <p class="my-3 text-center text-xs leading-relaxed">
            59/3, Brammonchiron, Jatrabari, Dhaka-1203, Bangladesh, Mobile: +88-02-224458009<br>
            Email: infobkabd@gmail.com, info@bkabd.com, Web: www.bkabd.com, www.bka.com.bd, www.bka.org.bd
        </p>

        <h2 class="text-center text-xl font-extrabold">SCHOLARSHIP RESULT - <?= e($student['session']) ?></h2>

        <div class="mx-auto my-3 max-w-xs rounded-full bg-black py-1 text-center text-sm font-semibold text-white">
            Student Details Information
        </div>

        <table class="w-full border-collapse text-sm">
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

        <div class="mx-auto my-5 max-w-xs rounded-full bg-black py-1 text-center text-sm font-semibold text-white">
            Exam Marks &amp; Grade Sheet
        </div>

        <table class="w-full border-collapse text-center text-sm">
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

        <div class="mt-6 text-center">
            <img src="/assets/img/result/qrcode.png" alt="QR code" class="mx-auto w-24">
        </div>

        <table class="mt-5 w-full text-center text-xs">
            <tr>
                <td class="p-2 align-top">
                    <img src="<?= e(result_signature('chairman')) ?>" alt="" class="mx-auto h-10 w-auto"><br>
                    MONOWARA BHUIYAN<br>Chairman
                </td>
                <td class="p-2 align-top">
                    <img src="<?= e(result_signature('general_secretary')) ?>" alt="" class="mx-auto h-10 w-auto"><br>
                    MD. MIZANUR RAHMAN<br>General Secretary
                </td>
                <td class="p-2 align-top">
                    <img src="<?= e(result_signature('controller')) ?>" alt="" class="mx-auto h-10 w-auto"><br>
                    MD. FARUK HOSSEN<br>Controller of Exam
                </td>
                <td class="p-2 align-top">
                    <img src="<?= e(result_signature('education_secretary')) ?>" alt="" class="mx-auto h-10 w-auto"><br>
                    MD. ABDUL ALIM<br>Education Secretary
                </td>
            </tr>
        </table>

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
