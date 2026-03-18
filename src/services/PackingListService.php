<?php
/**
 * Packlisten-Service
 *
 * Generiert Packlisten aus Projektzuweisungen, gruppiert nach Kategorie.
 * Unterstuetzt Packstatus-Tracking und PDF-Export.
 */
class PackingListService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Generate a packing list from project assignments
     */
    public function generateFromProject(int $instanceId, int $projectId, int $userId, ?string $title = null): int
    {
        // Get project info
        $this->db->where('projects_id', $projectId);
        $this->db->where('instances_id', $instanceId);
        $project = $this->db->getOne('projects', null, ['projects_name']);
        if (!$project) return 0;

        // Generate list number
        $listNumber = 'PL-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

        $this->db->insert('packing_lists', [
            'instances_id' => $instanceId,
            'projects_id'  => $projectId,
            'list_number'  => $listNumber,
            'title'        => $title ?: 'Packliste ' . $project['projects_name'],
            'status'       => 'draft',
            'created_by'   => $userId,
        ]);
        $listId = $this->db->getInsertId();

        // Get all assigned assets for this project
        $sql = "SELECT aa.assetsAssignments_id, a.assets_id, a.assets_tag,
                       at.assetTypes_name, at.assetTypes_mass,
                       a.assets_mass,
                       ac.assetCategories_name, ac.assetCategories_rank
                FROM assetsAssignments aa
                JOIN assets a ON aa.assets_id = a.assets_id
                JOIN assetTypes at ON a.assetTypes_id = at.assetTypes_id
                LEFT JOIN assetCategories ac ON at.assetCategories_id = ac.assetCategories_id
                WHERE aa.projects_id = ? AND aa.assetsAssignments_deleted = 0 AND a.assets_deleted = 0
                ORDER BY ac.assetCategories_rank ASC, at.assetTypes_name ASC, a.assets_tag ASC";

        $assets = $this->db->rawQuery($sql, [$projectId]) ?: [];

        $totalWeight = 0;
        $sortOrder = 0;
        foreach ($assets as $a) {
            $weight = (float)($a['assets_mass'] ?? $a['assetTypes_mass'] ?? 0);
            $totalWeight += $weight;

            $this->db->insert('packing_list_items', [
                'packing_lists_id'     => $listId,
                'assets_id'            => $a['assets_id'],
                'assetsAssignments_id' => $a['assetsAssignments_id'],
                'item_name'            => $a['assetTypes_name'] . ($a['assets_tag'] ? ' #' . $a['assets_tag'] : ''),
                'quantity'             => 1,
                'weight'               => $weight,
                'sort_order'           => $sortOrder++,
            ]);
        }

        // Update totals
        $this->db->where('id', $listId);
        $this->db->update('packing_lists', [
            'total_weight' => $totalWeight,
            'total_items'  => count($assets),
        ]);

        return $listId;
    }

    /**
     * Get packing list with items
     */
    public function getList(int $listId, int $instanceId): ?array
    {
        $this->db->where('pl.id', $listId);
        $this->db->where('pl.instances_id', $instanceId);
        $this->db->join('projects p', 'pl.projects_id=p.projects_id', 'LEFT');
        $list = $this->db->getOne('packing_lists pl', null, ['pl.*', 'p.projects_name']);
        if (!$list) return null;

        $this->db->where('packing_lists_id', $listId);
        $this->db->orderBy('sort_order', 'ASC');
        $list['items'] = $this->db->get('packing_list_items') ?: [];

        $list['packed_count'] = 0;
        foreach ($list['items'] as $item) {
            if ($item['packed']) $list['packed_count']++;
        }

        return $list;
    }

    /**
     * Get all packing lists for a project
     */
    public function getProjectLists(int $instanceId, int $projectId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('projects_id', $projectId);
        $this->db->orderBy('created_at', 'DESC');
        return $this->db->get('packing_lists') ?: [];
    }

    /**
     * Toggle packed status for an item
     */
    public function togglePacked(int $itemId, int $userId): bool
    {
        $this->db->where('id', $itemId);
        $item = $this->db->getOne('packing_list_items', null);
        if (!$item) return false;

        $this->db->where('id', $itemId);
        if ($item['packed']) {
            $this->db->update('packing_list_items', [
                'packed' => 0, 'packed_by' => null, 'packed_at' => null
            ]);
        } else {
            $this->db->update('packing_list_items', [
                'packed' => 1, 'packed_by' => $userId, 'packed_at' => date('Y-m-d H:i:s')
            ]);
        }

        // Check if all items are packed and update list status
        $this->db->where('packing_lists_id', $item['packing_lists_id']);
        $this->db->where('packed', 0);
        $unpacked = $this->db->getValue('packing_list_items', 'COUNT(*)');
        if ($unpacked == 0) {
            $this->db->where('id', $item['packing_lists_id']);
            $this->db->update('packing_lists', ['status' => 'packed']);
        }

        return true;
    }

    /**
     * Update packing list status
     */
    public function updateStatus(int $listId, int $instanceId, string $status): bool
    {
        $this->db->where('id', $listId);
        $this->db->where('instances_id', $instanceId);
        return $this->db->update('packing_lists', ['status' => $status]);
    }
}
