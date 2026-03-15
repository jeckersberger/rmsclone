<?php
/**
 * Advanced Reports API — Erweiterte Berichte ueber ReportingService
 *
 * GET Parameters:
 *   type   = pnl | utilization | aging | revenue_per_client | monthly_trend
 *   from   = YYYY-MM-DD (default: Jahresanfang)
 *   to     = YYYY-MM-DD (default: heute)
 *   format = json | csv (default: json)
 *   limit  = int (optional, fuer revenue_per_client, default: 20)
 *   months = int (optional, fuer monthly_trend, default: 12)
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/ReportingService.php';

if (!$AUTH->data['instance']) finish(false, ["message" => "No instance selected"]);
if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["message" => "Permission denied"]);

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$report = new ReportingService($DBLIB, $instanceId);

$type = $_GET['type'] ?? '';
$format = ($_GET['format'] ?? 'json') === 'csv' ? 'csv' : 'json';
$from = isset($_GET['from']) ? preg_replace('/[^0-9\-]/', '', $_GET['from']) : date('Y-01-01');
$to = isset($_GET['to']) ? preg_replace('/[^0-9\-]/', '', $_GET['to']) : date('Y-m-d');
$limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 20;

switch ($type) {
    case 'pnl':
        $data = $report->profitAndLoss($from, $to);
        if ($format === 'csv') {
            $rows = [];
            foreach ($data['months'] as $m) {
                $rows[] = [$m['label'], number_format($m['net_revenue'], 2, ',', '.'), number_format($m['gross_revenue'], 2, ',', '.'), number_format($m['tax'], 2, ',', '.'), number_format($m['credits'], 2, ',', '.'), number_format($m['net_after_credits'], 2, ',', '.'), $m['invoice_count']];
            }
            $rows[] = ['GESAMT', number_format($data['totals']['net_revenue'], 2, ',', '.'), number_format($data['totals']['gross_revenue'], 2, ',', '.'), number_format($data['totals']['tax'], 2, ',', '.'), number_format($data['totals']['credits'], 2, ',', '.'), number_format($data['totals']['net_after_credits'], 2, ',', '.'), $data['totals']['invoices']];
            $csv = ReportingService::toCsv($rows, ['Monat', 'Netto', 'Brutto', 'MwSt', 'Gutschriften', 'Netto n. Gutschr.', 'Anz. Rechnungen']);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="PnL_' . $from . '_' . $to . '.csv"');
            echo $csv;
            exit;
        }
        finish(true, null, $data);
        break;

    case 'utilization':
        $data = $report->assetUtilization($from, $to);
        if ($format === 'csv') {
            $rows = [];
            foreach ($data['types'] as $t) {
                $rows[] = [$t['name'], $t['total_assets'], $t['used_assets'], $t['idle_assets'], $t['assignments'], $t['utilization_pct'] . '%'];
            }
            $csv = ReportingService::toCsv($rows, ['Asset-Typ', 'Gesamt', 'Genutzt', 'Ungenutzt', 'Zuweisungen', 'Auslastung']);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="Utilization_' . $from . '_' . $to . '.csv"');
            echo $csv;
            exit;
        }
        finish(true, null, $data);
        break;

    case 'aging':
        $data = $report->arAging();
        if ($format === 'csv') {
            $rows = [];
            foreach ($data['buckets'] as $bucketKey => $bucket) {
                foreach ($bucket['invoices'] as $inv) {
                    $rows[] = [$bucket['label'], $inv['number'] ?: ('#' . $inv['id']), $inv['date'], $inv['due_date'], $inv['days_overdue'], number_format($inv['amount'], 2, ',', '.'), $inv['client'] ?: '-', $inv['project'] ?: '-'];
                }
            }
            $csv = ReportingService::toCsv($rows, ['Bucket', 'Rechnungs-Nr', 'Datum', 'Faellig', 'Tage ueberfaellig', 'Betrag EUR', 'Kunde', 'Projekt']);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="AR_Aging_' . date('Y-m-d') . '.csv"');
            echo $csv;
            exit;
        }
        finish(true, null, $data);
        break;

    case 'revenue_per_client':
        $data = $report->revenuePerClient($from, $to, $limit);
        if ($format === 'csv') {
            $rows = [];
            foreach ($data['clients'] as $c) {
                $rows[] = [$c['name'], number_format($c['gross_revenue'], 2, ',', '.'), number_format($c['net_revenue'], 2, ',', '.'), $c['invoice_count']];
            }
            $csv = ReportingService::toCsv($rows, ['Kunde', 'Brutto EUR', 'Netto EUR', 'Anz. Rechnungen']);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="Revenue_Clients_' . $from . '_' . $to . '.csv"');
            echo $csv;
            exit;
        }
        finish(true, null, $data);
        break;

    case 'monthly_trend':
        $months = isset($_GET['months']) ? max(1, min(60, (int)$_GET['months'])) : 12;
        $data = $report->monthlyRevenueTrend($months);
        if ($format === 'csv') {
            $rows = [];
            foreach ($data as $m) {
                $rows[] = [$m['label'], number_format($m['revenue'], 2, ',', '.'), $m['invoices']];
            }
            $csv = ReportingService::toCsv($rows, ['Monat', 'Umsatz EUR', 'Anz. Rechnungen']);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="Monthly_Trend.csv"');
            echo $csv;
            exit;
        }
        finish(true, null, ['trend' => $data]);
        break;

    default:
        finish(false, ["message" => "Unknown report type. Available: pnl, utilization, aging, revenue_per_client, monthly_trend"]);
}
