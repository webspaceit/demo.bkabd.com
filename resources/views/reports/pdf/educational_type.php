<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Educational Type Student Count Report</title>
    <style>
        @page { size: A4 portrait; margin: 10mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; margin: 0; padding: 0; color: #000; font-size: 10px; }
        .wrap { padding: 10px 14px 0; }
        /* Header */
        .head { text-align: center; margin-bottom: 6px; }
        .head img { width: 60px; }
        .head h3 { margin: 2px 0; font-size: 15px; font-weight: bold; }
        .head h5 { margin: 1px 0; font-size: 10px; font-weight: normal; }
        .head h4 { margin: 2px 0; font-size: 12px; font-weight: bold; }
        /* Main table */
        table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        th, td { border: 1px solid #000; text-align: center; padding: 3px 4px; vertical-align: middle; font-size: 10px; }
        th { background: #d3d3d3; font-weight: bold; }
        td.left { text-align: left; }
        .total-row td { font-weight: bold; background: #f0f0f0; }
        /* Summary tables */
        .summary-wrap { margin-top: 8px; }
        .summary-title th { background: #22798e; color: #fff; font-size: 11px; padding: 4px; }
        /* Signatures */
        .sign { width: 100%; margin-top: 24px; border: 0; }
        .sign td { border: 0; text-align: center; font-size: 10px; padding-top: 30px; }
        /* Print toolbar */
        .toolbar { text-align: right; padding: 8px 14px 0; }
        .toolbar button { font: inherit; padding: 5px 14px; border: 1px solid #22798e; background: #22798e; color: #fff; border-radius: 4px; cursor: pointer; }
        @media print { .toolbar { display: none; } .wrap { padding: 0; } }
        .copy { text-align: center; font-size: 9px; margin-top: 12px; }
        .stamp { text-align: right; font-size: 8px; margin-top: 3px; }
    </style>
</head>
<body>

<div class="toolbar">
    <button type="button" onclick="window.print()">Print / Save as PDF</button>
</div>

<div class="wrap">

    <!-- Association header -->
    <div class="head">
        <h3>BANGLADESH KINDERGARTEN ASSOCIATION</h3>
        <h5>Govt. Reg. No.: S-1028/98</h5>
        <h4>SCHOLARSHIP EXAM - <?= e($activeSession) ?></h4>
        <h4>Educational Type Student Count Report</h4>
    </div>

    <!-- Main table -->
    <?php
    $chunks = array_chunk($mainRows, 35);
    if (empty($chunks)) { $chunks = [[]]; }  // at least one page even if no data
    foreach ($chunks as $pageIndex => $chunk):
    $startSl = $pageIndex * 35 + 1;
    ?>
    <?php if ($pageIndex > 0): ?><div style="page-break-before:always"></div><?php endif; ?>
    <table>
        <thead>
            <tr>
                <th rowspan="2" style="width:28px">Sl.</th>
                <th rowspan="2">Center Name</th>
                <th rowspan="2" style="width:42px">Class</th>
                <th colspan="3">Education Type</th>
                <th rowspan="2" style="width:52px">Total Count</th>
            </tr>
            <tr>
                <th style="width:72px">Bangla Medium</th>
                <th style="width:72px">English Version</th>
                <th style="width:72px">Arabic Version</th>
            </tr>
        </thead>
        <tbody>
            <?php for ($i = 0; $i < 35; $i++):
                $r   = $chunk[$i] ?? null;
                $sl  = $startSl + $i;
                if (!$r && $i >= count($chunk)) continue; // skip blank padding rows
            ?>
            <tr>
                <td><?= $r ? $sl : '' ?></td>
                <td class="left"><?= $r ? e($r['center_name']) : '' ?></td>
                <td><?= $r ? e($r['class_name']) : '' ?></td>
                <td><?= $r ? ($r['bangla']  > 0 ? $r['bangla']  : '') : '' ?></td>
                <td><?= $r ? ($r['english'] > 0 ? $r['english'] : '') : '' ?></td>
                <td><?= $r ? ($r['arabic']  > 0 ? $r['arabic']  : '') : '' ?></td>
                <td><?= $r ? ($r['total']   > 0 ? $r['total']   : '') : '' ?></td>
            </tr>
            <?php endfor; ?>
            <?php if ($pageIndex === count($chunks) - 1): ?>
            <!-- Grand total row on the last page only -->
            <tr class="total-row">
                <td colspan="3" style="text-align:right;font-weight:bold">Total Student</td>
                <td><?= $grandTotal['bangla'] ?></td>
                <td><?= $grandTotal['english'] ?></td>
                <td><?= $grandTotal['arabic'] ?></td>
                <td><?= $grandTotal['total'] ?></td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php endforeach; ?>

    <!-- Bangla Medium summary -->
    <div class="summary-wrap">
        <table>
            <thead class="summary-title">
                <tr><th colspan="7">Bangla Medium Total Information</th></tr>
                <tr>
                    <th>Sl.</th>
                    <th>KG</th>
                    <th>One</th>
                    <th>Two</th>
                    <th>Three</th>
                    <th>Four</th>
                    <th>Five</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td><?= $banglaByClass[6] ?? 0 ?></td>
                    <td><?= $banglaByClass[1] ?? 0 ?></td>
                    <td><?= $banglaByClass[2] ?? 0 ?></td>
                    <td><?= $banglaByClass[3] ?? 0 ?></td>
                    <td><?= $banglaByClass[4] ?? 0 ?></td>
                    <td><?= $banglaByClass[5] ?? 0 ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- English Version summary -->
    <div class="summary-wrap">
        <table>
            <thead class="summary-title">
                <tr><th colspan="7">English Version Total Information</th></tr>
                <tr>
                    <th>Sl.</th>
                    <th>KG</th>
                    <th>One</th>
                    <th>Two</th>
                    <th>Three</th>
                    <th>Four</th>
                    <th>Five</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td><?= $englishByClass[6] ?? 0 ?></td>
                    <td><?= $englishByClass[1] ?? 0 ?></td>
                    <td><?= $englishByClass[2] ?? 0 ?></td>
                    <td><?= $englishByClass[3] ?? 0 ?></td>
                    <td><?= $englishByClass[4] ?? 0 ?></td>
                    <td><?= $englishByClass[5] ?? 0 ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Arabic Version summary -->
    <div class="summary-wrap">
        <table>
            <thead class="summary-title">
                <tr><th colspan="7">Arabic Version Total Information</th></tr>
                <tr>
                    <th>Sl.</th>
                    <th>KG</th>
                    <th>One</th>
                    <th>Two</th>
                    <th>Three</th>
                    <th>Four</th>
                    <th>Five</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td><?= $arabicByClass[6] ?? 0 ?></td>
                    <td><?= $arabicByClass[1] ?? 0 ?></td>
                    <td><?= $arabicByClass[2] ?? 0 ?></td>
                    <td><?= $arabicByClass[3] ?? 0 ?></td>
                    <td><?= $arabicByClass[4] ?? 0 ?></td>
                    <td><?= $arabicByClass[5] ?? 0 ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Signatures -->
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
