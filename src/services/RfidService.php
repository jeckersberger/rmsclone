<?php
/**
 * RFID Scanner Integration Service
 *
 * Manages RFID tag assignments, scan processing, checkout/checkin workflows,
 * and inventory operations for the Chafon CF-H906 UHF RFID handheld reader.
 */
class RfidService
{
    private $db;
    private ?StockItemService $stockService = null;
    private ?CrossInstanceLookupService $crossLookup = null;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Set StockItemService for dual-entity RFID lookups
     */
    public function setStockService(StockItemService $stockService): void
    {
        $this->stockService = $stockService;
    }

    /**
     * Set CrossInstanceLookupService for partner/federation tag lookups
     */
    public function setCrossLookupService(CrossInstanceLookupService $service): void
    {
        $this->crossLookup = $service;
    }

    /**
     * Universal RFID lookup: finds either an Asset or a Stock Instance
     * Returns a normalized result with entity_type = 'asset' | 'stock_instance'
     *
     * @param int $instanceId Tenant instance ID
     * @param string $rfidTag RFID EPC tag
     * @return array|null Normalized entity with entity_type field
     */
    public function findEntityByTag(int $instanceId, string $rfidTag): ?array
    {
        // 1. Try asset first
        $asset = $this->findByTag($instanceId, $rfidTag);
        if ($asset) {
            $asset['entity_type'] = 'asset';
            $asset['entity_id'] = $asset['assets_id'];
            $asset['display_name'] = $asset['type_name'] . ' - ' . ($asset['assets_name'] ?? '#' . $asset['assets_id']);
            return $asset;
        }

        // 2. Try stock instance
        if ($this->stockService) {
            $stockInst = $this->stockService->findByRfidTag($instanceId, $rfidTag);
            if ($stockInst) {
                $stockInst['entity_type'] = 'stock_instance';
                $stockInst['entity_id'] = $stockInst['id'];
                $stockInst['display_name'] = $stockInst['item_name'] . ' #' . $stockInst['instance_number'];
                return $stockInst;
            }
        }

        // 3. Try cross-instance lookup (partner instances and federated servers)
        if ($this->crossLookup) {
            $crossResult = $this->crossLookup->lookupTag($rfidTag);
            if ($crossResult) {
                return [
                    'entity_type' => 'partner_' . $crossResult['entity_type'],
                    'entity_id' => null,
                    'display_name' => $crossResult['entity']['display_name'] ?? 'Unbekannt',
                    'owner_name' => $crossResult['owner_instance_name'],
                    'owner_server_url' => $crossResult['owner_server_url'],
                    'source' => $crossResult['source'],
                    'is_foreign' => true,
                    'rfid_tag' => $rfidTag,
                ];
            }
        }

        return null;
    }

    /**
     * Universal scan processing: handles both assets and stock instances
     *
     * @param int $instanceId Tenant instance ID
     * @param string $rfidTag RFID EPC tag
     * @param string $action checkout, checkin, locate, inventory
     * @param int $userId User ID
     * @param int|null $projectId Project ID (for checkout)
     * @return array Scan result with entity info
     */
    public function processUniversalScan(int $instanceId, string $rfidTag, string $action, int $userId, ?int $projectId = null): array
    {
        $entity = $this->findEntityByTag($instanceId, $rfidTag);

        if (!$entity) {
            return [
                'success'      => false,
                'entity_type'  => null,
                'entity'       => null,
                'message'      => 'Unbekannter Tag - weder Gerät noch Artikel',
                'action_taken' => null,
            ];
        }

        if ($entity['entity_type'] === 'asset') {
            // Delegate to existing asset scan logic
            $result = $this->processScan($instanceId, $rfidTag, $action, $userId, $projectId);
            $result['entity_type'] = 'asset';
            return $result;
        }

        // Stock instance handling
        if ($entity['entity_type'] === 'stock_instance' && $this->stockService) {
            switch ($action) {
                case 'checkout':
                    if (!$projectId) {
                        return [
                            'success'      => false,
                            'entity_type'  => 'stock_instance',
                            'entity'       => $entity,
                            'message'      => 'Projekt-ID benötigt für Ausleihe',
                            'action_taken' => null,
                        ];
                    }
                    $result = $this->stockService->checkOut($entity['id'], $instanceId, $projectId, $userId);
                    return [
                        'success'      => $result['success'],
                        'entity_type'  => 'stock_instance',
                        'entity'       => $entity,
                        'asset'        => $entity, // backwards compat
                        'message'      => $result['message'],
                        'action_taken' => $result['success'] ? 'checkout' : null,
                    ];

                case 'checkin':
                    $result = $this->stockService->checkIn($entity['id'], $instanceId, $userId);
                    return [
                        'success'      => $result['success'],
                        'entity_type'  => 'stock_instance',
                        'entity'       => $entity,
                        'asset'        => $entity, // backwards compat
                        'message'      => $result['message'],
                        'action_taken' => $result['success'] ? 'checkin' : null,
                    ];

                case 'locate':
                    // Update last_scan_at
                    $this->db->where('id', $entity['id']);
                    $this->db->update('stock_instances', [
                        'last_scan_at' => date('Y-m-d H:i:s'),
                    ]);
                    return [
                        'success'      => true,
                        'entity_type'  => 'stock_instance',
                        'entity'       => $entity,
                        'asset'        => $entity,
                        'message'      => 'Artikel lokalisiert: ' . $entity['display_name'],
                        'action_taken' => 'locate',
                    ];

                case 'inventory':
                    return [
                        'success'      => true,
                        'entity_type'  => 'stock_instance',
                        'entity'       => $entity,
                        'asset'        => $entity,
                        'message'      => 'Artikel erfasst: ' . $entity['display_name'],
                        'action_taken' => 'inventory',
                    ];
            }
        }

        return [
            'success'      => false,
            'entity_type'  => $entity['entity_type'],
            'entity'       => $entity,
            'message'      => 'Ungültige Aktion',
            'action_taken' => null,
        ];
    }

