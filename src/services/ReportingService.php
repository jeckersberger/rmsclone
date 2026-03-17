<?php
/**
 * ReportingService — Zentrale Berichterstellung
 *
 * Liefert:
 * - P&L (Profit & Loss) nach Monat
 * - Asset-Auslastung (Utilization) nach Typ/Kategorie
 * - A/R Aging (Forderungsalter-Analyse)
 * - Revenue per Client Ranking
 * - Monthly Trend (beliebiger Zeitraum)
 *
 * Nutzung:
 *   $report = new ReportingService($DBLIB, $instanceId);
 *   $pnl = $report->profitAndLoss('2025-01-01', '2025-12-31');
 */
class ReportingService
{
    private $db;
    private int $instanceId;

    public function __construct($db, int $instanceId)
    {
        $this->db = $db;
        $this->instanceId = $instanceId;
    }

    /**
     * Profit & Loss — monatliche Aufschluesselung
     *
     * @param string $from  Startdatum (Y-m-d)
     * @param string $to    Enddatum (Y-m-d)
     * @return array ['months' => [...], 'totals' => [...]]
     */
    public function profitAndLoss(string $from, string $to): array
    {
        // Revenue (Invoices) - use rawQuery for aggregation with DATE_FORMAT
        $sql = "SELECT DATE_FORMAT(de.generated_at, '%Y-%m') AS month_key,
                       SUM(de.totals_json->'$.net_total') AS net_revenue,
                       SUM(de.totals_json->'$.gross_total') AS gross_revenue,
                       SUM(de.totals_json->'$.tax_total') AS tax_amount,
                       COUNT(*) AS invoice_count
                FROM document_exports de
                WHERE de.instances_id = ?
                AND de.type = 'invoice'
                AND DATE(de.generated_at) >= ?
                AND DATE(de.generated_at) <= ?
                GROUP BY DATE_FORMAT(de.generated_at, '%Y-%m')
                ORDER BY month_key ASC";
        $revenueRows = $this->db->rawQuery($sql, [$this->instanceId, $from, $to]) ?: [];

        // Credit notes
        $sql = "SELECT DATE_FORMAT(de.generated_at, '%Y-%m') AS month_key,
                       SUM(de.totals_json->'$.gross_total') AS credit_total,
                       COUNT(*) AS credit_count
                FROM document_exports de
                WHERE de.instances_id = ?
                AND de.type = 'credit'
                AND DATE(de.generated_at) >= ?
                AND DATE(de.generated_at) <= ?
                GROUP BY DATE_FORMAT(de.generated_at, '%Y-%m')";
        $creditRows = $this->db->rawQuery($sql, [$this->instanceId, $from, $to]) ?: [];

        // Index credit notes by month
        $credits = [];
        foreach (($creditRows ?: []) as $cr) {
            $credits[$cr['month_key']] = $cr;
        }

        $months = [];
        $totals = ['net_revenue' => 0, 'gross_revenue' => 0, 'tax' => 0, 'credits' => 0, 'net_after_credits' => 0, 'invoices' => 0];

        foreach (($revenueRows ?: []) as $row) {
            $key = $row['month_key'];
            $creditAmount = isset($credits[$key]) ? (float)$credits[$key]['credit_total'] : 0;
            $netAfterCredits = (float)$row['gross_revenue'] - $creditAmount;

            $months[] = [
                'month' => $key,
                'label' => $this->monthLabel($key),
                'net_revenue' => (float)$row['net_revenue'] / 100,
                'gross_revenue' => (float)$row['gross_revenue'] / 100,
                'tax' => (float)$row['tax_amount'] / 100,
                'credits' => $creditAmount / 100,
                'net_after_credits' => $netAfterCredits / 100,
                'invoice_count' => (int)$row['invoice_count']
            ];

            $totals['net_revenue'] += (float)$row['net_revenue'] / 100;
            $totals['gross_revenue'] += (float)$row['gross_revenue'] / 100;
            $totals['tax'] += (float)$row['tax_amount'] / 100;
            $totals['credits'] += $creditAmount / 100;
            $totals['net_after_credits'] += $netAfterCredits / 100;
            $totals['invoices'] += (int)$row['invoice_count'];
        }

        return ['months' => $months, 'totals' => $totals, 'period' => ['from' => $from, 'to' => $to]];
    }

