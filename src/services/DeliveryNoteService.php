<?php

require_once __DIR__ . '/QrCodeGenerator.php';

/**
 * Lieferschein-Service mit Unterschriftsfeld
 *
 * Generiert Lieferscheine aus Packlisten mit:
 * - Kundendaten (Name, Adresse)
 * - Equipment-Liste mit Zustand
 * - Unterschriftsfelder (Ausgabe + Rueckgabe)
 * - QR-Code fuer digitale Verknuepfung (Packauftrag)
 */
class DeliveryNoteService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Generate delivery note data from a project
     */
    public function generateFromProject(int $instanceId, int $projectId): array
    {
        // Project
        $this->db->where('projects_id', $projectId);
        $this->db->where('instances_id', $instanceId);
        $this->db->join('clients', 'projects.clients_id = clients.clients_id', 'LEFT');
        $project = $this->db->getOne('projects', ['projects.*', 'clients.*']);
        if (!$project) return [];

        // Instance/business details
        $this->db->where('instances_id', $instanceId);
        $instance = $this->db->getOne('instances');

        // Assigned assets
        $sql = "SELECT a.assets_id, a.assets_tag, at.assetTypes_name,
                       at.assetTypes_mass, a.assets_mass,
                       ac.assetCategories_name, ac.assetCategories_rank,
                       aa.assetsAssignments_comment
                FROM assetsAssignments aa
                JOIN assets a ON aa.assets_id = a.assets_id
                JOIN assetTypes at ON a.assetTypes_id = at.assetTypes_id
                LEFT JOIN assetCategories ac ON at.assetCategories_id = ac.assetCategories_id
                WHERE aa.projects_id = ? AND aa.assetsAssignments_deleted = 0 AND a.assets_deleted = 0
                ORDER BY ac.assetCategories_rank ASC, at.assetTypes_name ASC, a.assets_tag ASC";
        $assets = $this->db->rawQuery($sql, [$projectId]) ?: [];

        // Group by category
        $categories = [];
        $totalWeight = 0;
        foreach ($assets as $a) {
            $cat = $a['assetCategories_name'] ?: 'Sonstige';
            if (!isset($categories[$cat])) $categories[$cat] = [];
            $weight = (float)($a['assets_mass'] ?? $a['assetTypes_mass'] ?? 0);
            $totalWeight += $weight;
            $a['weight'] = $weight;
            $categories[$cat][] = $a;
        }

        // Generate delivery note number via SequenceService (fortlaufend, GoBD-konform)
        $noteNumber = SequenceService::next($this->db, $instanceId, 'delivery_note');

        return [
            'note_number' => $noteNumber,
            'date' => date('d.m.Y'),
            'project' => $project,
            'instance' => $instance,
            'categories' => $categories,
            'total_items' => count($assets),
            'total_weight' => $totalWeight,
            'has_signature_fields' => true,
        ];
    }
}
