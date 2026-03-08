<?php
/**
 * Report Export Service
 *
 * Generischer CSV-Export fuer alle Berichtstypen.
 * Erzeugt UTF-8-BOM-kodierte CSV-Dateien mit Semikolon-Trennung
 * fuer optimale Excel-Kompatibilitaet.
 */
class ReportExportService
{
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

        return $this->exportCsv($headers, $rows, $filename);
    }
}