    /**
     * Asset-Auslastung — gruppiert nach AssetType
     *
     * @param string $from  Startdatum
     * @param string $to    Enddatum
     * @return array
     */
    public function assetUtilization(string $from, string $to): array
    {
        // Total assets per type
        $sql1 = "SELECT at.assetTypes_id, at.assetTypes_name, COUNT(*) AS total_assets
                 FROM assets a
                 LEFT JOIN assetTypes at ON a.assetTypes_id = at.assetTypes_id
                 WHERE a.instances_id = ? AND a.assets_deleted = 0
                 GROUP BY at.assetTypes_id
                 ORDER BY total_assets DESC";

        $assetCounts = $this->db->rawQuery($sql1, [$this->instanceId]);

        // Assignments in period
        $sql2 = "SELECT at.assetTypes_id,
                        COUNT(DISTINCT a.assets_id) AS used_assets,
                        COUNT(*) AS assignment_count
                 FROM assetsAssignments aa
                 INNER JOIN assets a ON aa.assets_id = a.assets_id
                 LEFT JOIN assetTypes at ON a.assetTypes_id = at.assetTypes_id
                 LEFT JOIN projects p ON aa.projects_id = p.projects_id
                 WHERE aa.instances_id = ?
                   AND p.projects_dates_use_start <= ?
                   AND p.projects_dates_use_end >= ?
                 GROUP BY at.assetTypes_id";

        $assignmentCounts = $this->db->rawQuery($sql2, [$this->instanceId, $to, $from]);

        // Index assignments
        $assignments = [];
        foreach (($assignmentCounts ?: []) as $ac) {
            $assignments[$ac['assetTypes_id']] = $ac;
        }

        $result = [];
        $totalAssets = 0;
        $totalUsed = 0;
        foreach (($assetCounts ?: []) as $ac) {
            $typeId = $ac['assetTypes_id'];
            $total = (int)$ac['total_assets'];
            $used = isset($assignments[$typeId]) ? (int)$assignments[$typeId]['used_assets'] : 0;
            $assigns = isset($assignments[$typeId]) ? (int)$assignments[$typeId]['assignment_count'] : 0;
            $utilization = $total > 0 ? round(($used / $total) * 100, 1) : 0;

            $result[] = [
                'assetType_id' => $typeId,
                'name' => $ac['assetTypes_name'] ?: 'Unbekannt',
                'total_assets' => $total,
                'used_assets' => $used,
                'idle_assets' => $total - $used,
                'assignments' => $assigns,
                'utilization_pct' => $utilization
            ];

            $totalAssets += $total;
            $totalUsed += $used;
        }

        return [
            'types' => $result,
            'summary' => [
                'total_assets' => $totalAssets,
                'total_used' => $totalUsed,
                'total_idle' => $totalAssets - $totalUsed,
                'avg_utilization' => $totalAssets > 0 ? round(($totalUsed / $totalAssets) * 100, 1) : 0
            ],
            'period' => ['from' => $from, 'to' => $to]
        ];
    }

    /**
     * Accounts Receivable Aging — detailliert
     *
     * @return array Buckets mit einzelnen Rechnungen
     */
    public function arAging(): array
    {
        $today = date('Y-m-d');

        // Use document_lifecycle table for unpaid invoices with due dates
        $sql = "SELECT dl.id, dl.doc_number, dl.created_at,
                       dl.gross_amount, dl.due_date,
                       p.projects_id, p.projects_name, c.clients_name, c.clients_id
                FROM document_lifecycle dl
                LEFT JOIN projects p ON dl.projects_id = p.projects_id
                LEFT JOIN clients c ON p.clients_id = c.clients_id
                WHERE dl.instances_id = ?
                AND dl.doc_type = 'invoice'
                AND dl.status NOT IN ('paid')
                ORDER BY dl.created_at ASC";
        $invoices = $this->db->rawQuery($sql, [$this->instanceId]) ?: [];

        $buckets = [
            '0-30' => ['label' => '0-30 Tage', 'invoices' => [], 'total' => 0, 'count' => 0],
            '31-60' => ['label' => '31-60 Tage', 'invoices' => [], 'total' => 0, 'count' => 0],
            '61-90' => ['label' => '61-90 Tage', 'invoices' => [], 'total' => 0, 'count' => 0],
            '90+' => ['label' => 'Ueber 90 Tage', 'invoices' => [], 'total' => 0, 'count' => 0],
        ];

        $grandTotal = 0;
        foreach (($invoices ?: []) as $inv) {
            $dueDate = $inv['due_date'] ?: $inv['created_at'];
            $daysOld = max(0, (int)((strtotime($today) - strtotime($dueDate)) / 86400));
            $amount = (float)$inv['gross_amount'] / 100;

            if ($daysOld <= 30) $bucket = '0-30';
            elseif ($daysOld <= 60) $bucket = '31-60';
            elseif ($daysOld <= 90) $bucket = '61-90';
            else $bucket = '90+';

            $buckets[$bucket]['invoices'][] = [
                'id' => $inv['id'],
                'number' => $inv['doc_number'],
                'date' => $inv['created_at'],
                'due_date' => $dueDate,
                'days_overdue' => $daysOld,
                'amount' => $amount,
                'client' => $inv['clients_name'],
                'client_id' => $inv['clients_id'],
                'project' => $inv['projects_name'],
                'project_id' => $inv['projects_id'],
            ];
            $buckets[$bucket]['total'] += $amount;
            $buckets[$bucket]['count']++;
            $grandTotal += $amount;
        }

        return [
            'buckets' => $buckets,
            'grand_total' => $grandTotal,
            'total_invoices' => count($invoices ?: [])
        ];
    }

