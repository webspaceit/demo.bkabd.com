<?php
/**
 * Generic printable report sheet.
 *
 * Every report endpoint builds its data and passes it here, so the layout
 * (association header, summary tables, main table, signature footer) matches
 * the original pdf/reports/*.blade.php pages without duplicating it 26 times.
 *
 * The clone serves documents as printable HTML (the browser's "Print / Save as
 * PDF" produces the PDF) — the same approach used by the certificate pages.
 * This keeps thousands-of-rows sheets within shared-hosting memory; dompdf
 * cannot lay out tables that large.
 *
 * Expected data:
 *   $reportTitle   string   e.g. "Center Wise Student Details Report"
 *   $scopeName     string   e.g. "Center Name: Dhaka" (optional)
 *   $activeSession string   exam session
 *   $orientation   string   'portrait' | 'landscape'
 *   $summaries     array    each: ['headings' => [...], 'rows' => [[...]]]
 *   $headings      array    main table headings (optional — omit for summary-only)
 *   $rows          array    main table rows; a cell is scalar or
 *                           ['v' => value, 'c' => css class, 'colspan' => n,
 *                            'img' => src, 'raw' => html]
 */
$reportTitle   = $reportTitle   ?? '';
$scopeName     = $scopeName     ?? '';
$activeSession = $activeSession ?? '';
$orientation   = ($orientation ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait';
$summaries     = $summaries     ?? [];
$headings      = $headings      ?? [];
$rows          = $rows          ?? [];

$cell = static function ($value): string {
    $attrs = '';
    $inner = '';
    if (is_array($value)) {
        if (isset($value['c'])) {
            $attrs .= ' class="' . e($value['c']) . '"';
        }
        if (!empty($value['colspan'])) {
            $attrs .= ' colspan="' . (int) $value['colspan'] . '"';
        }
        if (!empty($value['img'])) {
            $inner = '<img class="photo" src="' . e($value['img']) . '">';
        } elseif (isset($value['raw'])) {
            $inner = (string) $value['raw'];
        } else {
            $inner = e($value['v'] ?? '');
        }
    } else {
        $inner = e($value);
    }
    return '<td' . $attrs . '>' . $inner . '</td>';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($reportTitle !== '' ? $reportTitle : 'BKABD Report') ?></title>
    <style>
        @page { size: A4 <?= $orientation ?>; margin: 10mm; }
        body { font-family: Arial, Helvetica, sans-serif; margin: 0; color: #000; }
        .wrap { padding: 10px 18px 0; }
        .head { text-align: center; line-height: 1.35; margin-bottom: 8px; }
        .head h3 { margin: 2px 0; font-size: 17px; }
        .head h4 { margin: 2px 0; font-size: 13px; }
        .head h5 { margin: 2px 0; font-size: 11px; font-weight: normal; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; table-layout: fixed; }
        th, td { border: 1px solid #000; text-align: center; font-size: 10px; padding: 3px; vertical-align: middle; overflow-wrap: break-word; word-wrap: break-word; }
        th { background: #d3d3d3; }
        thead.blue th, thead.blue td { background: #22798e; color: #fff; }
        td.left, th.left { text-align: left; }
        .sign { width: 100%; margin-top: 26px; border: 0; }
        .sign td { border: 0; text-align: center; font-size: 11px; padding-top: 34px; }
        .copy { text-align: center; font-size: 10px; margin-top: 14px; }
        .stamp { text-align: right; font-size: 8px; margin-top: 4px; }
        .photo { width: 34px; height: 34px; }
        .toolbar { text-align: right; padding: 10px 18px 0; }
        .toolbar button { font: inherit; padding: 6px 14px; border: 1px solid #22798e; background: #22798e; color: #fff; border-radius: 4px; cursor: pointer; }
        @media print { .toolbar { display: none; } .wrap { padding: 0; } }
    </style>
</head>
<body>
<div class="toolbar">
    <button type="button" onclick="window.print()">Print / Save as PDF</button>
</div>
<div class="wrap">
    <div class="head">
        <h3>BANGLADESH KINDERGARTEN ASSOCIATION</h3>
        <h5>Govt. Reg. No.: S-1028/98</h5>
        <h4>Scholarship Exam - <?= e($activeSession) ?></h4>
        <?php if ($reportTitle !== ''): ?><h4><?= e($reportTitle) ?></h4><?php endif; ?>
        <?php if ($scopeName !== ''): ?><h4><?= e($scopeName) ?></h4><?php endif; ?>
    </div>

    <?php foreach ($summaries as $summary): ?>
        <table>
            <thead class="blue">
                <tr><?php foreach (($summary['headings'] ?? []) as $h): ?><th><?= e($h) ?></th><?php endforeach; ?></tr>
            </thead>
            <tbody>
            <?php foreach (($summary['rows'] ?? []) as $r): ?>
                <tr><?php foreach ($r as $value) {
                    echo $cell($value);
                } ?></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endforeach; ?>

    <?php if ($headings !== []): ?>
        <table>
            <thead>
                <tr><?php foreach ($headings as $h): ?><th><?= e($h) ?></th><?php endforeach; ?></tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr><?php foreach ($r as $value) {
                    echo $cell($value);
                } ?></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <table class="sign">
        <tr>
            <td>MONOWARA BHUIYAN<br>Chairman</td>
            <td>Md. Mizanur Rahman<br>General Secretary</td>
            <td>Md. Faruk Hossen<br>Controller of Exam</td>
            <td>Md. Abdul Alim<br>Education Secretary</td>
        </tr>
    </table>

    <div class="copy">&copy; Copyright 1984 - <?= date('Y') ?> Bangladesh Kindergarten Association. All Rights Reserved. Developed by BKABD</div>
    <div class="stamp"><?= date('Y-m-d H:i:s') ?></div>
</div>
</body>
</html>
