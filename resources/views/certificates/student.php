<?php
/**
 * Printable student certificates — one page per student.
 * Ported from the original pdf/StudentCertificate.blade.php (browser print).
 * Rendered without a layout: this opens in its own window.
 *
 * The list is empty when the active exam session has no awarded grades (1-3)
 * for the institute/class, so an explicit empty state is shown rather than a
 * blank page.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>BKABD — Student Certificate</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: "Monotype Corsiva", cursive; margin: 0; }
        u { border-bottom: 1px dotted #000; text-decoration: none; padding: 0 70px; font-size: 20px; }
        .main-body { text-align: justify; font-size: 20px; line-height: 1.6; margin: 310.8px 55.4px 0 97.4px; }
        .date { margin-right: 70px; margin-top: 0.08px; float: right; font-family: Tahoma; }
        .sl { margin-left: 165px; margin-top: 0.08px; float: left; font-family: Tahoma; }
        .clear { clear: both; }
        .no-print { text-align: right; }
        .page { height: 646.8px; page-break-after: always; padding: 20px 0 0 0; }
        .empty { font-family: Tahoma, Arial, sans-serif; margin: 48px; font-size: 18px; line-height: 1.6; color: #333; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
<?php if (empty($students)): ?>
    <div class="empty" data-empty="1">
        No students with an awarded grade (1&ndash;3) were found for this institute and class
        in exam session <strong><?= e($session ?? '') ?></strong>.<br>
        Assign grades first, or switch the exam session from the header, then try again.
    </div>
<?php else: ?>
    <div class="no-print"><button type="button" onclick="window.print()">Click to Print</button></div>
    <?php foreach ($students as $s): ?>
        <div class="page">
            <div class="sl"><?= e(substr((string) ($s['center_name'] ?? ''), 0, 3) . '-' . $s['id']) ?></div>
            <div class="date"><?= e(date('d.m.Y')) ?></div>
            <div class="clear"></div>
            <div class="main-body">
                This is to certify that <u><b><?= e($s['name']) ?></b></u>, son/daughter of
                Mr. <u><?= e($s['father_name']) ?></u> and Mrs. <u><?= e($s['mother_name']) ?></u>,
                Class <u><?= e(ClassName((int) $s['class'])) ?></u>, Roll No. <u><?= e($s['roll']) ?></u>,
                Reg. No. <u><?= e($s['registration']) ?></u> is a student of
                <u><?= e($s['institute_name'] ?? '') ?></u>. He/She appeared at the Bangladesh Kindergarten
                Association Scholarship Examination <u><?= e($s['session']) ?></u> and obtained
                <u><?= e(GradeName((int) $s['grade'])) ?> <?= (int) $s['ml'] !== 0 ? '(' . e(MeritName((int) $s['ml'])) . ')' : '' ?></u>
                Grade.<br>
                We wish his/her all success in life.<br>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
