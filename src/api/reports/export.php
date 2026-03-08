<?php
/**
 * Report-Export (CSV, Excel, PDF)
 *
 * POST-Parameter:
 *   type     - 'revenue' | 'outstanding' | 'dunning' | 'utilization' | ...
 *   format   - 'csv' | 'xlsx' | 'pdf' (Standard: csv)
 *   year     - Jahr (optional, Standard: aktuelles Jahr)
 *   group_by - 'month' | 'client' (nur bei revenue)
 */
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$type = $_POST['type'] ?? 'revenue';
$format = $_POST['format'] ?? 'csv';
$year = (int)($_POST['year'] ?? date('Y'));
$groupBy = $_POST['group_by'] ?? 'month';

// Validate format
if (!in_array($format, ['csv', 'xlsx', 'pdf'])) {
    $format = 'csv';
}

$rows = [];
$headers = [];
$filename = 'export';

switch ($type) {
    case 'revenue':
        $svc = new ReportsService($DBLIB);
        $from = $year . '-01-01';
        $to = $year . '-12-31';
        $report = $svc->revenueReport($instanceId, $from, $to, $groupBy);
        $data = $report['data'] ?? [];

        if ($groupBy === 'month') {
            $headers = ['Monat', 'Netto', 'Brutto', 'Anzahl'];
            $monthNames = ['Januar','Februar','Maerz','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
            foreach ($data as $row) {
                $rows[] = [
                    $monthNames[($row['month'] ?? 1) - 1],
                    number_format($row['net_total'] ?? 0, 2, ',', '.'),
                    number_format($row['gross_total'] ?? 0, 2, ',', '.'),
                    $row['invoice_count'] ?? 0
                ];
            }
        } else {
            $headers = ['Kunde', 'Netto', 'Brutto', 'Anzahl'];
            foreach ($data as $row) {
                $rows[] = [
                    $row['client_name'] ?? '-',
                    number_format($row['net_total'] ?? 0, 2, ',', '.'),
                    number_format($row['gross_total'] ?? 0, 2, ',', '.'),
                    $row['invoice_count'] ?? 0
                ];
            }
        }
        $filename = 'umsatzbericht_' . $year;
        break;

    case 'outstanding':
        $svc = new ReportsService($DBLIB);
        $report = $svc->outstandingReport($instanceId);
        $invoices = $report['invoices'] ?? [];

        $headers = ['Beleg-Nr.', 'Projekt', 'Kunde', 'Betrag', 'Faelligkeitsdatum', 'Tage ueberfaellig'];
        foreach ($invoices as $inv) {
            $daysOverdue = (int)((time() - strtotime($inv['due_date'] ?? '')) / 86400);
            $rows[] = [
                $inv['doc_number'] ?? '-',
                $inv['projects_name'] ?? '-',
                $inv['clients_name'] ?? '-',
                number_format($inv['gross_amount'] ?? 0, 2, ',', '.'),
                $inv['due_date'] ?? '-',
                max(0, $daysOverdue)
            ];
        }
        $filename = 'ausstehende_rechnungen_' . date('Y-m-d');
        break;

    case 'dunning':
        $svc = new DunningService($DBLIB);
        $overdue = $svc->getOverdueInvoices($instanceId);

        $headers = ['Beleg-Nr.', 'Projekt', 'Kunde', 'Betrag', 'Faelligkeitsdatum', 'Tage ueberfaellig', 'Mahnstufe'];
        $levelNames = [0 => 'Keine', 1 => 'Zahlungserinnerung', 2 => '1. Mahnung', 3 => '2. Mahnung', 4 => 'Letzte Mahnung'];
        foreach ($overdue as $inv) {
            $rows[] = [
                $inv['doc_number'] ?? '-',
                $inv['projects_name'] ?? '-',
                $inv['clients_name'] ?? '-',
                number_format($inv['gross_amount'] ?? 0, 2, ',', '.'),
                $inv['due_date'] ?? '-',
                $inv['days_overdue'] ?? 0,
                $inv['dunning_level'] ?? 0
            ];
        }
        $filename = 'mahnungen_' . date('Y-m-d');
        break;

    case 'utilization':
    case 'top_clients':
    case 'seasonality':
    case 'roi':
    case 'compare':
    case 'top_performers':
    case 'underutilized':
        $exportSvc = new ReportExportService();
        $reportData = [];

        if ($type === 'utilization') {
            $utilSvc = new UtilizationReportService($DBLIB);
            $month = (int)($_POST['month'] ?? date('n'));
            $reportData = $utilSvc->getUtilizationOverview($instanceId, $year, $month);
        } elseif ($type === 'top_clients') {
            $profitSvc = new ProfitCalculationService($DBLIB);
            $limit = (int)($_POST['limit'] ?? 10);
            $reportData = $profitSvc->getTopClients($instanceId, $year, $limit);
        } elseif ($type === 'seasonality') {
            $profitSvc = new ProfitCalculationService($DBLIB);
            $years = (int)($_POST['years'] ?? 3);
            $reportData = $profitSvc->getSeasonality($instanceId, $years);
        } elseif ($type === 'roi') {
            $utilSvc = new UtilizationReportService($DBLIB);
            $sql = "SELECT DISTINCT at.assetTypes_id FROM assetTypes at
                    JOIN assets a ON a.assetTypes_id = at.assetTypes_id AND a.assets_deleted = 0
                    WHERE a.instances_id = ?";
            $types_list = $DBLIB->rawQuery($sql, [$instanceId]) ?: [];
            $reportData = [];
            foreach ($types_list as $t) {
                $roi = $utilSvc->calculateRoi((int)$t['assetTypes_id']);
                if (!isset($roi['error'])) $reportData[] = $roi;
            }
        } elseif ($type === 'compare') {
            $profitSvc = new ProfitCalculationService($DBLIB);
            $p1s = $_POST['period1_start'] ?? date('Y-01-01', strtotime('-1 year'));
            $p1e = $_POST['period1_end'] ?? date('Y-12-31', strtotime('-1 year'));
            $p2s = $_POST['period2_start'] ?? date('Y-01-01');
            $p2e = $_POST['period2_end'] ?? date('Y-m-d');
            $reportData = $profitSvc->comparePeriods($instanceId, $p1s, $p1e, $p2s, $p2e);
        } elseif ($type === 'top_performers') {
            $utilSvc = new UtilizationReportService($DBLIB);
            $reportData = $utilSvc->getTopPerformers($instanceId, $year);
        } elseif ($type === 'underutilized') {
            $utilSvc = new UtilizationReportService($DBLIB);
            $threshold = (int)($_POST['threshold'] ?? 30);
            $reportData = $utilSvc->getUnderutilized($instanceId, $year, $threshold);
        }

        if ($format === 'xlsx') {
            $result = $exportSvc->exportExcel($type, $reportData);
        } elseif ($format === 'pdf') {
            $result = $exportSvc->exportPdf($type, $reportData);
        } else {
            $result = $exportSvc->exportReport($type, $reportData);
        }
        if (isset($result['error'])) {
            finish(false, ["code" => "EXPORT_ERROR", "message" => $result['error']]);
        }
        finish(true, null, $result);
        break;

    default:
        finish(false, ["code" => "INVALID_TYPE"]);
}

// Build export based on format
$exportSvc2 = new ReportExportService();

if ($format === 'xlsx') {
    $csvResult = $exportSvc2->exportCsv($headers, $rows, $filename);
    // Re-parse CSV into structured data for Excel export
    $result = $exportSvc2->exportExcel('_raw', []);
    // Simpler: build Excel directly from headers+rows
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Export');
    foreach ($headers as $colIdx => $header) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1);
        $sheet->setCellValue($colLetter . '1', $header);
    }
    $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
    $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
    $sheet->getStyle("A1:{$lastCol}1")->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FFD9E1F2');
    foreach ($rows as $rowIdx => $row) {
        foreach ($row as $colIdx => $cell) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1);
            $sheet->setCellValue($colLetter . ($rowIdx + 2), $cell);
        }
    }
    foreach (range('A', $lastCol) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    $tmpFile = tempnam(sys_get_temp_dir(), 'rms_xlsx_');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save($tmpFile);
    $xlsxContent = file_get_contents($tmpFile);
    unlink($tmpFile);
    finish(true, null, [
        'data' => base64_encode($xlsxContent),
        'filename' => $filename . '.xlsx',
        'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'row_count' => count($rows),
    ]);
} elseif ($format === 'pdf') {
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
    $html .= '<style>body{font-family:DejaVu Sans,sans-serif;font-size:10px;margin:20px;}h1{font-size:16px;}table{width:100%;border-collapse:collapse;}th{background:#2c3e50;color:white;padding:6px 8px;text-align:left;font-size:9px;}td{padding:5px 8px;border-bottom:1px solid #ddd;font-size:9px;}tr:nth-child(even) td{background:#f9f9f9;}</style></head><body>';
    $html .= '<h1>' . htmlspecialchars($filename) . '</h1>';
    $html .= '<div style="color:#666;margin-bottom:15px;font-size:9px;">Erstellt am ' . date('d.m.Y H:i') . '</div>';
    $html .= '<table><thead><tr>';
    foreach ($headers as $h) $html .= '<th>' . htmlspecialchars($h) . '</th>';
    $html .= '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach ($row as $cell) $html .= '<td>' . htmlspecialchars((string)$cell) . '</td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table></body></html>';
    $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false]);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    finish(true, null, [
        'data' => base64_encode($dompdf->output()),
        'filename' => $filename . '.pdf',
        'mime' => 'application/pdf',
        'row_count' => count($rows),
    ]);
} else {
    // CSV (default)
    $csv = chr(0xEF) . chr(0xBB) . chr(0xBF); // UTF-8 BOM for Excel
    $csv .= implode(';', $headers) . "\r\n";
    foreach ($rows as $row) {
        $escaped = array_map(function($cell) {
            $cell = str_replace('"', '""', (string)$cell);
            return '"' . $cell . '"';
        }, $row);
        $csv .= implode(';', $escaped) . "\r\n";
    }
    finish(true, null, [
        'csv' => base64_encode($csv),
        'filename' => $filename . '.csv',
        'row_count' => count($rows),
    ]);
}
