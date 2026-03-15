<?php

/**
 * StockWarningService
 *
 * Provides comprehensive availability checking and warning generation for asset rental
 * management. Identifies conflicts, low stock, maintenance issues, and overdue returns.
 */
class StockWarningService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Check if enough assets of a type are available for a date range
     *
     * @param int $instanceId Instance ID
     * @param int $assetTypeId Asset type ID
     * @param string $from Start date (Y-m-d)
     * @param string $to End date (Y-m-d)
     * @param int $quantity Required quantity
     * @return array ['available' => bool, 'total' => int, 'in_use' => int, 'in_maintenance' => int, 'free' => int, 'conflicts' => [...]]
     */
    public function checkAvailability(int $instanceId, int $assetTypeId, string $from, string $to, int $quantity = 1): array
    {
        // Get total count of asset type in this instance
        $this->db->where('instances_id', $instanceId);
        $this->db->where('assetTypes_id', $assetTypeId);
        $this->db->where('assets_deleted', 0);
        $totalCount = (int)$this->db->getValue('assets', 'COUNT(*)');

        if ($totalCount === 0) {
            return [
                'available' => false,
                'total' => 0,
                'in_use' => 0,
                'in_maintenance' => 0,
                'free' => 0,
                'conflicts' => [],
                'message' => 'No assets of this type in inventory'
            ];
        }

        // Get assets currently assigned to overlapping projects during this period
        $sql = "SELECT DISTINCT aa.assets_id
                FROM assetsAssignments aa
                JOIN assets a ON aa.assets_id = a.assets_id
                JOIN projects p ON aa.projects_id = p.projects_id
                WHERE a.instances_id = ?
                AND a.assetTypes_id = ?
                AND a.assets_deleted = 0
                AND (
                    (p.projects_dates_use_start <= ? AND p.projects_dates_use_end >= ?)
                    OR (p.projects_dates_use_start <= ? AND p.projects_dates_use_end >= ?)
                    OR (p.projects_dates_use_start >= ? AND p.projects_dates_use_end <= ?)
                )";

        $inUseAssets = $this->db->rawQuery($sql, [
            $instanceId,
            $assetTypeId,
            $to,
            $from,
            $from,
            $to,
            $from,
            $to
        ]) ?: [];

        $inUseCount = count($inUseAssets);
        $inUseIds = array_column($inUseAssets, 'assets_id');

        // Get assets in maintenance during this period
        $maintenanceSql = "SELECT DISTINCT mj.assets_id
                          FROM maintenanceJobs mj
                          JOIN assets a ON mj.assets_id = a.assets_id
                          WHERE a.instances_id = ?
                          AND a.assetTypes_id = ?
                          AND a.assets_deleted = 0
                          AND mj.maintenanceJobs_deleted = 0
                          AND mj.maintenanceJobs_status IN ('scheduled', 'in_progress')
                          AND (
                              (mj.maintenanceJobs_startDate <= ? AND mj.maintenanceJobs_endDate >= ?)
                              OR (mj.maintenanceJobs_startDate <= ? AND mj.maintenanceJobs_endDate >= ?)
                              OR (mj.maintenanceJobs_startDate >= ? AND mj.maintenanceJobs_endDate <= ?)
                          )";

        $maintenanceAssets = $this->db->rawQuery($maintenanceSql, [
            $instanceId,
            $assetTypeId,
            $to,
            $from,
            $from,
            $to,
            $from,
            $to
        ]) ?: [];

        $maintenanceCount = count($maintenanceAssets);
        $maintenanceIds = array_column($maintenanceAssets, 'assets_id');

        // Calculate free assets (avoiding double-count)
        $unavailableIds = array_unique(array_merge($inUseIds, $maintenanceIds));
        $freeCount = $totalCount - count($unavailableIds);

        // Get specific conflict details for in-use assets
        $conflicts = [];
        if (!empty($inUseIds)) {
            $placeholders = implode(',', array_fill(0, count($inUseIds), '?'));
            $conflictSql = "SELECT DISTINCT a.assets_id, a.assets_tag, p.projects_id, p.projects_name,
                                   p.projects_dates_use_start, p.projects_dates_use_end
                           FROM assetsAssignments aa
                           JOIN assets a ON aa.assets_id = a.assets_id
                           JOIN projects p ON aa.projects_id = p.projects_id
                           WHERE a.assets_id IN ($placeholders)
                           ORDER BY a.assets_id, p.projects_dates_use_start";
            $conflicts = $this->db->rawQuery($conflictSql, $inUseIds) ?: [];
        }

        return [
            'available' => $freeCount >= $quantity,
            'total' => $totalCount,
            'in_use' => $inUseCount,
            'in_maintenance' => $maintenanceCount,
            'free' => $freeCount,
            'conflicts' => $conflicts,
            'needed' => $quantity,
            'shortfall' => max(0, $quantity - $freeCount)
        ];
    }

    /**
     * Check all assets assigned to a project for double-booking conflicts
     *
     * @param int $instanceId Instance ID
     * @param int $projectId Project ID
     * @return array Conflict details
     */
    public function checkProjectConflicts(int $instanceId, int $projectId): array
    {
        // Get all assets assigned to this project
        $sql = "SELECT DISTINCT aa.assets_id, a.assets_tag, at.assetTypes_name
                FROM assetsAssignments aa
                JOIN assets a ON aa.assets_id = a.assets_id
                JOIN assetTypes at ON a.assetTypes_id = at.assetTypes_id
                WHERE a.instances_id = ?
                AND aa.projects_id = ?
                AND a.assets_deleted = 0";

        $projectAssets = $this->db->rawQuery($sql, [$instanceId, $projectId]) ?: [];

        if (empty($projectAssets)) {
            return ['conflicts' => [], 'total_assigned' => 0];
        }

        // Get project dates
        $this->db->where('projects_id', $projectId);
        $project = $this->db->getOne('projects', ['projects_dates_use_start', 'projects_dates_use_end']);

        $conflictAssets = [];

        foreach ($projectAssets as $asset) {
            $assetId = (int)$asset['assets_id'];

            // Find overlapping assignments with other projects
            $overlapSql = "SELECT DISTINCT p.projects_id, p.projects_name, p.projects_dates_use_start, p.projects_dates_use_end
                          FROM assetsAssignments aa
                          JOIN projects p ON aa.projects_id = p.projects_id
                          WHERE aa.assets_id = ?
                          AND p.projects_id != ?
                          AND (
                              (p.projects_dates_use_start <= ? AND p.projects_dates_use_end >= ?)
                              OR (p.projects_dates_use_start <= ? AND p.projects_dates_use_end >= ?)
                              OR (p.projects_dates_use_start >= ? AND p.projects_dates_use_end <= ?)
                          )";

            $overlaps = $this->db->rawQuery($overlapSql, [
                $assetId,
                $projectId,
                $project['projects_dates_use_end'],
                $project['projects_dates_use_start'],
                $project['projects_dates_use_start'],
                $project['projects_dates_use_end'],
                $project['projects_dates_use_start'],
                $project['projects_dates_use_end']
            ]) ?: [];

            if (!empty($overlaps)) {
                $conflictAssets[] = [
                    'asset_id' => $assetId,
                    'asset_tag' => $asset['assets_tag'],
                    'asset_type' => $asset['assetTypes_name'],
                    'overlapping_projects' => $overlaps
                ];
            }
        }

        return [
            'conflicts' => $conflictAssets,
            'total_assigned' => count($projectAssets),
            'conflict_count' => count($conflictAssets)
        ];
    }

    /**
     * Find specific assets that are available in a time window
     *
     * @param int $instanceId Instance ID
     * @param int $assetTypeId Asset type ID
     * @param string $from Start date (Y-m-d)
     * @param string $to End date (Y-m-d)
     * @return array List of available assets
     */
    public function findAvailableAssets(int $instanceId, int $assetTypeId, string $from, string $to): array
    {
        // Get all assets of this type
        $this->db->where('instances_id', $instanceId);
        $this->db->where('assetTypes_id', $assetTypeId);
        $this->db->where('assets_deleted', 0);
        $allAssets = $this->db->get('assets', null, ['assets_id', 'assets_tag']) ?: [];

        if (empty($allAssets)) {
            return [];
        }

        $assetIds = array_column($allAssets, 'assets_id');
        $placeholders = implode(',', array_fill(0, count($assetIds), '?'));

        // Get assets with overlapping assignments
        $unavailableSql = "SELECT DISTINCT aa.assets_id
                          FROM assetsAssignments aa
                          JOIN projects p ON aa.projects_id = p.projects_id
                          WHERE aa.assets_id IN ($placeholders)
                          AND (
                              (p.projects_dates_use_start <= ? AND p.projects_dates_use_end >= ?)
                              OR (p.projects_dates_use_start <= ? AND p.projects_dates_use_end >= ?)
                              OR (p.projects_dates_use_start >= ? AND p.projects_dates_use_end <= ?)
                          )";

        $unavailable = $this->db->rawQuery($unavailableSql, array_merge(
            $assetIds,
            [$to, $from, $from, $to, $from, $to]
        )) ?: [];

        $unavailableIds = array_column($unavailable, 'assets_id');

        // Filter and return available assets
        return array_filter($allAssets, function ($asset) use ($unavailableIds) {
            return !in_array($asset['assets_id'], $unavailableIds);
        });
    }

    /**
     * Generate comprehensive warnings for an instance
     *
     * @param int $instanceId Instance ID
     * @return array All active warnings categorized by type
     */
    public function generateWarnings(int $instanceId): array
    {
        return [
            'double_bookings' => $this->getDoubleBookings($instanceId),
            'overdue_returns' => $this->getOverdueReturns($instanceId),
            'low_stock' => $this->getLowStockTypes($instanceId),
            'maintenance_due' => $this->getMaintenanceDue($instanceId),
            'inventory_check' => $this->getAssetsWithoutRecentCheck($instanceId),
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Get all assets currently assigned to overlapping projects
     *
     * @param int $instanceId Instance ID
     * @return array List of double-booked assets
     */
    public function getDoubleBookings(int $instanceId): array
    {
        $sql = "SELECT DISTINCT a.assets_id, a.assets_tag, at.assetTypes_name,
                       p.projects_id, p.projects_name, p.projects_dates_use_start, p.projects_dates_use_end
                FROM assets a
                JOIN assetTypes at ON a.assetTypes_id = at.assetTypes_id
                JOIN assetsAssignments aa ON a.assets_id = aa.assets_id
                JOIN projects p ON aa.projects_id = p.projects_id
                WHERE a.instances_id = ?
                AND a.assets_deleted = 0
                AND a.assets_id IN (
                    SELECT aa2.assets_id
                    FROM assetsAssignments aa2
                    JOIN projects p2 ON aa2.projects_id = p2.projects_id
                    WHERE aa2.assets_id = a.assets_id
                    GROUP BY aa2.assets_id
                    HAVING COUNT(DISTINCT aa2.projects_id) > 1
                )
                ORDER BY a.assets_id, p.projects_dates_use_start";

        return $this->db->rawQuery($sql, [$instanceId]) ?: [];
    }

    /**
     * Get assets that should have been returned (overdue)
     *
     * @param int $instanceId Instance ID
     * @return array Overdue returns
     */
    public function getOverdueReturns(int $instanceId): array
    {
        $sql = "SELECT DISTINCT a.assets_id, a.assets_tag, at.assetTypes_name,
                       p.projects_id, p.projects_name, p.projects_dates_use_end,
                       DATEDIFF(CURDATE(), p.projects_dates_use_end) as days_overdue
                FROM assets a
                JOIN assetTypes at ON a.assetTypes_id = at.assetTypes_id
                JOIN assetsAssignments aa ON a.assets_id = aa.assets_id
                JOIN projects p ON aa.projects_id = p.projects_id
                WHERE a.instances_id = ?
                AND a.assets_deleted = 0
                AND p.projects_dates_use_end < CURDATE()
                ORDER BY p.projects_dates_use_end ASC";

        return $this->db->rawQuery($sql, [$instanceId]) ?: [];
    }

    /**
     * Get asset types with high utilization (low stock)
     *
     * @param int $instanceId Instance ID
     * @param float $threshold Utilization threshold (default 0.8 = 80%)
     * @return array Asset types with low availability
     */
    public function getLowStockTypes(int $instanceId, float $threshold = 0.8): array
    {
        $sql = "SELECT at.assetTypes_id, at.assetTypes_name,
                       COUNT(DISTINCT a.assets_id) as total_assets,
                       COUNT(DISTINCT CASE WHEN aa.assets_id IS NOT NULL THEN a.assets_id END) as assigned_assets,
                       ROUND(COUNT(DISTINCT CASE WHEN aa.assets_id IS NOT NULL THEN a.assets_id END) /
                             COUNT(DISTINCT a.assets_id) * 100, 2) as utilization_percent
                FROM assetTypes at
                LEFT JOIN assets a ON at.assetTypes_id = a.assetTypes_id AND a.instances_id = ? AND a.assets_deleted = 0
                LEFT JOIN assetsAssignments aa ON a.assets_id = aa.assets_id
                WHERE at.assetTypes_deleted = 0
                GROUP BY at.assetTypes_id, at.assetTypes_name
                HAVING utilization_percent >= ?
                ORDER BY utilization_percent DESC";

        return $this->db->rawQuery($sql, [$instanceId, ($threshold * 100)]) ?: [];
    }

    /**
     * Get assets with due or upcoming maintenance
     *
     * @param int $instanceId Instance ID
     * @return array Assets needing maintenance
     */
    public function getMaintenanceDue(int $instanceId): array
    {
        $sql = "SELECT a.assets_id, a.assets_tag, at.assetTypes_name, at.assetTypes_maintenanceInterval,
                       a.assets_lastMaintenanceDate,
                       DATEDIFF(CURDATE(), COALESCE(a.assets_lastMaintenanceDate, a.assets_purchaseDate, '2020-01-01')) as days_since_maintenance,
                       (at.assetTypes_maintenanceInterval - DATEDIFF(CURDATE(), COALESCE(a.assets_lastMaintenanceDate, a.assets_purchaseDate, '2020-01-01'))) as days_until_due,
                       CASE
                           WHEN DATEDIFF(CURDATE(), COALESCE(a.assets_lastMaintenanceDate, a.assets_purchaseDate, '2020-01-01')) >= at.assetTypes_maintenanceInterval THEN 'overdue'
                           ELSE 'upcoming'
                       END as status
                FROM assets a
                JOIN assetTypes at ON a.assetTypes_id = at.assetTypes_id
                WHERE a.instances_id = ?
                AND a.assets_deleted = 0
                AND at.assetTypes_deleted = 0
                AND at.assetTypes_maintenanceInterval IS NOT NULL
                AND at.assetTypes_maintenanceInterval > 0
                AND (DATEDIFF(CURDATE(), COALESCE(a.assets_lastMaintenanceDate, a.assets_purchaseDate, '2020-01-01')) >= at.assetTypes_maintenanceInterval
                     OR (at.assetTypes_maintenanceInterval - DATEDIFF(CURDATE(), COALESCE(a.assets_lastMaintenanceDate, a.assets_purchaseDate, '2020-01-01'))) <= 7)
                ORDER BY days_until_due ASC";

        return $this->db->rawQuery($sql, [$instanceId]) ?: [];
    }

    /**
     * Get assets without recent inventory check (if RFID tracking active)
     *
     * @param int $instanceId Instance ID
     * @param int $days Days threshold (default 30)
     * @return array Assets without recent checks
     */
    public function getAssetsWithoutRecentCheck(int $instanceId, int $days = 30): array
    {
        // Check if RFID tracking is active for this instance
        $this->db->where('instances_id', $instanceId);
        $instance = $this->db->getOne('instances', ['instances_rfid_active']);

        if (!$instance || !$instance['instances_rfid_active']) {
            return [];
        }

        $sql = "SELECT a.assets_id, a.assets_tag, at.assetTypes_name,
                       MAX(abs.assetsBarcodesScans_scanDate) as last_scan,
                       DATEDIFF(CURDATE(), MAX(abs.assetsBarcodesScans_scanDate)) as days_since_scan
                FROM assets a
                JOIN assetTypes at ON a.assetTypes_id = at.assetTypes_id
                LEFT JOIN assetsBarcodes asc ON a.assets_id = asc.assets_id
                LEFT JOIN assetsBarcodesScans abs ON asc.assetsBarcodes_id = abs.assetsBarcodes_id
                WHERE a.instances_id = ?
                AND a.assets_deleted = 0
                GROUP BY a.assets_id
                HAVING days_since_scan IS NULL OR days_since_scan >= ?
                ORDER BY days_since_scan DESC";

        return $this->db->rawQuery($sql, [$instanceId, $days]) ?: [];
    }

    /**
     * Get warning counts for dashboard badge
     *
     * @param int $instanceId Instance ID
     * @return array Count summary
     */
    public function getWarningCounts(int $instanceId): array
    {
        $doubleBookings = count($this->getDoubleBookings($instanceId));
        $overdueReturns = count($this->getOverdueReturns($instanceId));
        $lowStock = count($this->getLowStockTypes($instanceId));
        $maintenanceDue = count($this->getMaintenanceDue($instanceId));

        return [
            'double_bookings' => $doubleBookings,
            'overdue_returns' => $overdueReturns,
            'low_stock' => $lowStock,
            'maintenance_due' => $maintenanceDue,
            'total' => $doubleBookings + $overdueReturns + $lowStock + $maintenanceDue
        ];
    }
}