    /**
     * Assign an RFID EPC tag to an asset
     *
     * @param int $assetId Asset ID
     * @param string $rfidTag RFID EPC tag
     * @param int $userId User ID for audit trail
     * @return bool Success status
     */
    public function assignTag(int $assetId, string $rfidTag, int $userId): bool
    {
        $rfidTag = trim($rfidTag);

        if (empty($rfidTag)) {
            return false;
        }

        // Validate uniqueness - check if tag exists elsewhere
        $this->db->where('asset_definableFields_1', $rfidTag);
        $this->db->where('assets_id', $assetId, '!=');
        $existing = $this->db->getOne('assets');

        if ($existing) {
            return false; // Tag already assigned to another asset
        }

        // Update asset with RFID tag in asset_definableFields_1
        $this->db->where('assets_id', $assetId);
        return (bool) $this->db->update('assets', [
            'asset_definableFields_1' => $rfidTag,
        ]);
    }

    /**
     * Remove RFID tag from an asset
     *
     * @param int $assetId Asset ID
     * @param int $userId User ID for audit trail
     * @return bool Success status
     */
    public function unassignTag(int $assetId, int $userId): bool
    {
        $this->db->where('assets_id', $assetId);
        return (bool) $this->db->update('assets', [
            'asset_definableFields_1' => null,
        ]);
    }

    /**
     * Find asset by RFID tag with full details
     *
     * @param int $instanceId Instance ID
     * @param string $rfidTag RFID EPC tag
     * @return array|null Asset with type name, status, current assignment
     */
    public function findByTag(int $instanceId, string $rfidTag): ?array
    {
        $rfidTag = trim($rfidTag);

        // Find asset by RFID tag
        $this->db->where('asset_definableFields_1', $rfidTag);
        $this->db->where('instances_id', $instanceId);
        $asset = $this->db->getOne('assets', null, ['assets_id', 'assets_name', 'assetTypes_id', 'asset_definableFields_1', 'asset_definableFields_2', 'asset_definableFields_3', 'asset_definableFields_4', 'asset_definableFields_5']);

        if (!$asset) {
            return null;
        }

        // Get asset type
        $this->db->where('assetTypes_id', $asset['assetTypes_id']);
        $assetType = $this->db->getOne('assetTypes', null, ['assetTypes_name']);

        $asset['type_name'] = $assetType ? $assetType['assetTypes_name'] : 'Unknown';

        // Get current assignment if active
        $this->db->where('assets_id', $asset['assets_id']);
        $this->db->where('assetsAssignments_end', null);
        $this->db->orderBy('assetsAssignments_id', 'DESC');
        $assignment = $this->db->getOne('assetsAssignments', null, ['assetsAssignments_id', 'projects_id', 'assetsAssignments_start']);

        $asset['current_assignment'] = $assignment ?: null;
        $asset['status'] = $assignment ? 'checked_out' : 'available';

        return $asset;
    }

    /**
     * Bulk lookup multiple RFID tags
     *
     * @param int $instanceId Instance ID
     * @param array $rfidTags Array of RFID tags
     * @return array Lookup results
     */
    public function bulkLookup(int $instanceId, array $rfidTags): array
    {
        $results = [];

        foreach ($rfidTags as $tag) {
            $asset = $this->findByTag($instanceId, $tag);
            $results[$tag] = $asset;
        }

        return $results;
    }

