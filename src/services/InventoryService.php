<?php
/**
 * Inventur-Service (Scanner / RFID)
 *
 * Schnellinventur: Assets per Scanner/RFID als "vorhanden" markieren.
 * - Neue Inventur starten
 * - Assets scannen (Barcode/Tag)
 * - Fehlende Assets identifizieren
 * - Inventur-Bericht erstellen
 *
 * Tabellen:
 *   inventory_sessions      - Inventursitzungen
 *   inventory_session_items - Gescannte Assets
 */
class InventoryService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Start a new inventory session
     */
    public function startSession(int $instanceId, int $userId, ?string $title = null): int
    {
        return $this->db->insert('inventory_sessions', [
            'instances_id' => $instanceId,
            'title' => $title ?: 'Inventur ' . date('d.m.Y H:i'),
            'status' => 'in_progress',
            'started_by' => $userId,
        ]);
    }

    /**
     * Scan/register an asset in current inventory
     */
    public function scanAsset(int $sessionId, string $tagOrBarcode, int $userId, ?string $condition = null, ?string $location = null): array
    {
        // Find asset by tag or barcode
        $this->db->where('(assets_tag = ? OR assets_barcode = ?)', [$tagOrBarcode, $tagOrBarcode]);
        $this->db->where('assets_deleted', 0);
        $this->db->join('assetTypes', 'assets.assetTypes_id = assetTypes.assetTypes_id', 'LEFT');
        $asset = $this->db->getOne('assets', null, ['assets.assets_id', 'assets.assets_tag', 'assetTypes.assetTypes_name']);

        if (!$asset) {
            return ['success' => false, 'error' => 'asset_not_found', 'tag' => $tagOrBarcode];
        }

        // Check if already scanned in this session
        $this->db->where('inventory_sessions_id', $sessionId);
        $this->db->where('assets_id', $asset['assets_id']);
        $existing = $this->db->getOne('inventory_session_items');
        if ($existing) {
            return [
                'success' => true, 'duplicate' => true,
                'asset' => $asset, 'scanned_at' => $existing['scanned_at']
            ];
        }

        $this->db->insert('inventory_session_items', [
            'inventory_sessions_id' => $sessionId,
            'assets_id' => $asset['assets_id'],
            'scanned_by' => $userId,
            'scanned_at' => date('Y-m-d H:i:s'),
            'condition_note' => $condition,
            'location_note' => $location,
        ]);

        return ['success' => true, 'duplicate' => false, 'asset' => $asset];
    }

    /**
     * Get session progress
     */
    public function getSessionProgress(int $sessionId, int $instanceId): array
    {
        // Total assets in instance
        $this->db->where('instances_id', $instanceId);
        $this->db->where('assets_deleted', 0);
        $totalAssets = (int)$this->db->getValue('assets', 'COUNT(*)');

        // Scanned in this session
        $this->db->where('inventory_sessions_id', $sessionId);
        $scannedCount = (int)$this->db->getValue('inventory_session_items', 'COUNT(*)');

        // Get scanned assets
        $this->db->where('isi.inventory_sessions_id', $sessionId);
        $this->db->join('assets a', 'isi.assets_id = a.assets_id', 'LEFT');
        $this->db->join('assetTypes at', 'a.assetTypes_id = at.assetTypes_id', 'LEFT');
        $this->db->orderBy('isi.scanned_at', 'DESC');
        $scanned = $this->db->get('inventory_session_items isi', null, [
            'isi.*', 'a.assets_tag', 'at.assetTypes_name'
        ]) ?: [];

        return [
            'total_assets' => $totalAssets,
            'scanned' => $scannedCount,
            'missing' => $totalAssets - $scannedCount,
            'progress_pct' => $totalAssets > 0 ? round(($scannedCount / $totalAssets) * 100, 1) : 0,
            'items' => $scanned,
        ];
    }

    /**
     * Get missing assets (not scanned in a session)
     */
    public function getMissingAssets(int $sessionId, int $instanceId): array
    {
        $sql = "SELECT a.assets_id, a.assets_tag, at.assetTypes_name, ac.assetCategories_name
                FROM assets a
                JOIN assetTypes at ON a.assetTypes_id = at.assetTypes_id
                LEFT JOIN assetCategories ac ON at.assetCategories_id = ac.assetCategories_id
                WHERE a.instances_id = ? AND a.assets_deleted = 0
                AND a.assets_id NOT IN (
                    SELECT assets_id FROM inventory_session_items WHERE inventory_sessions_id = ?
                )
                ORDER BY ac.assetCategories_rank ASC, at.assetTypes_name ASC";
        return $this->db->rawQuery($sql, [$instanceId, $sessionId]) ?: [];
    }

    /**
     * Complete an inventory session
     */
    public function completeSession(int $sessionId, int $instanceId): array
    {
        $progress = $this->getSessionProgress($sessionId, $instanceId);
        $missing = $this->getMissingAssets($sessionId, $instanceId);

        $this->db->where('id', $sessionId);
        $this->db->update('inventory_sessions', [
            'status' => 'completed',
            'completed_at' => date('Y-m-d H:i:s'),
            'total_scanned' => $progress['scanned'],
            'total_missing' => $progress['missing'],
        ]);

        return [
            'progress' => $progress,
            'missing' => $missing,
        ];
    }

    /**
     * Get previous inventory sessions
     */
    public function getSessions(int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('deleted', 0);
        $this->db->orderBy('created_at', 'DESC');
        return $this->db->get('inventory_sessions') ?: [];
    }
}
