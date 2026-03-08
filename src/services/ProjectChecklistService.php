<?php
/**
 * Projekt-Checklisten
 *
 * Verwaltet Checklisten-Eintraege pro Projekt.
 * Ermoeglicht Hinzufuegen, Abhaken, Loeschen, Umsortieren.
 *
 * Tabellen:
 *   project_checklists - Checklisten-Eintraege
 */
class ProjectChecklistService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Neuen Checklisten-Eintrag hinzufuegen
     */
    public function addItem(int $projectId, string $title): int
    {
        // Naechste sort_order ermitteln
        $this->db->where('projects_id', $projectId);
        $maxOrder = $this->db->getValue('project_checklists', 'COALESCE(MAX(sort_order), 0)');

        return $this->db->insert('project_checklists', [
            'projects_id' => $projectId,
            'title' => $title,
            'sort_order' => ($maxOrder ?: 0) + 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Eintrag als erledigt/offen umschalten
     */
    public function toggleItem(int $itemId, int $userId): bool
    {
        $this->db->where('id', $itemId);
        $item = $this->db->getOne('project_checklists', ['id', 'is_completed']);
        if (!$item) return false;

        $newState = $item['is_completed'] ? 0 : 1;
        $this->db->where('id', $itemId);
        return $this->db->update('project_checklists', [
            'is_completed' => $newState,
            'completed_at' => $newState ? date('Y-m-d H:i:s') : null,
            'completed_by' => $newState ? $userId : null,
        ]);
    }

    /**
     * Alle Eintraege eines Projekts abrufen
     */
    public function getItems(int $projectId): array
    {
        $this->db->where('projects_id', $projectId);
        $this->db->orderBy('sort_order', 'ASC');
        return $this->db->get('project_checklists') ?: [];
    }

    /**
     * Eintrag loeschen
     */
    public function deleteItem(int $itemId): bool
    {
        $this->db->where('id', $itemId);
        return $this->db->delete('project_checklists');
    }

    /**
     * Eintraege umsortieren
     *
     * @param array $items Array von ['id' => int, 'sort_order' => int]
     */
    public function reorder(array $items): bool
    {
        foreach ($items as $item) {
            if (empty($item['id'])) continue;
            $this->db->where('id', (int)$item['id']);
            $this->db->update('project_checklists', [
                'sort_order' => (int)($item['sort_order'] ?? 0),
            ]);
        }
        return true;
    }

    /**
     * Fortschritt abrufen (erledigt/gesamt)
     */
    public function getProgress(int $projectId): array
    {
        $this->db->where('projects_id', $projectId);
        $total = $this->db->getValue('project_checklists', 'COUNT(*)');

        $this->db->where('projects_id', $projectId);
        $this->db->where('is_completed', 1);
        $completed = $this->db->getValue('project_checklists', 'COUNT(*)');

        return [
            'completed' => (int)$completed,
            'total' => (int)$total,
        ];
    }
}