    /**
     * Revenue per Client — Top N Kunden nach Umsatz
     *
     * @param string $from
     * @param string $to
     * @param int $limit
     * @return array
     */
    public function revenuePerClient(string $from, string $to, int $limit = 20): array
    {
        $sql = "SELECT c.clients_id, c.clients_name,
                       SUM(dl.gross_amount) AS total_revenue,
                       SUM(dl.net_amount) AS net_revenue,
                       COUNT(*) AS invoice_count
                FROM document_lifecycle dl
                LEFT JOIN projects p ON dl.projects_id = p.projects_id
                LEFT JOIN clients c ON p.clients_id = c.clients_id
                WHERE dl.instances_id = ?
                AND dl.doc_type = 'invoice'
                AND DATE(dl.created_at) >= ?
                AND DATE(dl.created_at) <= ?
                GROUP BY c.clients_id
                ORDER BY total_revenue DESC
                LIMIT ?";
        $rows = $this->db->rawQuery($sql, [$this->instanceId, $from, $to, $limit]) ?: [];

        $result = [];
        foreach (($rows ?: []) as $row) {
            $result[] = [
                'client_id' => $row['clients_id'],
                'name' => $row['clients_name'] ?: 'Unbekannt',
                'gross_revenue' => (float)$row['total_revenue'] / 100,
                'net_revenue' => (float)$row['net_revenue'] / 100,
                'invoice_count' => (int)$row['invoice_count']
            ];
        }

        return ['clients' => $result, 'period' => ['from' => $from, 'to' => $to]];
    }

    /**
     * Monatlicher Revenue-Trend (fuer Charts)
     *
     * @param int $months  Anzahl Monate zurueck
     * @return array
     */
    public function monthlyRevenueTrend(int $months = 12): array
    {
        $from = date('Y-m-01', strtotime("-{$months} months"));
        $to = date('Y-m-d');

        $sql = "SELECT DATE_FORMAT(dl.created_at, '%Y-%m') AS month_key,
                       SUM(dl.gross_amount) AS revenue,
                       COUNT(*) AS invoice_count
                FROM document_lifecycle dl
                WHERE dl.instances_id = ?
                AND dl.doc_type = 'invoice'
                AND DATE(dl.created_at) >= ?
                AND DATE(dl.created_at) <= ?
                GROUP BY DATE_FORMAT(dl.created_at, '%Y-%m')
                ORDER BY month_key ASC";
        $rows = $this->db->rawQuery($sql, [$this->instanceId, $from, $to]) ?: [];

        $trend = [];
        foreach (($rows ?: []) as $row) {
            $trend[] = [
                'month' => $row['month_key'],
                'label' => $this->monthLabel($row['month_key']),
                'revenue' => (float)$row['revenue'] / 100,
                'invoices' => (int)$row['invoice_count']
            ];
        }

        return $trend;
    }

    /**
     * Export-Helper: Array zu CSV-String
     *
     * @param array $data     Flaches Array von Rows
     * @param array $headers  Spaltenkoepfe
     * @return string         CSV content
     */
    public static function toCsv(array $data, array $headers): string
    {
        $output = fopen('php://temp', 'r+');
        // BOM for Excel UTF-8 compatibility
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, $headers, ';');
        foreach ($data as $row) {
            fputcsv($output, $row, ';');
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        return $csv;
    }

    private function monthLabel(string $yearMonth): string
    {
        $months = ['01' => 'Jan', '02' => 'Feb', '03' => 'Maer', '04' => 'Apr', '05' => 'Mai', '06' => 'Jun',
                    '07' => 'Jul', '08' => 'Aug', '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Dez'];
        $parts = explode('-', $yearMonth);
        return ($months[$parts[1]] ?? $parts[1]) . ' ' . $parts[0];
    }
}