    /**
     * Process a single RFID scan
     *
     * @param int $instanceId Instance ID
     * @param string $rfidTag RFID EPC tag
     * @param string $action Action: checkout, checkin, inventory, locate
     * @param int $userId User ID
     * @param int|null $projectId Project ID (required for checkout)
     * @return array Result with keys: success, asset, message, action_taken
     */
    public function processScan(int $instanceId, string $rfidTag, string $action, int $userId, ?int $projectId = null): array
    {
        $asset = $this->findByTag($instanceId, $rfidTag);

        if (!$asset) {
            return [
                'success' => false,
                'asset' => null,
                'message' => 'Asset not found',
                'action_taken' => null,
            ];
        }

        switch ($action) {
            case 'checkout':
                if (!$projectId) {
                    return [
                        'success' => false,
                        'asset' => $asset,
                        'message' => 'Project ID required for checkout',
                        'action_taken' => null,
                    ];
                }

                if ($asset['status'] === 'checked_out') {
                    return [
                        'success' => false,
                        'asset' => $asset,
                        'message' => 'Asset already checked out',
                        'action_taken' => null,
                    ];
                }

                $result = $this->checkOut($instanceId, $asset['assets_id'], $projectId, $userId);
                return $result;

            case 'checkin':
                if ($asset['status'] !== 'checked_out') {
                    return [
                        'success' => false,
                        'asset' => $asset,
                        'message' => 'Asset not currently checked out',
                        'action_taken' => null,
                    ];
                }

                $result = $this->checkIn($instanceId, $asset['assets_id'], $userId);
                return $result;

            case 'locate':
                return [
                    'success' => true,
                    'asset' => $asset,
                    'message' => 'Asset located',
                    'action_taken' => 'locate',
                ];

            case 'inventory':
                return [
                    'success' => true,
                    'asset' => $asset,
                    'message' => 'Asset recorded for inventory',
                    'action_taken' => 'inventory',
                ];

            default:
                return [
                    'success' => false,
                    'asset' => null,
                    'message' => 'Invalid action',
                    'action_taken' => null,
                ];
        }
    }

    /**
     * Check asset out to a project
     *
     * @param int $instanceId Instance ID
     * @param int $assetId Asset ID
     * @param int $projectId Project ID
     * @param int $userId User ID
     * @return array Result
     */
    public function checkOut(int $instanceId, int $assetId, int $projectId, int $userId): array
    {
        // Get asset details
        $this->db->where('assets_id', $assetId);
        $asset = $this->db->getOne('assets', null, ['assets_id', 'assets_name', 'asset_definableFields_1']);

        if (!$asset) {
            return [
                'success' => false,
                'asset' => null,
                'message' => 'Asset not found',
                'action_taken' => null,
            ];
        }

        // Create assignment
        $assignmentId = $this->db->insert('assetsAssignments', [
            'assets_id' => $assetId,
            'projects_id' => $projectId,
            'instances_id' => $instanceId,
            'assetsAssignments_start' => date('Y-m-d H:i:s'),
            'assetsAssignments_end' => null,
        ]);

        if (!$assignmentId) {
            return [
                'success' => false,
                'asset' => $asset,
                'message' => 'Failed to create assignment',
                'action_taken' => null,
            ];
        }

        // Log scan
        $this->logScan($instanceId, $assetId, $asset['asset_definableFields_1'], 'checkout', $userId, $projectId);

        return [
            'success' => true,
            'asset' => $asset,
            'message' => 'Asset checked out successfully',
            'action_taken' => 'checkout',
        ];
    }

    /**
     * Check asset back in from a project
     *
     * @param int $instanceId Instance ID
     * @param int $assetId Asset ID
     * @param int $userId User ID
     * @return array Result
     */
    public function checkIn(int $instanceId, int $assetId, int $userId): array
    {
        // Get asset details
        $this->db->where('assets_id', $assetId);
        $asset = $this->db->getOne('assets', null, ['assets_id', 'assets_name', 'asset_definableFields_1']);

        if (!$asset) {
            return [
                'success' => false,
                'asset' => null,
                'message' => 'Asset not found',
                'action_taken' => null,
            ];
        }

        // Find active assignment
        $this->db->where('assets_id', $assetId);
        $this->db->where('assetsAssignments_end', null);
        $this->db->orderBy('assetsAssignments_id', 'DESC');
        $assignment = $this->db->getOne('assetsAssignments');

        if (!$assignment) {
            return [
                'success' => false,
                'asset' => $asset,
                'message' => 'No active assignment found',
                'action_taken' => null,
            ];
        }

        // End assignment
        $this->db->where('assetsAssignments_id', $assignment['assetsAssignments_id']);
        $updated = $this->db->update('assetsAssignments', [
            'assetsAssignments_end' => date('Y-m-d H:i:s'),
        ]);

        if (!$updated) {
            return [
                'success' => false,
                'asset' => $asset,
                'message' => 'Failed to end assignment',
                'action_taken' => null,
            ];
        }

        // Log scan
        $this->logScan($instanceId, $assetId, $asset['asset_definableFields_1'], 'checkin', $userId, $assignment['projects_id']);

        return [
            'success' => true,
            'asset' => $asset,
            'message' => 'Asset checked in successfully',
            'action_taken' => 'checkin',
        ];
    }

