<?php
/**
 * QuickEntryService - Schnellerfassung fuer Projekte
 *
 * Ermoeglicht das Anlegen eines Projekts in unter 30 Sekunden
 * mit nur den noetigsten Feldern. Weitere Details koennen spaeter ergaenzt werden.
 */
class QuickEntryService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Projekt schnell anlegen (Minimal-Felder)
     */
    public function createQuickProject(int $instanceId, int $userId, array $data): array
    {
        $name = trim($data['name'] ?? '');
        if (empty($name)) {
            return ['success' => false, 'error' => 'Projektname erforderlich'];
        }

        $startDate = $data['start_date'] ?? date('Y-m-d');
        $endDate = $data['end_date'] ?? $startDate;
        $clientId = intval($data['client_id'] ?? 0);

        // Validierung
        if (!strtotime($startDate) || !strtotime($endDate)) {
            return ['success' => false, 'error' => 'Ungueltiges Datum'];
        }
        if (strtotime($endDate) < strtotime($startDate)) {
            $endDate = $startDate;
        }

        $projectData = [
            'projects_name' => $name,
            'instances_id' => $instanceId,
            'projects_dates_use_start' => $startDate . ' 08:00:00',
            'projects_dates_use_end' => $endDate . ' 22:00:00',
            'projects_dates_deliver_start' => $startDate . ' 06:00:00',
            'projects_dates_deliver_end' => date('Y-m-d', strtotime($endDate . ' +1 day')) . ' 12:00:00',
            'projects_manager' => $userId,
            'projects_created' => date('Y-m-d H:i:s'),
            'projects_deleted' => 0,
            'projects_status' => 'draft',
        ];

        if ($clientId > 0) {
            $projectData['clients_id'] = $clientId;
        }

        if (!empty($data['location'])) {
            $projectData['projects_location'] = trim($data['location']);
        }
        if (!empty($data['notes'])) {
            $projectData['projects_description'] = trim($data['notes']);
        }

        $projectId = $this->db->insert('projects', $projectData);
        if (!$projectId) {
            return ['success' => false, 'error' => 'Fehler beim Anlegen'];
        }

        // Equipment schnell zuweisen (optional)
        $assignedCount = 0;
        if (!empty($data['asset_type_ids']) && is_array($data['asset_type_ids'])) {
            foreach ($data['asset_type_ids'] as $assetTypeId) {
                $assetTypeId = intval($assetTypeId);
                if ($assetTypeId <= 0) continue;

                // Erstes verfuegbares Asset dieses Typs finden
                $sql = "SELECT a.assets_id FROM assets a
                        JOIN assetTypes at ON a.assetTypes_id = at.assetTypes_id
                        WHERE at.assetTypes_id = ? AND a.assets_deleted = 0
                        AND at.instances_id = ?
                        AND a.assets_id NOT IN (
                            SELECT aa.assets_id FROM assetsAssignments aa
                            JOIN projects p ON aa.projects_id = p.projects_id
                            WHERE aa.assetsAssignments_deleted = 0
                            AND p.projects_deleted = 0
                            AND p.projects_dates_use_start <= ?
                            AND p.projects_dates_use_end >= ?
                        )
                        LIMIT 1";
                $available = $this->db->rawQuery($sql, [
                    $assetTypeId, $instanceId, $endDate . ' 22:00:00', $startDate . ' 08:00:00'
                ]);

                if ($available) {
                    $this->db->insert('assetsAssignments', [
                        'assets_id' => $available[0]['assets_id'],
                        'projects_id' => $projectId,
                        'assetsAssignments_deleted' => 0,
                    ]);
                    $assignedCount++;
                }
            }
        }

        return [
            'success' => true,
            'project_id' => $projectId,
            'assets_assigned' => $assignedCount,
        ];
    }

    /**
     * Kunden-Schnellsuche (fuer Autocomplete)
     */
    public function searchClients(string $term, int $instanceId, int $limit = 5): array
    {
        if (strlen($term) < 2) return [];

        $sqlSan = new SqlSanitizer();
        $safeTerm = $sqlSan->sanitizeSearch($term);

        $this->db->where('instances_id', $instanceId);
        $this->db->where('clients_deleted', 0);
        $this->db->where("(clients_name LIKE ? OR clients_company LIKE ?)", ["%{$safeTerm}%", "%{$safeTerm}%"]);
        $this->db->orderBy('clients_name', 'ASC');
        return $this->db->get('clients', $limit, ['clients_id', 'clients_name', 'clients_company']) ?: [];
    }
}
