<?php
/**
 * Report Export Service
 *
 * Generischer Export fuer alle Berichtstypen.
 * Unterstuetzt CSV, Excel (PhpSpreadsheet) und PDF (Dompdf).
 */

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ReportExportService
{
    /** @var array Report type to German title mapping */
    private const REPORT_TITLES = [
        'utilization'    => 'Auslastungsbericht',
        'top_clients'    => 'Top-Kunden Ranking',
        'seasonality'    => 'Saisonalitaets-Analyse',
        'roi'            => 'Equipment ROI',
        'compare'        => 'Vergleichsbericht',
        'top_performers' => 'Top-Performer',
        'underutilized'  => 'Unterausgelastete Assets',
        'revenue'        => 'Umsatzbericht',
        'outstanding'    => 'Offene Posten',
        'dunning'        => 'Mahnungen',
    ];

    /**
     * Generischer CSV-Export
     *
     * @param array  $headers  Spaltenkoepfe
     * @param array  $rows     Datenzeilen (Array von Arrays)
     * @param string $filename Dateiname ohne Erweiterung
     * @return array CSV-Daten als Base64 und Metadaten
     */
    public function exportCsv(array $headers, array $rows, string $filename): array
    {
        // UTF-8 BOM fuer Excel
        $csv = chr(0xEF) . chr(0xBB) . chr(0xBF);
        $csv .= implode(';', $headers) . "\r\n";

        foreach ($rows as $row) {
            $escaped = array_map(function ($cell) {
                $cell = str_replace('"', '""', (string)$cell);
                return '"' . $cell . '"';
            }, $row);
            $csv .= implode(';', $escaped) . "\r\n";
        }

        return [
            'csv' => base64_encode($csv),
            'filename' => $filename . '.csv',
            'row_count' => count($rows),
        ];
    }

    /**
     * Export eines bestimmten Berichtstyps als CSV
     *
     * @param string $reportType Berichtstyp
     * @param array  $data       Berichtsdaten
     * @return array CSV-Export-Daten
     */
    public function exportReport(string $reportType, array $data): array
    {
        $report = $this->prepareReportData($reportType, $data);
        if (isset($report['error'])) {
            return $report;
        }

        return $this->exportCsv($report['headers'], $report['rows'], $report['filename']);
    }

    /**
     * Export als Excel (XLSX) via PhpSpreadsheet
     *
     * @param string $reportType Berichtstyp
     * @param array  $data       Berichtsdaten
     * @return array Excel-Daten als Base64 und Metadaten
     */
    public function exportExcel(string $reportType, array $data): array
    {
        $report = $this->prepareReportData($reportType, $data);
        if (isset($report['error'])) {
            return $report;
        }

        $headers = $report['headers'];
        $rows = $report['rows'];
        $filename = $report['filename'];
        $title = self::REPORT_TITLES[$reportType] ?? 'Bericht';

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('RMS Report System')
            ->setTitle($title)
            ->setDescription('Automatisch generierter Bericht');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($title, 0, 31));

        $colCount = count($headers);
        $lastCol = Coordinate::stringFromColumnIndex($colCount);

        // Title row
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells('A1:' . $lastCol . '1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Date row
        $sheet->setCellValue('A2', 'Erstellt am: ' . date('d.m.Y H:i'));
        $sheet->mergeCells('A2:' . $lastCol . '2');
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(9);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Header row (row 4)
        $headerRow = 4;
        foreach ($headers as $colIdx => $header) {
            $col = Coordinate::stringFromColumnIndex($colIdx + 1);
            $sheet->setCellValue($col . $headerRow, $header);
        }

        // Style header row
        $headerRange = 'A' . $headerRow . ':' . $lastCol . $headerRow;
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Data rows
        $dataStartRow = $headerRow + 1;
        foreach ($rows as $rowIdx => $row) {
            $excelRow = $dataStartRow + $rowIdx;
            foreach ($row as $colIdx => $cell) {
                $col = Coordinate::stringFromColumnIndex($colIdx + 1);
                $sheet->setCellValue($col . $excelRow, $cell);
            }

            // Alternate row coloring
            if ($rowIdx % 2 === 0) {
                $rowRange = 'A' . $excelRow . ':' . $lastCol . $excelRow;
                $sheet->getStyle($rowRange)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('D9E2F3');
            }
        }

        // Data borders
        if (count($rows) > 0) {
            $lastDataRow = $dataStartRow + count($rows) - 1;
            $dataRange = 'A' . $dataStartRow . ':' . $lastCol . $lastDataRow;
            $sheet->getStyle($dataRange)->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN);
        }

        // Auto-size columns
        for ($i = 1; $i <= $colCount; $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        // Write to temp file
        $tmpFile = tempnam(sys_get_temp_dir(), 'rms_xlsx_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tmpFile);
        $content = file_get_contents($tmpFile);
        unlink($tmpFile);
        $spreadsheet->disconnectWorksheets();

        return [
            'file' => base64_encode($content),
            'filename' => $filename . '.xlsx',
            'row_count' => count($rows),
            'format' => 'xlsx',
        ];
    }

    /**
     * Export als PDF via Dompdf
     *
     * @param string $reportType Berichtstyp
     * @param array  $data       Berichtsdaten
     * @return array PDF-Daten als Base64 und Metadaten
     */
    public function exportPdf(string $reportType, array $data): array
    {
        $report = $this->prepareReportData($reportType, $data);
        if (isset($report['error'])) {
            return $report;
        }

        $headers = $report['headers'];
        $rows = $report['rows'];
        $filename = $report['filename'];
        $title = self::REPORT_TITLES[$reportType] ?? 'Bericht';

        // Build HTML
        $html = $this->buildPdfHtml($title, $headers, $rows);

        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        // Use landscape for wide tables
        $orientation = count($headers) > 6 ? 'landscape' : 'portrait';
        $dompdf->setPaper('A4', $orientation);
        $dompdf->render();
        $content = $dompdf->output();

        return [
            'file' => base64_encode($content),
            'filename' => $filename . '.pdf',
            'row_count' => count($rows),
            'format' => 'pdf',
        ];
    }

    /**
     * Bereitet die Daten fuer einen bestimmten Berichtstyp auf.
     *
     * @param string $reportType
     * @param array  $data
     * @return array {headers, rows, filename} oder {error}
     */
    private function prepareReportData(string $reportType, array $data): array
    {
        $headers = [];
        $rows = [];
        $filename = 'bericht';

        switch ($reportType) {
            case 'utilization':
                $headers = ['Equipment', 'Kategorie', 'Tage vermietet', 'Tage verfuegbar', 'Auslastung %', 'Umsatz'];
                foreach ($data as $row) {
                    $rows[] = [
                        $row['assetTypes_name'] ?? '-',
                        $row['assetCategories_name'] ?? '-',
                        $row['days_rented'] ?? 0,
                        $row['days_available'] ?? 0,
                        number_format($row['utilization_pct'] ?? 0, 2, ',', '.'),
                        number_format($row['revenue'] ?? 0, 2, ',', '.'),
                    ];
                }
                $filename = 'auslastungsbericht_' . date('Y-m-d');
                break;

            case 'top_clients':
                $headers = ['Rang', 'Kunde', 'Projekte', 'Umsatz', 'Bezahlt'];
                $rank = 1;
                foreach ($data as $row) {
                    $rows[] = [
                        $rank++,
                        $row['clients_name'] ?? '-',
                        $row['project_count'] ?? 0,
                        number_format($row['total_revenue'] ?? 0, 2, ',', '.'),
                        number_format($row['paid_revenue'] ?? 0, 2, ',', '.'),
                    ];
                }
                $filename = 'top_kunden_' . date('Y');
                break;

            case 'seasonality':
                $headers = ['Monat', 'Durchschnittlicher Umsatz', 'Analysierte Jahre'];
                $months = $data['months'] ?? [];
                foreach ($months as $m) {
                    $rows[] = [
                        $m['month_name'] ?? '-',
                        number_format($m['avg_revenue'] ?? 0, 2, ',', '.'),
                        $m['year_count'] ?? 0,
                    ];
                }
                $filename = 'saisonalitaet_' . date('Y');
                break;

            case 'roi':
                $headers = ['Equipment', 'Kategorie', 'Anzahl', 'Kaufpreis', 'Tagesrate', 'Gesamtumsatz', 'ROI %', 'Amortisation (Monate)', 'Profitabel'];
                foreach ($data as $row) {
                    $rows[] = [
                        $row['assetTypes_name'] ?? '-',
                        $row['assetCategories_name'] ?? '-',
                        $row['asset_count'] ?? 0,
                        number_format($row['purchase_price'] ?? 0, 2, ',', '.'),
                        number_format($row['day_rate'] ?? 0, 2, ',', '.'),
                        number_format($row['total_revenue'] ?? 0, 2, ',', '.'),
                        number_format($row['roi_pct'] ?? 0, 2, ',', '.'),
                        $row['payback_months'] !== null ? number_format($row['payback_months'], 1, ',', '.') : '-',
                        ($row['is_profitable'] ?? false) ? 'Ja' : 'Nein',
                    ];
                }
                $filename = 'equipment_roi_' . date('Y-m-d');
                break;

            case 'compare':
                $p1 = $data['period1'] ?? [];
                $p2 = $data['period2'] ?? [];
                $changes = $data['changes'] ?? [];
                $headers = ['Kennzahl', 'Zeitraum 1', 'Zeitraum 2', 'Veraenderung'];
                $rows = [
                    ['Zeitraum', ($p1['start'] ?? '') . ' - ' . ($p1['end'] ?? ''), ($p2['start'] ?? '') . ' - ' . ($p2['end'] ?? ''), ''],
                    ['Umsatz', number_format($p1['revenue'] ?? 0, 2, ',', '.'), number_format($p2['revenue'] ?? 0, 2, ',', '.'), ($changes['revenue_pct'] ?? 0) . '%'],
                    ['Kosten', number_format($p1['costs'] ?? 0, 2, ',', '.'), number_format($p2['costs'] ?? 0, 2, ',', '.'), ''],
                    ['Gewinn', number_format($p1['profit'] ?? 0, 2, ',', '.'), number_format($p2['profit'] ?? 0, 2, ',', '.'), ($changes['profit_pct'] ?? 0) . '%'],
                    ['Marge', ($p1['margin_pct'] ?? 0) . '%', ($p2['margin_pct'] ?? 0) . '%', ''],
                    ['Projekte', $p1['project_count'] ?? 0, $p2['project_count'] ?? 0, ($changes['project_diff'] ?? 0) > 0 ? '+' . $changes['project_diff'] : $changes['project_diff']],
                    ['Kunden', $p1['client_count'] ?? 0, $p2['client_count'] ?? 0, ($changes['client_diff'] ?? 0) > 0 ? '+' . $changes['client_diff'] : $changes['client_diff']],
                ];
                $filename = 'vergleichsbericht_' . date('Y-m-d');
                break;

            case 'top_performers':
                $headers = ['Rang', 'Equipment', 'Kategorie', 'Tage vermietet', 'Auslastung %', 'Umsatz'];
                $rank = 1;
                foreach ($data as $row) {
                    $rows[] = [
                        $rank++,
                        $row['assetTypes_name'] ?? '-',
                        $row['assetCategories_name'] ?? '-',
                        $row['days_rented'] ?? 0,
                        number_format($row['utilization_pct'] ?? 0, 2, ',', '.'),
                        number_format($row['revenue'] ?? 0, 2, ',', '.'),
                    ];
                }
                $filename = 'top_performer_' . date('Y');
                break;

            case 'underutilized':
                $headers = ['Equipment', 'Kategorie', 'Tage vermietet', 'Auslastung %', 'Tagesrate', 'Umsatz'];
                foreach ($data as $row) {
                    $rows[] = [
                        $row['assetTypes_name'] ?? '-',
                        $row['assetCategories_name'] ?? '-',
                        $row['days_rented'] ?? 0,
                        number_format($row['utilization_pct'] ?? 0, 2, ',', '.'),
                        number_format($row['day_rate'] ?? 0, 2, ',', '.'),
                        number_format($row['revenue'] ?? 0, 2, ',', '.'),
                    ];
                }
                $filename = 'unterausgelastet_' . date('Y');
                break;

            default:
                return ['error' => 'Unbekannter Berichtstyp: ' . $reportType];
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
            'filename' => $filename,
        ];
    }

    /**
     * Erzeugt die HTML-Darstellung fuer den PDF-Export.
     *
     * @param string $title
     * @param array  $headers
     * @param array  $rows
     * @return string
     */
    private function buildPdfHtml(string $title, array $headers, array $rows): string
    {
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
        $html .= '<style>
            body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #333; margin: 20px; }
            h1 { font-size: 18px; color: #2c3e50; border-bottom: 2px solid #4472C4; padding-bottom: 8px; margin-bottom: 5px; }
            .meta { font-size: 9px; color: #888; margin-bottom: 15px; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th { background-color: #4472C4; color: #fff; padding: 6px 8px; text-align: left; font-size: 10px; }
            td { padding: 5px 8px; border-bottom: 1px solid #ddd; font-size: 9px; }
            tr:nth-child(even) td { background-color: #f2f6fc; }
            .text-right { text-align: right; }
            .footer { margin-top: 20px; font-size: 8px; color: #aaa; text-align: center; border-top: 1px solid #ddd; padding-top: 5px; }
        </style>';
        $html .= '</head><body>';
        $html .= '<h1>' . htmlspecialchars($title) . '</h1>';
        $html .= '<div class="meta">Erstellt am: ' . date('d.m.Y H:i') . '</div>';
        $html .= '<table><thead><tr>';

        foreach ($headers as $h) {
            $html .= '<th>' . htmlspecialchars($h) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . htmlspecialchars((string)$cell) . '</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        $html .= '<div class="footer">RMS Berichtssystem &ndash; ' . htmlspecialchars($title) . ' &ndash; ' . date('d.m.Y') . '</div>';
        $html .= '</body></html>';

        return $html;
    }
}
