<?php
/**
 * Equipment Auslastungsberichte (Utilization Reports)
 *
 * Berechnet Vermietungstage vs. verfuegbare Tage,
 * Top-Performer, unterausgelastete Assets und
 * cacht Ergebnisse in equipment_utilization_cache.
 */
class UtilizationReportService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Berechnet Auslastung fuer einen bestimmten Asset-Typ in einem Monat
     *
     * @param int $instanceId  Instanz-ID
     * @param int $assetTypeId Asset-Typ-ID
     * @param int $year        Jahr
     * @param int $month       Monat (1-12)
     * @return array Auslastungsdaten
     */
    public function calculateUtilization(int $instanceId, int $assetTypeId, int $year, int $month): array
    {
        $monthStart = sprintf('%04d-%02d-01', $year, $month);
        $monthEnd = date('Y-m-t', strtotime($monthStart));
        $daysInMonth = (int)date('t', strtotime($monthStart));

        // Anzahl Assets dieses Typs
        $sql = "SELECT COUNT(*) as cnt FROM assets a
                WHERE a.assetTypes_id = ? AND a.assets_deleted = 0 AND a.instances_id = ?";
        $result = $this->db->rawQuery($sql, [$assetTypeId, $instanceId]);
        $assetCount = $result ? (int)$result[0]['cnt'] : 0;

        if ($assetCount === 0) {
            return [
                'assetTypes_id' => $assetTypeId,
                'year' => $year,
                'month' => $month,
                'days_rented' => 0,
                'days_available' => 0,
                'revenue' => 0,
                'utilization_pct' => 0,
            ];
        }

        $daysAvailable = $assetCount * $daysInMonth;

        // Vermietungstage berechnen aus Projektzuweisungen
        $sql = "SELECT SUM(
                    DATEDIFF(
                        LEAST(COALESCE(p.projects_dates_use_end, ?), ?),
                        GREATEST(COALESCE(p.projects_dates_use_start, ?), ?)
                    ) + 1
                ) as rental_days
                FROM assetsAssignments aa
                JOIN assets a ON aa.assets_id = a.assets_id
                JOIN projects p ON aa.projects_id = p.projects_id
                WHERE a.assetTypes_id = ? AND a.instances_id = ?
                AND aa.assetsAssignments_deleted = 0 AND a.assets_deleted = 0 AND p.projects_deleted = 0
                AND COALESCE(p.projects_dates_use_start, p.projects_dates_deliver_start) <= ?
                AND COALESCE(p.projects_dates_use_end, p.projects_dates_deliver_end) >= ?";
        $params = [$monthEnd, $monthEnd, $monthStart, $monthStart, $assetTypeId, $instanceId, $monthEnd, $monthStart];
        $result = $this->db->rawQuery($sql, $params);
        $daysRented = $result ? max(0, (int)$result[0]['rental_days']) : 0;
        // Begrenze auf maximal verfuegbare Tage
        $daysRented = min($daysRented, $daysAvailable);

        // Umsatz berechnen
        $sql = "SELECT COALESCE(SUM(
                    CASE
                        WHEN aa.assetsAssignments_customPrice IS NOT NULL THEN aa.assetsAssignments_customPrice
                        ELSE at.assetTypes_dayRate
                    END
                    * (1 - COALESCE(aa.assetsAssignments_discount, 0) / 100)
                ), 0) as revenue
                FROM assetsAssignments aa
                JOIN assets a ON aa.assets_id = a.assets_id
                JOIN assetTypes at ON a.assetTypes_id = at.assetTypes_id
                JOIN projects p ON aa.projects_id = p.projects_id
                WHERE a.assetTypes_id = ? AND a.instances_id = ?
                AND aa.assetsAssignments_deleted = 0 AND a.assets_deleted = 0 AND p.projects_deleted = 0
                AND COALESCE(p.projects_dates_use_start, p.projects_dates_deliver_start) <= ?
                AND COALESCE(p.projects_dates_use_end, p.projects_dates_deliver_end) >= ?";
        $params = [$assetTypeId, $instanceId, $monthEnd, $monthStart];
        $result = $this->db->rawQuery($sql, $params);
        $revenue = $result ? round((float)$result[0]['revenue'], 2) : 0;

        $utilizationPct = $daysAvailable > 0 ? round(($daysRented / $daysAvailable) * 100, 2) : 0;

        // Cache aktualisieren
        $this->updateCache($assetTypeId, $year, $month, $daysRented, $daysAvailable, $revenue, $utilizationPct);

        return [
            'assetTypes_id' => $assetTypeId,
            'year' => $year,
            'month' => $month,
            'days_rented' => $daysRented,
            'days_available' => $daysAvailable,
            'revenue' => $revenue,
            'utilization_pct' => $utilizationPct,
        ];
    }

    /**
     * Uebersicht aller Asset-Typen nach Auslastung sortiert
     */
    public function getUtilizationOverview(int $instanceId, int $year, int $month): array
    {
        // Alle Asset-Typen der Instanz
        $sql = "SELECT DISTINCT at.assetTypes_id, at.assetTypes_name, ac.assetCategories_name
                FROM assetTypes at
                JOIN assets a ON a.assetTypes_id = at.assetTypes_id AND a.assets_deleted = 0
                JOIN assetCategories ac ON at.assetCategories_id = ac.assetCategories_id
                WHERE a.instances_id = ?
                ORDER BY ac.assetCategories_name, at.assetTypes_name";
        $assetTypes = $this->db->rawQuery($sql, [$instanceId]) ?: [];

        $results = [];
        foreach ($assetTypes as $at) {
            $util = $this->calculateUtilization($instanceId, (int)$at['assetTypes_id'], $year, $month);
            $util['assetTypes_name'] = $at['assetTypes_name'];
            $util['assetCategories_name'] = $at['assetCategories_name'];
            $results[] = $util;
        }

        // Sortierung nach Auslastung absteigend
        usort($results, fn($a, $b) => $b['utilization_pct'] <=> $a['utilization_pct']);

        return $results;
    }

    /**
     * Top 10 meistgenutzte Assets im Jahr
     */
    public function getTopPerformers(int $instanceId, int $year): array
    {
        $sql = "SELECT DISTINCT at.assetTypes_id, at.assetTypes_name, ac.assetCategories_name
                FROM assetTypes at
                JOIN assets a ON a.assetTypes_id = at.assetTypes_id AND a.assets_deleted = 0
                JOIN assetCategories ac ON at.assetCategories_id = ac.assetCategories_id
                WHERE a.instances_id = ?
                ORDER BY at.assetTypes_name";
        $assetTypes = $this->db->rawQuery($sql, [$instanceId]) ?: [];

        $results = [];
        foreach ($assetTypes as $at) {
            $totalDaysRented = 0;
            $totalDaysAvailable = 0;
            $totalRevenue = 0;

            for ($m = 1; $m <= 12; $m++) {
                $util = $this->calculateUtilization($instanceId, (int)$at['assetTypes_id'], $year, $m);
                $totalDaysRented += $util['days_rented'];
                $totalDaysAvailable += $util['days_available'];
                $totalRevenue += $util['revenue'];
            }

            $yearPct = $totalDaysAvailable > 0 ? round(($totalDaysRented / $totalDaysAvailable) * 100, 2) : 0;

            $results[] = [
                'assetTypes_id' => $at['assetTypes_id'],
                'assetTypes_name' => $at['assetTypes_name'],
                'assetCategories_name' => $at['assetCategories_name'],
                'days_rented' => $totalDaysRented,
                'days_available' => $totalDaysAvailable,
                'revenue' => $totalRevenue,
                'utilization_pct' => $yearPct,
            ];
        }

        usort($results, fn($a, $b) => $b['utilization_pct'] <=> $a['utilization_pct']);

        return array_slice($results, 0, 10);
    }

    /**
     * Unterausgelastete Assets (unter Schwellenwert)
     */
    public function getUnderutilized(int $instanceId, int $year, int $threshold = 30): array
    {
        $sql = "SELECT DISTINCT at.assetTypes_id, at.assetTypes_name, ac.assetCategories_name,
                       at.assetTypes_dayRate
                FROM assetTypes at
                JOIN assets a ON a.assetTypes_id = at.assetTypes_id AND a.assets_deleted = 0
                JOIN assetCategories ac ON at.assetCategories_id = ac.assetCategories_id
                WHERE a.instances_id = ?
                ORDER BY at.assetTypes_name";
        $assetTypes = $this->db->rawQuery($sql, [$instanceId]) ?: [];

        $results = [];
        foreach ($assetTypes as $at) {
            $totalDaysRented = 0;
            $totalDaysAvailable = 0;
            $totalRevenue = 0;

            for ($m = 1; $m <= 12; $m++) {
                $util = $this->calculateUtilization($instanceId, (int)$at['assetTypes_id'], $year, $m);
                $totalDaysRented += $util['days_rented'];
                $totalDaysAvailable += $util['days_available'];
                $totalRevenue += $util['revenue'];
            }

            $yearPct = $totalDaysAvailable > 0 ? round(($totalDaysRented / $totalDaysAvailable) * 100, 2) : 0;

            if ($yearPct < $threshold) {
                $results[] = [
                    'assetTypes_id' => $at['assetTypes_id'],
                    'assetTypes_name' => $at['assetTypes_name'],
                    'assetCategories_name' => $at['assetCategories_name'],
                    'days_rented' => $totalDaysRented,
                    'days_available' => $totalDaysAvailable,
                    'revenue' => $totalRevenue,
                    'utilization_pct' => $yearPct,
                    'day_rate' => (float)$at['assetTypes_dayRate'],
                ];
            }
        }

        usort($results, fn($a, $b) => $a['utilization_pct'] <=> $b['utilization_pct']);

        return $results;
    }

    /**
     * Equipment ROI berechnen: Kaufpreis vs. Gesamtmietumsatz, Amortisationszeit
     */
    public function calculateRoi(int $assetTypeId): array
    {
        // Asset-Typ Daten
        $sql = "SELECT at.assetTypes_id, at.assetTypes_name, at.assetTypes_value,
                       at.assetTypes_dayRate, ac.assetCategories_name,
                       COUNT(DISTINCT a.assets_id) as asset_count
                FROM assetTypes at
                JOIN assets a ON a.assetTypes_id = at.assetTypes_id AND a.assets_deleted = 0
                JOIN assetCategories ac ON at.assetCategories_id = ac.assetCategories_id
                WHERE at.assetTypes_id = ?
                GROUP BY at.assetTypes_id";
        $result = $this->db->rawQuery($sql, [$assetTypeId]);
        if (!$result) {
            return ['error' => 'Asset-Typ nicht gefunden'];
        }
        $assetType = $result[0];

        $purchasePrice = (float)$assetType['assetTypes_value'] * (int)$assetType['asset_count'];
        $dayRate = (float)$assetType['assetTypes_dayRate'];

        // Gesamter Mietumsatz aller Zeiten
        $sql = "SELECT COALESCE(SUM(
                    CASE
                        WHEN aa.assetsAssignments_customPrice IS NOT NULL THEN aa.assetsAssignments_customPrice
                        ELSE at.assetTypes_dayRate
                    END
                    * (1 - COALESCE(aa.assetsAssignments_discount, 0) / 100)
                ), 0) as total_revenue,
                COUNT(DISTINCT aa.assetsAssignments_id) as total_assignments,
                MIN(p.projects_dates_use_start) as first_rental,
                MAX(p.projects_dates_use_end) as last_rental
                FROM assetsAssignments aa
                JOIN assets a ON aa.assets_id = a.assets_id
                JOIN assetTypes at ON a.assetTypes_id = at.assetTypes_id
                JOIN projects p ON aa.projects_id = p.projects_id
                WHERE a.assetTypes_id = ?
                AND aa.assetsAssignments_deleted = 0 AND a.assets_deleted = 0 AND p.projects_deleted = 0";
        $result = $this->db->rawQuery($sql, [$assetTypeId]);
        $totalRevenue = $result ? round((float)$result[0]['total_revenue'], 2) : 0;
        $totalAssignments = $result ? (int)$result[0]['total_assignments'] : 0;
        $firstRental = $result ? $result[0]['first_rental'] : null;
        $lastRental = $result ? $result[0]['last_rental'] : null;

        // ROI berechnen
        $roi = $purchasePrice > 0 ? round((($totalRevenue - $purchasePrice) / $purchasePrice) * 100, 2) : 0;

        // Amortisationsdauer schaetzen
        $paybackMonths = null;
        if ($firstRental && $totalRevenue > 0) {
            $monthsActive = max(1, (int)((strtotime($lastRental ?: date('Y-m-d')) - strtotime($firstRental)) / (30 * 86400)));
            $monthlyRevenue = $totalRevenue / $monthsActive;
            if ($monthlyRevenue > 0) {
                $paybackMonths = $purchasePrice > 0 ? round($purchasePrice / $monthlyRevenue, 1) : 0;
            }
        }

        return [
            'assetTypes_id' => $assetType['assetTypes_id'],
            'assetTypes_name' => $assetType['assetTypes_name'],
            'assetCategories_name' => $assetType['assetCategories_name'],
            'asset_count' => (int)$assetType['asset_count'],
            'purchase_price' => $purchasePrice,
            'unit_price' => (float)$assetType['assetTypes_value'],
            'day_rate' => $dayRate,
            'total_revenue' => $totalRevenue,
            'total_assignments' => $totalAssignments,
            'first_rental' => $firstRental,
            'last_rental' => $lastRental,
            'roi_pct' => $roi,
            'payback_months' => $paybackMonths,
            'is_profitable' => $totalRevenue >= $purchasePrice,
        ];
    }

    /**
     * Cache aktualisieren
     */
    private function updateCache(int $assetTypeId, int $year, int $month, int $daysRented, int $daysAvailable, float $revenue, float $utilizationPct): void
    {
        $sql = "INSERT INTO equipment_utilization_cache
                (assetTypes_id, year, month, days_rented, days_available, revenue, utilization_pct, calculated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                days_rented = VALUES(days_rented),
                days_available = VALUES(days_available),
                revenue = VALUES(revenue),
                utilization_pct = VALUES(utilization_pct),
                calculated_at = NOW()";
        $this->db->rawQuery($sql, [$assetTypeId, $year, $month, $daysRented, $daysAvailable, $revenue, $utilizationPct]);
    }
}
