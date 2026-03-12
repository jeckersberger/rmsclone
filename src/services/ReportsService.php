<?php
/**
 * Reports & Dashboards Service
 *
 * Stellt Umsatzauswertungen, Auslastungsberichte, Offene-Posten-Listen
 * und Forecast-Daten bereit.
 */
class ReportsService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Revenue report by period, client, or category
     */
    public function revenueReport(int $instanceId, string $from, string $to, ?string $groupBy = 'month'): array
    {
        $baseWhere = "dl.instances_id = ? AND dl.doc_type = 'invoice' AND dl.status IN ('sent','paid','overdue','reminded')";
        $params = [$instanceId];

        if ($groupBy === 'month') {
            $sql = "SELECT DATE_FORMAT(dl.created_at, '%Y-%m') as period,
                           COUNT(*) as invoice_count,
                           SUM(dl.net_amount) as net_total,
                           SUM(dl.gross_amount) as gross_total,
                           SUM(CASE WHEN dl.status='paid' THEN dl.gross_amount ELSE 0 END) as paid_total
                    FROM document_lifecycle dl
                    WHERE {$baseWhere} AND DATE(dl.created_at) BETWEEN ? AND ?
                    GROUP BY DATE_FORMAT(dl.created_at, '%Y-%m')
                    ORDER BY period ASC";
            $params[] = $from;
            $params[] = $to;
        } elseif ($groupBy === 'client') {
            $sql = "SELECT c.clients_name as label, c.clients_id,
                           COUNT(*) as invoice_count,
                           SUM(dl.net_amount) as net_total,
                           SUM(dl.gross_amount) as gross_total,
                           SUM(CASE WHEN dl.status='paid' THEN dl.gross_amount ELSE 0 END) as paid_total
                    FROM document_lifecycle dl
                    JOIN projects p ON dl.projects_id = p.projects_id
                    JOIN clients c ON p.clients_id = c.clients_id
                    WHERE {$baseWhere} AND DATE(dl.created_at) BETWEEN ? AND ?
                    GROUP BY c.clients_id, c.clients_name
                    ORDER BY gross_total DESC";
            $params[] = $from;
            $params[] = $to;
        } else {
            $sql = "SELECT DATE_FORMAT(dl.created_at, '%Y') as period,
                           COUNT(*) as invoice_count,
                           SUM(dl.net_amount) as net_total,
                           SUM(dl.gross_amount) as gross_total,
                           SUM(CASE WHEN dl.status='paid' THEN dl.gross_amount ELSE 0 END) as paid_total
                    FROM document_lifecycle dl
                    WHERE {$baseWhere} AND DATE(dl.created_at) BETWEEN ? AND ?
                    GROUP BY DATE_FORMAT(dl.created_at, '%Y')
                    ORDER BY period ASC";
            $params[] = $from;
            $params[] = $to;
        }

        $rows = $this->db->rawQuery($sql, $params) ?: [];

        $totalNet = 0;
        $totalGross = 0;
        $totalPaid = 0;
        foreach ($rows as &$r) {
            $r['net_total'] = round((float)$r['net_total'], 2);
            $r['gross_total'] = round((float)$r['gross_total'], 2);
            $r['paid_total'] = round((float)$r['paid_total'], 2);
            $totalNet += $r['net_total'];
            $totalGross += $r['gross_total'];
            $totalPaid += $r['paid_total'];
        }
        unset($r);

        return [
            'rows'        => $rows,
            'total_net'   => round($totalNet, 2),
            'total_gross' => round($totalGross, 2),
            'total_paid'  => round($totalPaid, 2),
            'period'      => ['from' => $from, 'to' => $to],
            'group_by'    => $groupBy,
        ];
    }

    /**
     * Outstanding invoices (Offene Posten Liste)
     */
    public function outstandingReport(int $instanceId): array
    {
        $this->db->where('dl.instances_id', $instanceId);
        $this->db->where('dl.doc_type', 'invoice');
        $this->db->where('dl.status', ['sent', 'overdue', 'reminded'], 'IN');
        $this->db->join('projects p', 'dl.projects_id=p.projects_id', 'LEFT');
        $this->db->join('clients c', 'p.clients_id=c.clients_id', 'LEFT');
        $this->db->orderBy('dl.due_date', 'ASC');
        $invoices = $this->db->get('document_lifecycle dl', null, [
            'dl.*', 'p.projects_name', 'c.clients_name'
        ]) ?: [];

        $totalOutstanding = 0;
        $overdueCount = 0;
        $today = date('Y-m-d');
        $agingBuckets = ['current' => 0, '1_30' => 0, '31_60' => 0, '61_90' => 0, 'over_90' => 0];

        foreach ($invoices as &$inv) {
            $inv['gross_amount'] = round((float)$inv['gross_amount'], 2);
            $inv['outstanding'] = $inv['gross_amount'] - round((float)$inv['paid_amount'], 2);
            $totalOutstanding += $inv['outstanding'];

            $daysOverdue = max(0, (int)((strtotime($today) - strtotime($inv['due_date'])) / 86400));
            $inv['days_overdue'] = $daysOverdue;

            if ($daysOverdue > 0) {
                $overdueCount++;
                if ($daysOverdue <= 30) $agingBuckets['1_30'] += $inv['outstanding'];
                elseif ($daysOverdue <= 60) $agingBuckets['31_60'] += $inv['outstanding'];
                elseif ($daysOverdue <= 90) $agingBuckets['61_90'] += $inv['outstanding'];
                else $agingBuckets['over_90'] += $inv['outstanding'];
            } else {
                $agingBuckets['current'] += $inv['outstanding'];
            }
        }
        unset($inv);

        return [
            'invoices'          => $invoices,
            'total_outstanding' => round($totalOutstanding, 2),
            'overdue_count'     => $overdueCount,
            'total_count'       => count($invoices),
            'aging_buckets'     => array_map(function($v) { return round($v, 2); }, $agingBuckets),
        ];
    }

    /**
     * Asset utilization report
     */
    public function utilizationReport(int $instanceId, string $from, string $to): array
    {
        $totalDays = max(1, (int)((strtotime($to) - strtotime($from)) / 86400));

        // Get all asset types with their assignment counts
        $sql = "SELECT at.assetTypes_id, at.assetTypes_name,
                       ac.assetCategories_name,
                       COUNT(DISTINCT a.assets_id) as total_assets,
                       COUNT(DISTINCT aa.assetsAssignments_id) as assignment_count,
                       SUM(DATEDIFF(
                           LEAST(COALESCE(p.projects_dates_deliver_end, p.projects_dates_use_end, ?), ?),
                           GREATEST(COALESCE(p.projects_dates_deliver_start, p.projects_dates_use_start, ?), ?)
                       )) as total_rental_days
                FROM assetTypes at
                JOIN assets a ON a.assetTypes_id = at.assetTypes_id AND a.assets_deleted = 0
                JOIN assetCategories ac ON at.assetCategories_id = ac.assetCategories_id
                LEFT JOIN assetsAssignments aa ON aa.assets_id = a.assets_id AND aa.assetsAssignments_deleted = 0
                LEFT JOIN projects p ON aa.projects_id = p.projects_id AND p.projects_deleted = 0
                    AND COALESCE(p.projects_dates_deliver_start, p.projects_dates_use_start) < ?
                    AND COALESCE(p.projects_dates_deliver_end, p.projects_dates_use_end) > ?
                WHERE a.instances_id = ?
                GROUP BY at.assetTypes_id, at.assetTypes_name, ac.assetCategories_name
                ORDER BY ac.assetCategories_name, at.assetTypes_name";

        $params = [$to, $to, $from, $from, $to, $from, $instanceId];
        $rows = $this->db->rawQuery($sql, $params) ?: [];

        foreach ($rows as &$r) {
            $maxDays = (int)$r['total_assets'] * $totalDays;
            $rentalDays = max(0, (int)$r['total_rental_days']);
            $r['utilization_pct'] = $maxDays > 0 ? round(($rentalDays / $maxDays) * 100, 1) : 0;
            $r['total_rental_days'] = $rentalDays;
            $r['max_possible_days'] = $maxDays;
        }
        unset($r);

        return [
            'period' => ['from' => $from, 'to' => $to, 'total_days' => $totalDays],
            'asset_types' => $rows,
        ];
    }

    /**
     * Dashboard KPIs
     */
    public function dashboardKPIs(int $instanceId): array
    {
        $currentYear = date('Y');
        $currentMonth = date('Y-m');
        $today = date('Y-m-d');

        // Revenue this year
        $sql = "SELECT COALESCE(SUM(gross_amount), 0) as total
                FROM document_lifecycle
                WHERE instances_id = ? AND doc_type = 'invoice'
                AND status IN ('sent','paid','overdue','reminded')
                AND YEAR(created_at) = ?";
        $yearRevenue = $this->db->rawQuery($sql, [$instanceId, $currentYear]);
        $yearRevenue = $yearRevenue ? (float)$yearRevenue[0]['total'] : 0;

        // Revenue this month
        $sql = "SELECT COALESCE(SUM(gross_amount), 0) as total
                FROM document_lifecycle
                WHERE instances_id = ? AND doc_type = 'invoice'
                AND status IN ('sent','paid','overdue','reminded')
                AND DATE_FORMAT(created_at, '%Y-%m') = ?";
        $monthRevenue = $this->db->rawQuery($sql, [$instanceId, $currentMonth]);
        $monthRevenue = $monthRevenue ? (float)$monthRevenue[0]['total'] : 0;

        // Outstanding amount
        $sql = "SELECT COALESCE(SUM(gross_amount - paid_amount), 0) as total, COUNT(*) as cnt
                FROM document_lifecycle
                WHERE instances_id = ? AND doc_type = 'invoice'
                AND status IN ('sent','overdue','reminded')";
        $outstanding = $this->db->rawQuery($sql, [$instanceId]);
        $outstandingAmount = $outstanding ? (float)$outstanding[0]['total'] : 0;
        $outstandingCount = $outstanding ? (int)$outstanding[0]['cnt'] : 0;

        // Overdue amount
        $sql = "SELECT COALESCE(SUM(gross_amount - paid_amount), 0) as total, COUNT(*) as cnt
                FROM document_lifecycle
                WHERE instances_id = ? AND doc_type = 'invoice'
                AND status IN ('sent','overdue','reminded')
                AND due_date < ?";
        $overdue = $this->db->rawQuery($sql, [$instanceId, $today]);
        $overdueAmount = $overdue ? (float)$overdue[0]['total'] : 0;
        $overdueCount = $overdue ? (int)$overdue[0]['cnt'] : 0;

        // Active projects count
        $sql = "SELECT COUNT(*) as cnt FROM projects p
                JOIN projectsStatuses ps ON p.projectsStatuses_id = ps.projectsStatuses_id
                WHERE p.instances_id = ? AND p.projects_deleted = 0 AND p.projects_archived = 0
                AND ps.projectsStatuses_assetsReleased = 0";
        $activeProjects = $this->db->rawQuery($sql, [$instanceId]);
        $activeProjectCount = $activeProjects ? (int)$activeProjects[0]['cnt'] : 0;

        // Upcoming projects (next 30 days)
        $sql = "SELECT COUNT(*) as cnt FROM projects
                WHERE instances_id = ? AND projects_deleted = 0 AND projects_archived = 0
                AND COALESCE(projects_dates_deliver_start, projects_dates_use_start) BETWEEN ? AND ?";
        $upcoming = $this->db->rawQuery($sql, [$instanceId, $today, date('Y-m-d', strtotime('+30 days'))]);
        $upcomingCount = $upcoming ? (int)$upcoming[0]['cnt'] : 0;

        // KUR threshold warning
        $kurWarning = $yearRevenue > 22000;
        $kurExceeded = $yearRevenue > 25000;

        return [
            'revenue_year'       => round($yearRevenue, 2),
            'revenue_month'      => round($monthRevenue, 2),
            'outstanding_amount' => round($outstandingAmount, 2),
            'outstanding_count'  => $outstandingCount,
            'overdue_amount'     => round($overdueAmount, 2),
            'overdue_count'      => $overdueCount,
            'active_projects'    => $activeProjectCount,
            'upcoming_projects'  => $upcomingCount,
            'kur_warning'        => $kurWarning,
            'kur_exceeded'       => $kurExceeded,
        ];
    }
}
