<?php
/**
 * ConflictDetectionService - Doppelbuchungs-Erkennung
 *
 * Prueft ob Equipment fuer einen Zeitraum bereits gebucht ist
 * und warnt vor Konflikten bei Projekterstellung/-aenderung.
 */
class ConflictDetectionService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Prueft ob ein Asset im Zeitraum verfuegbar ist
     */
    public function checkAssetConflicts(int $assetId, string $startDate, string $endDate, ?int $excludeProjectId = null): array
    {
        $sql = "SELECT aa.assetsAssignments_id, p.projects_id, p.projects_name,
                       p.projects_dates_use_start, p.projects_dates_use_end
                FROM assetsAssignments aa
                JOIN projects p ON aa.projects_id = p.projects_id
                WHERE aa.assets_id = ?
                AND aa.assetsAssignments_deleted = 0
                AND p.projects_deleted = 0
                AND p.projects_dates_use_start <= ?
                AND p.projects_dates_use_end >= ?";
        $params = [$assetId, $endDate, $startDate];

        if ($excludeProjectId) {
            $sql .= " AND p.projects_id != ?";
            $params[] = $excludeProjectId;
        }

        $sql .= " ORDER BY p.projects_dates_use_start ASC";
        return $this->db->rawQuery($sql, $params) ?: [];
    }

    /**
     * Prueft mehrere Assets gleichzeitig auf Konflikte
     */
    public function checkBulkConflicts(array $assetIds, string $startDate, string $endDate, ?int $excludeProjectId = null): array
    {
        if (empty($assetIds)) return [];

        $conflicts = [];
        foreach ($assetIds as $assetId) {
            $assetConflicts = $this->checkAssetConflicts(intval($assetId), $startDate, $endDate, $excludeProjectId);
            if (!empty($assetConflicts)) {
                $conflicts[$assetId] = $assetConflicts;
            }
        }
        return $conflicts;
    }

    /**
     * Prueft einen Asset-Typ (alle Assets dieses Typs)
     */
    public function checkAssetTypeAvailability(int $assetTypeId, int $instanceId, string $startDate, string $endDate, ?int $excludeProjectId = null): array
    {
        // Gesamtanzahl Assets dieses Typs
        $this->db->where('assetTypes_id', $assetTypeId);
        $this->db->where('assets_deleted', 0);
        $totalCount = (int) $this->db->getValue('assets', 'count(*)');

        // Gebuchte Assets im Zeitraum
        $sql = "SELECT COUNT(DISTINCT aa.assets_id) as booked
                FROM assetsAssignments aa
                JOIN assets a ON aa.assets_id = a.assets_id
                JOIN projects p ON aa.projects_id = p.projects_id
                WHERE a.assetTypes_id = ?
                AND aa.assetsAssignments_deleted = 0
                AND a.assets_deleted = 0
                AND p.projects_deleted = 0
                AND p.instances_id = ?
                AND p.projects_dates_use_start <= ?
                AND p.projects_dates_use_end >= ?";
        $params = [$assetTypeId, $instanceId, $endDate, $startDate];

        if ($excludeProjectId) {
            $sql .= " AND p.projects_id != ?";
            $params[] = $excludeProjectId;
        }

        $result = $this->db->rawQuery($sql, $params);
        $bookedCount = $result ? (int) $result[0]['booked'] : 0;

        return [
            'total' => $totalCount,
            'booked' => $bookedCount,
            'available' => $totalCount - $bookedCount,
            'has_conflict' => $bookedCount >= $totalCount,
        ];
    }
}
