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
