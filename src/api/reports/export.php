<?php
/**
 * CSV-Export fuer Berichte
 *
 * POST-Parameter:
 *   type     - 'revenue' | 'outstanding' | 'dunning'
 *   year     - Jahr (optional, Standard: aktuelles Jahr)
 *   group_by - 'month' | 'client' (nur bei revenue)
 */
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$type = $_POST['type'] ?? 'revenue';
$year = (int)($_POST['year'] ?? date('Y'));
$groupBy = $_POST['group_by'] ?? 'month';

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

    default:
        finish(false, ["code" => "INVALID_TYPE"]);
}

// Build CSV
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
    'row_count' => count($rows)
]);
