<?php
/**
 * Gewinnberechnung pro Projekt
 *
 * Automatische Kalkulation:
 * - Umsatz (Vermieteinnahmen)
 * - Abzuege: Fremdleistungen, Personal, Partner-Equipment
 * - Gewinn = Umsatz - alle Kosten
 * - Marge in %
 */
class ProfitCalculationService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Calculate profit for a single project
     */
    public function calculateProjectProfit(int $projectId): array
    {
        // Revenue: Equipment rental income
        $sql = "SELECT COALESCE(SUM(
                    CASE
                        WHEN aa.assetsAssignments_customPrice IS NOT NULL THEN aa.assetsAssignments_customPrice
                        ELSE at.assetTypes_dayRate
                    END
                    * (1 - COALESCE(aa.assetsAssignments_discount, 0) / 100)
                ), 0) as equipment_revenue
                FROM assetsAssignments aa
                JOIN assets a ON aa.assets_id = a.assets_id
                JOIN assetTypes at ON a.assetTypes_id = at.assetTypes_id
                WHERE aa.projects_id = ? AND aa.assetsAssignments_deleted = 0 AND a.assets_deleted = 0";
        $eqResult = $this->db->rawQuery($sql, [$projectId]);
        $equipmentRevenue = $eqResult[0]['equipment_revenue'] ?? 0;

        // Additional sales revenue
        $this->db->where('projects_id', $projectId);
        $this->db->where('payments_type', 2); // sales
        $this->db->where('payments_deleted', 0);
        $salesResult = $this->db->getValue('payments', 'COALESCE(SUM(payments_amount * payments_quantity), 0)');
        $salesRevenue = (float)$salesResult;

        // Costs: Staff
        $this->db->where('projects_id', $projectId);
        $this->db->where('payments_type', 4); // staff
        $this->db->where('payments_deleted', 0);
        $staffCost = (float)$this->db->getValue('payments', 'COALESCE(SUM(payments_amount * payments_quantity), 0)');

        // Costs: Sub-hire / Additional hires
        $this->db->where('projects_id', $projectId);
        $this->db->where('payments_type', 3); // sub-hire
        $this->db->where('payments_deleted', 0);
        $subHireCost = (float)$this->db->getValue('payments', 'COALESCE(SUM(payments_amount * payments_quantity), 0)');

        // Total
        $totalRevenue = $equipmentRevenue + $salesRevenue;
        $totalCosts = $staffCost + $subHireCost;
        $profit = $totalRevenue - $totalCosts;
        $margin = $totalRevenue > 0 ? round(($profit / $totalRevenue) * 100, 1) : 0;

        return [
            'revenue' => [
                'equipment' => $equipmentRevenue,
                'sales' => $salesRevenue,
                'total' => $totalRevenue,
            ],
            'costs' => [
                'staff' => $staffCost,
                'sub_hire' => $subHireCost,
                'total' => $totalCosts,
            ],
            'profit' => $profit,
            'margin_pct' => $margin,
        ];
    }

    /**
     * Calculate profit summary for a date range
     */
    public function calculatePeriodProfit(int $instanceId, string $dateFrom, string $dateTo): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('projects_deleted', 0);
        $this->db->where("(projects_dates_use_start >= '$dateFrom' AND projects_dates_use_start <= '$dateTo')");
        $projects = $this->db->get('projects', null, ['projects_id', 'projects_name']);

        $totals = ['revenue' => 0, 'costs' => 0, 'profit' => 0];
        $projectProfits = [];

        foreach (($projects ?: []) as $p) {
            $profit = $this->calculateProjectProfit($p['projects_id']);
            $totals['revenue'] += $profit['revenue']['total'];
            $totals['costs'] += $profit['costs']['total'];
            $totals['profit'] += $profit['profit'];

            $projectProfits[] = [
                'projects_id' => $p['projects_id'],
                'projects_name' => $p['projects_name'],
                'profit' => $profit,
            ];
        }

        $totals['margin_pct'] = $totals['revenue'] > 0 ? round(($totals['profit'] / $totals['revenue']) * 100, 1) : 0;

        // Sort by profit descending
        usort($projectProfits, fn($a, $b) => $b['profit']['profit'] <=> $a['profit']['profit']);

        return [
            'totals' => $totals,
            'projects' => $projectProfits,
        ];
    }

    /**
     * Top-Kunden nach Umsatz
     */
    public function getTopClients(int $instanceId, int $year, int $limit = 10): array
    {
        $sql = "SELECT c.clients_id, c.clients_name,
                       COUNT(DISTINCT p.projects_id) as project_count,
                       COALESCE(SUM(dl.gross_amount), 0) as total_revenue,
                       COALESCE(SUM(CASE WHEN dl.status='paid' THEN dl.gross_amount ELSE 0 END), 0) as paid_revenue
                FROM clients c
                JOIN projects p ON p.clients_id = c.clients_id AND p.projects_deleted = 0
                LEFT JOIN document_lifecycle dl ON dl.projects_id = p.projects_id
                    AND dl.doc_type = 'invoice'
                    AND dl.status IN ('sent','paid','overdue','reminded')
                    AND YEAR(dl.created_at) = ?
                WHERE p.instances_id = ?
                GROUP BY c.clients_id, c.clients_name
                HAVING total_revenue > 0
                ORDER BY total_revenue DESC
                LIMIT ?";
        $rows = $this->db->rawQuery($sql, [$year, $instanceId, $limit]) ?: [];

        foreach ($rows as &$r) {
            $r['total_revenue'] = round((float)$r['total_revenue'], 2);
            $r['paid_revenue'] = round((float)$r['paid_revenue'], 2);
        }
        unset($r);

        return $rows;
    }

    /**
     * Umsatztrend eines Kunden ueber die letzten Monate
     */
    public function getClientRevenueTrend(int $instanceId, int $clientId, int $months = 12): array
    {
        $rows = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $monthStart = date('Y-m-01', strtotime("-{$i} months"));
            $monthEnd = date('Y-m-t', strtotime("-{$i} months"));
            $label = date('M Y', strtotime("-{$i} months"));

            $sql = "SELECT COALESCE(SUM(dl.gross_amount), 0) as revenue,
                           COUNT(*) as invoice_count
                    FROM document_lifecycle dl
                    JOIN projects p ON dl.projects_id = p.projects_id
                    WHERE p.clients_id = ? AND p.instances_id = ?
                    AND dl.doc_type = 'invoice'
                    AND dl.status IN ('sent','paid','overdue','reminded')
                    AND DATE(dl.created_at) BETWEEN ? AND ?";
            $result = $this->db->rawQuery($sql, [$clientId, $instanceId, $monthStart, $monthEnd]);

            $rows[] = [
                'month' => $label,
                'month_start' => $monthStart,
                'revenue' => $result ? round((float)$result[0]['revenue'], 2) : 0,
                'invoice_count' => $result ? (int)$result[0]['invoice_count'] : 0,
            ];
        }

        return $rows;
    }

    /**
     * Saisonalitaets-Analyse: Durchschnittlicher Umsatz pro Monat ueber mehrere Jahre
     */
    public function getSeasonality(int $instanceId, int $years = 3): array
    {
        $currentYear = (int)date('Y');
        $startYear = $currentYear - $years + 1;

        $sql = "SELECT MONTH(dl.created_at) as month_num,
                       YEAR(dl.created_at) as year_num,
                       COALESCE(SUM(dl.gross_amount), 0) as revenue,
                       COUNT(*) as invoice_count
                FROM document_lifecycle dl
                WHERE dl.instances_id = ?
                AND dl.doc_type = 'invoice'
                AND dl.status IN ('sent','paid','overdue','reminded')
                AND YEAR(dl.created_at) BETWEEN ? AND ?
                GROUP BY YEAR(dl.created_at), MONTH(dl.created_at)
                ORDER BY YEAR(dl.created_at), MONTH(dl.created_at)";
        $rows = $this->db->rawQuery($sql, [$instanceId, $startYear, $currentYear]) ?: [];

        $monthNames = ['Januar','Februar','Maerz','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];

        // Gruppiere nach Monat
        $monthly = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthly[$m] = [
                'month' => $m,
                'month_name' => $monthNames[$m - 1],
                'total_revenue' => 0,
                'year_count' => 0,
                'avg_revenue' => 0,
                'years' => [],
            ];
        }

        foreach ($rows as $row) {
            $m = (int)$row['month_num'];
            $y = (int)$row['year_num'];
            $rev = round((float)$row['revenue'], 2);
            $monthly[$m]['total_revenue'] += $rev;
            $monthly[$m]['year_count']++;
            $monthly[$m]['years'][$y] = $rev;
        }

        foreach ($monthly as &$m) {
            $m['avg_revenue'] = $m['year_count'] > 0 ? round($m['total_revenue'] / $m['year_count'], 2) : 0;
        }
        unset($m);

        return [
            'months' => array_values($monthly),
            'years_analyzed' => $years,
            'start_year' => $startYear,
            'end_year' => $currentYear,
        ];
    }

    /**
     * Spitzenmonate identifizieren
     */
    public function getPeakMonths(int $instanceId): array
    {
        $seasonality = $this->getSeasonality($instanceId, 3);
        $months = $seasonality['months'];

        usort($months, fn($a, $b) => $b['avg_revenue'] <=> $a['avg_revenue']);

        $maxRevenue = $months[0]['avg_revenue'] ?? 0;
        foreach ($months as &$m) {
            $m['is_peak'] = $maxRevenue > 0 && $m['avg_revenue'] >= ($maxRevenue * 0.7);
            $m['intensity_pct'] = $maxRevenue > 0 ? round(($m['avg_revenue'] / $maxRevenue) * 100, 1) : 0;
        }
        unset($m);

        return $months;
    }

    /**
     * Zeitraum-Vergleich: Zwei Perioden nebeneinander
     */
    public function comparePeriods(int $instanceId, string $period1Start, string $period1End, string $period2Start, string $period2End): array
    {
        $p1 = $this->calculatePeriodProfit($instanceId, $period1Start, $period1End);
        $p2 = $this->calculatePeriodProfit($instanceId, $period2Start, $period2End);

        // Kundenanzahl je Periode
        $sql = "SELECT COUNT(DISTINCT c.clients_id) as client_count
                FROM projects p
                JOIN clients c ON p.clients_id = c.clients_id
                WHERE p.instances_id = ? AND p.projects_deleted = 0
                AND p.projects_dates_use_start BETWEEN ? AND ?";
        $r1 = $this->db->rawQuery($sql, [$instanceId, $period1Start, $period1End]);
        $r2 = $this->db->rawQuery($sql, [$instanceId, $period2Start, $period2End]);
        $clients1 = $r1 ? (int)$r1[0]['client_count'] : 0;
        $clients2 = $r2 ? (int)$r2[0]['client_count'] : 0;

        $revenueChange = $p1['totals']['revenue'] > 0
            ? round((($p2['totals']['revenue'] - $p1['totals']['revenue']) / $p1['totals']['revenue']) * 100, 1)
            : 0;
        $profitChange = $p1['totals']['profit'] != 0
            ? round((($p2['totals']['profit'] - $p1['totals']['profit']) / abs($p1['totals']['profit'])) * 100, 1)
            : 0;

        return [
            'period1' => [
                'start' => $period1Start,
                'end' => $period1End,
                'revenue' => $p1['totals']['revenue'],
                'costs' => $p1['totals']['costs'],
                'profit' => $p1['totals']['profit'],
                'margin_pct' => $p1['totals']['margin_pct'],
                'project_count' => count($p1['projects']),
                'client_count' => $clients1,
            ],
            'period2' => [
                'start' => $period2Start,
                'end' => $period2End,
                'revenue' => $p2['totals']['revenue'],
                'costs' => $p2['totals']['costs'],
                'profit' => $p2['totals']['profit'],
                'margin_pct' => $p2['totals']['margin_pct'],
                'project_count' => count($p2['projects']),
                'client_count' => $clients2,
            ],
            'changes' => [
                'revenue_pct' => $revenueChange,
                'profit_pct' => $profitChange,
                'project_diff' => count($p2['projects']) - count($p1['projects']),
                'client_diff' => $clients2 - $clients1,
            ],
        ];
    }

    /**
     * Revenue forecast based on planned projects
     */
    public function getRevenueForecast(int $instanceId, int $monthsAhead = 3): array
    {
        $forecast = [];
        for ($i = 0; $i < $monthsAhead; $i++) {
            $monthStart = date('Y-m-01', strtotime("+{$i} months"));
            $monthEnd = date('Y-m-t', strtotime("+{$i} months"));
            $monthName = date('F Y', strtotime("+{$i} months"));

            $this->db->where('instances_id', $instanceId);
            $this->db->where('projects_deleted', 0);
            $this->db->where('projects_archived', 0);
            $this->db->where("(projects_dates_use_start >= '$monthStart' AND projects_dates_use_start <= '$monthEnd')");
            $projects = $this->db->get('projects', null, ['projects_id']);

            $monthRevenue = 0;
            $projectCount = count($projects ?: []);
            foreach (($projects ?: []) as $p) {
                $profit = $this->calculateProjectProfit($p['projects_id']);
                $monthRevenue += $profit['revenue']['total'];
            }

            $forecast[] = [
                'month' => $monthName,
                'month_start' => $monthStart,
                'project_count' => $projectCount,
                'estimated_revenue' => $monthRevenue,
            ];
        }

        return $forecast;
    }
}
