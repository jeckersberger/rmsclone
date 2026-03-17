<?php
/**
 * Equipment-Wartungsintervalle
 *
 * Automatische Erinnerung wenn Equipment X Tage nicht gewartet wurde.
 * - Wartungsintervall pro Asset-Typ definierbar
 * - Dashboard-Warnungen fuer faellige Wartungen
 * - Wartungshistorie pro Asset
 */
class MaintenanceScheduleService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get assets due for maintenance
     */
    public function getDueMaintenance(int $instanceId): array
    {
        $sql = "SELECT a.assets_id, a.assets_tag,
                       at.assetTypes_name, at.assetTypes_maintenanceInterval,
                       a.assets_lastMaintenanceDate,
                       DATEDIFF(CURDATE(), COALESCE(a.assets_lastMaintenanceDate, a.assets_purchaseDate, '2020-01-01')) as days_since_maintenance
                FROM assets a
                JOIN assetTypes at ON a.assetTypes_id = at.assetTypes_id
                WHERE a.instances_id = ?
                AND a.assets_deleted = 0
                AND at.assetTypes_deleted = 0
                AND at.assetTypes_maintenanceInterval IS NOT NULL
                AND at.assetTypes_maintenanceInterval > 0
                AND DATEDIFF(CURDATE(), COALESCE(a.assets_lastMaintenanceDate, a.assets_purchaseDate, '2020-01-01')) >= at.assetTypes_maintenanceInterval
                ORDER BY days_since_maintenance DESC";
        return $this->db->rawQuery($sql, [$instanceId]) ?: [];
    }

    /**
     * Get upcoming maintenance (within X days)
     */
    public function getUpcomingMaintenance(int $instanceId, int $withinDays = 30): array
    {
        $sql = "SELECT a.assets_id, a.assets_tag,
                       at.assetTypes_name, at.assetTypes_maintenanceInterval,
                       a.assets_lastMaintenanceDate,
                       DATEDIFF(CURDATE(), COALESCE(a.assets_lastMaintenanceDate, a.assets_purchaseDate, '2020-01-01')) as days_since,
                       (at.assetTypes_maintenanceInterval - DATEDIFF(CURDATE(), COALESCE(a.assets_lastMaintenanceDate, a.assets_purchaseDate, '2020-01-01'))) as days_until_due
                FROM assets a
                JOIN assetTypes at ON a.assetTypes_id = at.assetTypes_id
                WHERE a.instances_id = ?
                AND a.assets_deleted = 0
                AND at.assetTypes_deleted = 0
                AND at.assetTypes_maintenanceInterval IS NOT NULL
                AND at.assetTypes_maintenanceInterval > 0
                AND (at.assetTypes_maintenanceInterval - DATEDIFF(CURDATE(), COALESCE(a.assets_lastMaintenanceDate, a.assets_purchaseDate, '2020-01-01'))) BETWEEN 0 AND ?
                ORDER BY days_until_due ASC";
        return $this->db->rawQuery($sql, [$instanceId, $withinDays]) ?: [];
    }

    /**
     * Record maintenance performed
     */
    public function recordMaintenance(int $assetId, int $userId, ?string $notes = null): bool
    {
        $this->db->where('assets_id', $assetId);
        $result = $this->db->update('assets', [
            'assets_lastMaintenanceDate' => date('Y-m-d'),
        ]);

        // Log it
        $this->db->insert('maintenance_log', [
            'assets_id' => $assetId,
            'performed_by' => $userId,
            'performed_at' => date('Y-m-d H:i:s'),
            'notes' => $notes,
        ]);

        return $result;
    }

    /**
     * Get maintenance history for an asset
     */
    public function getHistory(int $assetId): array
    {
        $this->db->where('assets_id', $assetId);
        $this->db->join('users', 'maintenance_log.performed_by = users.users_userid', 'LEFT');
        $this->db->orderBy('maintenance_log.performed_at', 'DESC');
        return $this->db->get('maintenance_log', null, [
            'maintenance_log.*', 'users.users_name1', 'users.users_name2'
        ]) ?: [];
    }

    /**
     * Get maintenance stats for dashboard
     */
    public function getStats(int $instanceId): array
    {
        $due = $this->getDueMaintenance($instanceId);
        $upcoming = $this->getUpcomingMaintenance($instanceId, 14);

        return [
            'overdue_count' => count($due),
            'upcoming_count' => count($upcoming),
            'overdue' => array_slice($due, 0, 5),
            'upcoming' => array_slice($upcoming, 0, 5),
        ];
    }
}
