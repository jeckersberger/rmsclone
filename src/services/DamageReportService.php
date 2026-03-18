<?php
/**
 * Schadensmeldungen bei Rueckgabe
 *
 * Workflow:
 * 1. Bei Check-in wird Zustand geprueft
 * 2. Schaden wird dokumentiert (Text + Fotos)
 * 3. Automatisch Wartungsauftrag erstellt
 * 4. Optional: Schadenskosten dem Kunden zuordnen
 *
 * Tabellen:
 *   damage_reports  - Schadensmeldungen
 */
class DamageReportService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Create a damage report
     */
    public function createReport(int $instanceId, int $assetId, int $projectId, array $data, int $userId): int
    {
        $reportId = $this->db->insert('damage_reports', [
            'instances_id' => $instanceId,
            'assets_id' => $assetId,
            'projects_id' => $projectId,
            'severity' => $data['severity'] ?? 'minor', // minor, moderate, major, total_loss
            'description' => $data['description'],
            'repair_estimate' => $data['repair_estimate'] ?? null,
            'charge_to_client' => $data['charge_to_client'] ?? 0,
            'status' => 'reported',
            'reported_by' => $userId,
        ]);

        // Auto-create maintenance job if severity is moderate+
        if (in_array($data['severity'] ?? 'minor', ['moderate', 'major', 'total_loss'])) {
            $this->createMaintenanceJob($instanceId, $assetId, $reportId, $data, $userId);
        }

        return $reportId;
    }

    /**
     * Auto-create a maintenance job from damage report
     */
    private function createMaintenanceJob(int $instanceId, int $assetId, int $reportId, array $data, int $userId): int
    {
        // Get asset info for the title
        $this->db->where('a.assets_id', $assetId);
        $this->db->join('assetTypes at', 'a.assetTypes_id = at.assetTypes_id', 'LEFT');
        $asset = $this->db->getOne('assets a', null, ['a.assets_tag', 'at.assetTypes_name']);

        $title = 'Schaden: ' . ($asset['assetTypes_name'] ?? 'Asset') .
                 ($asset['assets_tag'] ? ' #' . $asset['assets_tag'] : '');

        $severity = $data['severity'] ?? 'minor';
        $priority = match($severity) {
            'total_loss' => 1,
            'major' => 2,
            'moderate' => 3,
            default => 4
        };

        $jobId = $this->db->insert('maintenanceJobs', [
            'maintenanceJobs_title' => $title,
            'maintenanceJobs_faultDescription' => $data['description'],
            'maintenanceJobs_priority' => $priority,
            'maintenanceJobs_status' => 0, // open
            'maintenanceJobs_timestamp' => date('Y-m-d H:i:s'),
            'instances_id' => $instanceId,
            'assets_id' => $assetId,
            'users_userid' => $userId,
        ]);

        // Link report to maintenance job
        if ($jobId) {
            $this->db->where('id', $reportId);
            $this->db->update('damage_reports', ['maintenanceJobs_id' => $jobId]);
        }

        return $jobId ?: 0;
    }

    /**
     * Get damage reports for an asset
     */
    public function getAssetReports(int $assetId): array
    {
        $this->db->where('assets_id', $assetId);
        $this->db->where('deleted', 0);
        $this->db->join('projects', 'damage_reports.projects_id = projects.projects_id', 'LEFT');
        $this->db->join('users', 'damage_reports.reported_by = users.users_userid', 'LEFT');
        $this->db->orderBy('damage_reports.created_at', 'DESC');
        return $this->db->get('damage_reports', null, [
            'damage_reports.*', 'projects.projects_name', 'users.users_name1', 'users.users_name2'
        ]) ?: [];
    }

    /**
     * Get damage reports for a project
     */
    public function getProjectReports(int $projectId): array
    {
        $this->db->where('projects_id', $projectId);
        $this->db->where('deleted', 0);
        $this->db->join('assets', 'damage_reports.assets_id = assets.assets_id', 'LEFT');
        $this->db->join('assetTypes', 'assets.assetTypes_id = assetTypes.assetTypes_id', 'LEFT');
        $this->db->orderBy('damage_reports.created_at', 'DESC');
        return $this->db->get('damage_reports', null, [
            'damage_reports.*', 'assets.assets_tag', 'assetTypes.assetTypes_name'
        ]) ?: [];
    }

    /**
     * Update damage report status
     */
    public function updateStatus(int $reportId, string $status, ?string $resolution = null): bool
    {
        $this->db->where('id', $reportId);
        $data = ['status' => $status];
        if ($resolution) $data['resolution'] = $resolution;
        if ($status === 'resolved') $data['resolved_at'] = date('Y-m-d H:i:s');
        return $this->db->update('damage_reports', $data);
    }

    /**
     * Get overview stats
     */
    public function getStats(int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('deleted', 0);
        $this->db->where('status', 'reported');
        $open = $this->db->getValue('damage_reports', 'COUNT(*)');

        $this->db->where('instances_id', $instanceId);
        $this->db->where('deleted', 0);
        $this->db->where("created_at >= '" . date('Y-m-01') . "'");
        $thisMonth = $this->db->getValue('damage_reports', 'COUNT(*)');

        return ['open' => $open, 'this_month' => $thisMonth];
    }
}
