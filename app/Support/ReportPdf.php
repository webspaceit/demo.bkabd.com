<?php

namespace App\Support;

use App\Core\Response;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Output helpers for the report module.
 *
 * Report sheets are rendered as printable HTML by ReportController (the
 * browser's "Print / Save as PDF" produces the PDF) — dompdf's table engine
 * cannot lay out the thousand-row sheets this system produces within
 * shared-hosting memory. The original Excel exports are reproduced here with
 * the vendored PhpSpreadsheet, so the download file types match the original.
 */
final class ReportPdf
{
    /** Raise limits for the memory-hungry spreadsheet writer. */
    private static function bootstrap(): void
    {
        // PhpSpreadsheet needs well over PHP's default 128 MB for large sheets
        // (thousands of rows). Raise for the request only; hosts may cap lower.
        @ini_set('memory_limit', '1024M');
        @set_time_limit(0);
    }

    /**
     * Build a single-sheet .xlsx and send it as a download.
     *
     * @param string[]              $headings
     * @param array<int,array<int,mixed>> $rows
     */
    public static function xlsx(array $headings, array $rows, string $filename, string $sheetTitle = 'Report'): never
    {
        self::bootstrap();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($sheetTitle !== '' ? $sheetTitle : 'Report', 0, 31));

        $col = 1;
        foreach ($headings as $heading) {
            $sheet->setCellValue([$col, 1], (string) $heading);
            $sheet->getStyle([$col, 1])->getFont()->setBold(true);
            $col++;
        }

        $rowNo = 2;
        foreach ($rows as $row) {
            $col = 1;
            foreach ($row as $cell) {
                $sheet->setCellValueExplicit(
                    [$col, $rowNo],
                    $cell === null ? '' : (string) $cell,
                    DataType::TYPE_STRING
                );
                $col++;
            }
            $rowNo++;
        }

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $body = (string) ob_get_clean();
        $spreadsheet->disconnectWorksheets();

        Response::download(
            $body,
            $filename,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    /**
     * Original ReportController::no_data() — shown when a report has no rows.
     * Rendered as a small self-closing page.
     */
    public static function noData(string $org = 'record'): never
    {
        $org = htmlspecialchars($org, ENT_QUOTES, 'UTF-8');
        $html = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
            . '<title>No Data</title><style>'
            . 'body{font-family:Arial,Helvetica,sans-serif;background:#f4f4f4;display:flex;'
            . 'justify-content:center;align-items:center;height:100vh;margin:0}'
            . '.c{background:#fff;padding:40px;border-radius:10px;box-shadow:0 4px 12px rgba(0,0,0,.1);'
            . 'text-align:center;max-width:500px;width:90%;border-left:5px solid #dc3545}'
            . 'h1{color:#dc3545;margin-bottom:15px}p{color:#555;font-size:1.1em;margin-bottom:25px}'
            . '</style></head><body><div class="c"><h1>No Data Found</h1>'
            . '<p>There is no information on this ' . $org . '.</p>'
            . '<p>This tab will close automatically in 5 seconds...</p></div>'
            . '<script>setTimeout(function(){if(window.opener&&!window.opener.closed){window.close();}},5000);</script>'
            . '</body></html>';
        Response::html($html, 404);
    }
}