    /**
     * Log a scan event to history
     *
     * @param int $instanceId Instance ID
     * @param int $assetId Asset ID
     * @param string|null $rfidTag RFID tag
     * @param string $action Action performed
     * @param int $userId User ID
     * @param int|null $projectId Project ID
     * @return void
     */
    public function logScan(int $instanceId, int $assetId, ?string $rfidTag, string $action, int $userId, ?int $projectId = null): void
    {
        // Find or create barcode record for this RFID tag
        $barcode = null;
        if ($rfidTag) {
            $this->db->where('assetsBarcodes_code', $rfidTag);
            $this->db->where('assetTypes_id', null);
            $barcode = $this->db->getOne('assetsBarcodes');

            if (!$barcode) {
                $barcodeId = $this->db->insert('assetsBarcodes', [
                    'assetsBarcodes_code' => $rfidTag,
                    'assetsBarcodes_type' => 'rfid',
                    'assetTypes_id' => null,
                ]);
                $barcode = ['assetsBarcodes_id' => $barcodeId];
            }
        }

        // Log scan
        $this->db->insert('assetsBarcodesScans', [
            'assets_id' => $assetId,
            'assetsBarcodes_id' => $barcode['assetsBarcodes_id'] ?? null,
            'assetsBarcodesScans_timestamp' => date('Y-m-d H:i:s'),
            'assetsBarcodesScans_action' => $action,
            'users_userid' => $userId,
            'projects_id' => $projectId,
            'instances_id' => $instanceId,
        ]);
    }

    /**
     * Start a new inventory session
     *
     * @param int $instanceId Instance ID
     * @param int $userId User ID
     * @return int Inventory session ID
     */
    public function startInventory(int $instanceId, int $userId): int
    {
        $sessionId = $this->db->insert('rfidInventorySessions', [
            'instances_id' => $instanceId,
            'users_userid' => $userId,
            'session_started' => date('Y-m-d H:i:s'),
            'session_ended' => null,
        ]);

        return $sessionId;
    }

    /**
     * Process a tag scan during inventory
     *
     * @param int $sessionId Inventory session ID
     * @param string $rfidTag RFID tag
     * @return array Asset or stock instance info and found status
     */
    public function processInventoryScan(int $sessionId, string $rfidTag): array
    {
        // Get session details
        $this->db->where('rfidInventorySessions_id', $sessionId);
        $session = $this->db->getOne('rfidInventorySessions');

        if (!$session) {
            return [
                'success' => false,
                'message' => 'Session not found',
                'asset' => null,
            ];
        }

        // 1. Try to find asset by RFID tag (existing behavior)
        $asset = $this->findByTag($session['instances_id'], $rfidTag);

        if ($asset) {
            // Known asset - log as found
            $this->db->insert('rfidInventoryScans', [
                'rfidInventorySessions_id' => $sessionId,
                'assets_id' => $asset['assets_id'],
                'rfid_tag' => trim($rfidTag),
                'entity_type' => 'asset',
                'found' => 1,
                'scan_timestamp' => date('Y-m-d H:i:s'),
            ]);

            return [
                'success' => true,
                'message' => 'Asset recorded',
                'asset' => $asset,
            ];
        }

        // 2. If not found as asset AND stockService is set, try stock instance
        if ($this->stockService) {
            $stockInstance = $this->stockService->findByRfidTag($session['instances_id'], $rfidTag);

            if ($stockInstance) {
                // Known stock instance - log as found
                $this->db->insert('rfidInventoryScans', [
                    'rfidInventorySessions_id' => $sessionId,
                    'stock_instance_id' => $stockInstance['id'],
                    'rfid_tag' => trim($rfidTag),
                    'entity_type' => 'stock_instance',
                    'found' => 1,
                    'scan_timestamp' => date('Y-m-d H:i:s'),
                ]);

                return [
                    'success' => true,
                    'message' => 'Stock instance recorded',
                    'asset' => $stockInstance,
                ];
            }
        }

        // 3. Unknown tag - log as unknown
        $this->db->insert('rfidInventoryScans', [
            'rfidInventorySessions_id' => $sessionId,
            'assets_id' => null,
            'stock_instance_id' => null,
            'rfid_tag' => trim($rfidTag),
            'entity_type' => null,
            'found' => 0,
            'scan_timestamp' => date('Y-m-d H:i:s'),
        ]);

        return [
            'success' => false,
            'message' => 'Unknown tag',
            'asset' => null,
        ];
    }

