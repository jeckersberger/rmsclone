<?php
/**
 * Verfuegbarkeitskalender + Kollisionserkennung
 *
 * Prueft ob Assets fuer einen Zeitraum verfuegbar sind und verhindert Doppelbuchungen.
 * Beruecksichtigt:
 * - Bestehende Projektzuweisungen (assetsAssignments)
 * - Manuelle Sperren (asset_availability_blocks)
 * - Projektstatus (freigegebene Projekte blockieren nicht)
 */
class AvailabilityService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Check if a specific asset is available for a date range
     */
    public function isAssetAvailable(int $assetId, string $startDate, string $endDate, ?int $excludeProjectId = null): bool
    {
        $conflicts = $this->getAssetConflicts($assetId, $startDate, $endDate, $excludeProjectId);
        return count($conflicts) === 0;
    }

    /**
     * Get all conflicts for an asset in a date range
     */
    public function getAssetConflicts(int $assetId, string $startDate, string $endDate, ?int $excludeProjectId = null): array
    {
        $conflicts = [];

        // 1) Check project assignments
        $sql = "SELECT aa.assetsAssignments_id, p.projects_id, p.projects_name,
                       p.projects_dates_deliver_start, p.projects_dates_deliver_end,
                       p.projects_dates_use_start, p.projects_dates_use_end,
                       ps.projectsStatuses_name, ps.projectsStatuses_assetsReleased
                FROM assetsAssignments aa
                JOIN projects p ON aa.projects_id = p.projects_id
                LEFT JOIN projectsStatuses ps ON p.projectsStatuses_id = ps.projectsStatuses_id
                WHERE aa.assets_id = ?
                AND aa.assetsAssignments_deleted = 0
                AND p.projects_deleted = 0
                AND p.projects_archived = 0";

        $params = [$assetId];

        if ($excludeProjectId) {
            $sql .= " AND p.projects_id != ?";
            $params[] = $excludeProjectId;
        }

        // Date overlap: project overlaps if its delivery period intersects our range
        $sql .= " AND (
            COALESCE(p.projects_dates_deliver_start, p.projects_dates_use_start) < ?
            AND COALESCE(p.projects_dates_deliver_end, p.projects_dates_use_end) > ?
        )";
        $params[] = $endDate;
        $params[] = $startDate;

        $assignments = $this->db->rawQuery($sql, $params) ?: [];

        foreach ($assignments as $a) {
            // Skip if assets are released (cancelled/lost projects)
            if ($a['projectsStatuses_assetsReleased']) continue;

            $conflicts[] = [
                'type' => 'project',
                'project_id' => $a['projects_id'],
                'project_name' => $a['projects_name'],
                'status' => $a['projectsStatuses_name'],
                'start' => $a['projects_dates_deliver_start'] ?? $a['projects_dates_use_start'],
                'end' => $a['projects_dates_deliver_end'] ?? $a['projects_dates_use_end'],
            ];
        }

        // 2) Check manual availability blocks
        $this->db->where('assets_id', $assetId);
        $this->db->where('deleted', 0);
        $this->db->where('block_start', $endDate, '<');
        $this->db->where('block_end', $startDate, '>');
        $blocks = $this->db->get('asset_availability_blocks') ?: [];

        foreach ($blocks as $b) {
            $conflicts[] = [
                'type' => 'block',
                'block_type' => $b['block_type'],
                'reason' => $b['reason'],
                'start' => $b['block_start'],
                'end' => $b['block_end'],
            ];
        }

        return $conflicts;
    }

    /**
     * Check availability for multiple assets at once (bulk check)
     */
    public function checkBulkAvailability(array $assetIds, string $startDate, string $endDate, ?int $excludeProjectId = null): array
    {
        $result = [];
        foreach ($assetIds as $assetId) {
            $conflicts = $this->getAssetConflicts($assetId, $startDate, $endDate, $excludeProjectId);
            $result[$assetId] = [
                'available' => count($conflicts) === 0,
                'conflicts' => $conflicts,
            ];
        }
        return $result;
    }

    /**
     * Get availability calendar for an asset type (all individual assets of a type)
     */
    public function getAssetTypeAvailability(int $assetTypeId, int $instanceId, string $startDate, string $endDate): array
    {
        $this->db->where('assetTypes_id', $assetTypeId);
        $this->db->where('instances_id', $instanceId);
        $this->db->where('assets_deleted', 0);
        $assets = $this->db->get('assets', null, ['assets_id', 'assets_tag']) ?: [];

        $total = count($assets);
        $available = 0;
        $assetDetails = [];

        foreach ($assets as $asset) {
            $conflicts = $this->getAssetConflicts($asset['assets_id'], $startDate, $endDate);
            $isAvailable = count($conflicts) === 0;
            if ($isAvailable) $available++;

            $assetDetails[] = [
                'assets_id' => $asset['assets_id'],
                'assets_tag' => $asset['assets_tag'],
                'available' => $isAvailable,
                'conflicts' => $conflicts,
            ];
        }

        return [
            'total' => $total,
            'available' => $available,
            'unavailable' => $total - $available,
            'assets' => $assetDetails,
        ];
    }

    /**
     * Get calendar events for an asset (for FullCalendar integration)
     */
    public function getCalendarEvents(int $assetId, string $startDate, string $endDate): array
    {
        $events = [];

        // Project assignments
        $sql = "SELECT aa.assetsAssignments_id, p.projects_id, p.projects_name,
                       COALESCE(p.projects_dates_deliver_start, p.projects_dates_use_start) as ev_start,
                       COALESCE(p.projects_dates_deliver_end, p.projects_dates_use_end) as ev_end,
                       ps.projectsStatuses_name, ps.projectsStatuses_backgroundColour, ps.projectsStatuses_foregroundColour
                FROM assetsAssignments aa
                JOIN projects p ON aa.projects_id = p.projects_id
                LEFT JOIN projectsStatuses ps ON p.projectsStatuses_id = ps.projectsStatuses_id
                WHERE aa.assets_id = ?
                AND aa.assetsAssignments_deleted = 0
                AND p.projects_deleted = 0
                AND COALESCE(p.projects_dates_deliver_start, p.projects_dates_use_start) < ?
                AND COALESCE(p.projects_dates_deliver_end, p.projects_dates_use_end) > ?";
        $assignments = $this->db->rawQuery($sql, [$assetId, $endDate, $startDate]) ?: [];

        foreach ($assignments as $a) {
            $events[] = [
                'id' => 'proj_' . $a['assetsAssignments_id'],
                'title' => $a['projects_name'],
                'start' => $a['ev_start'],
                'end' => $a['ev_end'],
                'color' => $a['projectsStatuses_backgroundColour'] ?? '#007bff',
                'textColor' => $a['projectsStatuses_foregroundColour'] ?? '#fff',
                'type' => 'project',
                'project_id' => $a['projects_id'],
            ];
        }

        // Manual blocks
        $this->db->where('assets_id', $assetId);
        $this->db->where('deleted', 0);
        $this->db->where('block_start', $endDate, '<');
        $this->db->where('block_end', $startDate, '>');
        $blocks = $this->db->get('asset_availability_blocks') ?: [];

        $blockColors = [
            'maintenance' => '#ffc107',
            'reserved' => '#17a2b8',
            'unavailable' => '#dc3545',
            'other' => '#6c757d',
        ];

        foreach ($blocks as $b) {
            $events[] = [
                'id' => 'block_' . $b['id'],
                'title' => $b['reason'] ?? ucfirst($b['block_type']),
                'start' => $b['block_start'],
                'end' => $b['block_end'],
                'color' => $blockColors[$b['block_type']] ?? '#6c757d',
                'type' => 'block',
                'block_type' => $b['block_type'],
            ];
        }

        return $events;
    }

    /**
     * Create a manual availability block
     */
    public function createBlock(int $instanceId, int $assetId, string $type, string $start, string $end, ?string $reason, int $userId): int
    {
        $this->db->insert('asset_availability_blocks', [
            'assets_id'   => $assetId,
            'instances_id' => $instanceId,
            'block_type'  => $type,
            'block_start' => $start,
            'block_end'   => $end,
            'reason'      => $reason,
            'created_by'  => $userId,
        ]);
        return $this->db->getInsertId();
    }

    /**
     * Delete a manual availability block
     */
    public function deleteBlock(int $blockId, int $instanceId): bool
    {
        $this->db->where('id', $blockId);
        $this->db->where('instances_id', $instanceId);
        return $this->db->update('asset_availability_blocks', ['deleted' => 1]);
    }
}