    /**
     * Complete inventory session and get results
     *
     * @param int $sessionId Inventory session ID
     * @return array Found assets/stock instances, missing assets/instances, unknown tags
     */
    public function completeInventory(int $sessionId): array
    {
        // Get session details
        $this->db->where('rfidInventorySessions_id', $sessionId);
        $session = $this->db->getOne('rfidInventorySessions');

        if (!$session) {
            return [
                'success' => false,
                'message' => 'Session not found',
                'found_assets' => [],
                'found_stock_instances' => [],
                'missing_assets' => [],
                'missing_stock_instances' => [],
                'unknown_tags' => [],
            ];
        }

        // End session
        $this->db->where('rfidInventorySessions_id', $sessionId);
        $this->db->update('rfidInventorySessions', [
            'session_ended' => date('Y-m-d H:i:s'),
        ]);

        // Get all scanned items (assets and stock instances)
        $this->db->where('rfidInventorySessions_id', $sessionId);
        $this->db->where('found', 1);
        $scannedItems = $this->db->get('rfidInventoryScans') ?: [];

        // Separate found assets from found stock instances
        $foundAssets = array_values(array_filter($scannedItems, fn($s) => !empty($s['assets_id']) || $s['entity_type'] === 'asset'));
        $foundStockInstances = array_values(array_filter($scannedItems, fn($s) => !empty($s['stock_instance_id']) || $s['entity_type'] === 'stock_instance'));

        // Collect scanned asset IDs and stock instance IDs
        $scannedAssetIds = array_column($foundAssets, 'assets_id');
        $scannedAssetIds = array_filter($scannedAssetIds);

        $scannedStockInstanceIds = array_column($foundStockInstances, 'stock_instance_id');
        $scannedStockInstanceIds = array_filter($scannedStockInstanceIds);

        // Get unknown tags (not found as asset or stock instance)
        $this->db->where('rfidInventorySessions_id', $sessionId);
        $this->db->where('found', 0);
        $unknownScans = $this->db->get('rfidInventoryScans') ?: [];

        $unknownTags = array_column($unknownScans, 'rfid_tag');

        // Get all assets in instance with RFID tags (to find missing ones)
        $this->db->where('instances_id', $session['instances_id']);
        $this->db->where('asset_definableFields_1', null, '!=');
        $allAssetsWithRfid = $this->db->get('assets') ?: [];

        $allAssetIds = array_column($allAssetsWithRfid, 'assets_id');
        $missingAssetIds = array_diff($allAssetIds, $scannedAssetIds);

        // Get missing asset details
        $missingAssets = [];
        if (!empty($missingAssetIds)) {
            foreach ($missingAssetIds as $id) {
                $this->db->where('assets_id', $id);
                $asset = $this->db->getOne('assets');
                if ($asset) {
                    $missingAssets[] = $asset;
                }
            }
        }

        // Get all stock instances in session with RFID tags (to find missing ones)
        $missingStockInstances = [];
        if ($this->stockService) {
            $allStockInstancesWithRfid = $this->stockService->getAllByInstanceWithRfid($session['instances_id']) ?: [];
            $allStockInstanceIds = array_column($allStockInstancesWithRfid, 'id');
            $missingStockInstanceIds = array_diff($allStockInstanceIds, $scannedStockInstanceIds);

            if (!empty($missingStockInstanceIds)) {
                foreach ($missingStockInstanceIds as $id) {
                    $stockInst = $this->stockService->getById($id);
                    if ($stockInst) {
                        $missingStockInstances[] = $stockInst;
                    }
                }
            }
        }

        return [
            'success' => true,
            'message' => 'Inventory completed',
            'found_assets' => $foundAssets,
            'found_stock_instances' => $foundStockInstances,
            'missing_assets' => $missingAssets,
            'missing_stock_instances' => $missingStockInstances,
            'unknown_tags' => $unknownTags,
        ];
    }
}
