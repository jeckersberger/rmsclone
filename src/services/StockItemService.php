<?php
/**
 * Stock Item Service
 *
 * Manages "Artikel" (stock items) and their individual instances.
 * Stock items are bulk/consumable items (cables, adapters, etc.) that are
 * tracked individually via RFID but don't need full asset profiles.
 *
 * Two-tier model:
 * - stock_items: Article types (e.g. "HDMI-Kabel 3m")
 * - stock_instances: Individual physical items with unique RFID tags
 */
class StockItemService
{
    private $db;
    private ?TagFormatService $tagFormatService = null;
    private ?CrossInstanceLookupService $crossLookup = null;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function setTagFormatService(TagFormatService $svc): void
    {
        $this->tagFormatService = $svc;
    }

    public function setCrossLookupService(CrossInstanceLookupService $service): void
    {
        $this->crossLookup = $service;
    }

    // ═══════════════════════════════════════════
    // Stock Item Type Management (Artikeltypen)
    // ═══════════════════════════════════════════

    /**
     * Create a new stock item type
     */
    public function createItem(int $instanceId, array $data): ?int
    {
        $now = date('Y-m-d H:i:s');

        $id = $this->db->insert('stock_items', [
            'instances_id'  => $instanceId,
            'name'          => $data['name'] ?? '',
            'description'   => $data['description'] ?? null,
            'category'      => $data['category'] ?? '',
            'sku'           => $data['sku'] ?? '',
            'unit_value'    => $data['unit_value'] ?? 0,
            'day_rate'      => $data['day_rate'] ?? 0,
            'week_rate'     => $data['week_rate'] ?? 0,
            'min_stock'     => $data['min_stock'] ?? 0,
            'image_url'     => $data['image_url'] ?? null,
            'notes'         => $data['notes'] ?? null,
            'active'        => 1,
            'deleted'       => 0,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        return $id ?: null;
    }

    /**
     * Update a stock item type
     */
    public function updateItem(int $itemId, array $data): bool
    {
        $allowed = ['name', 'description', 'category', 'sku', 'unit_value',
                     'day_rate', 'week_rate', 'min_stock', 'image_url', 'notes', 'active'];
        $update = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }
        $update['updated_at'] = date('Y-m-d H:i:s');

        $this->db->where('id', $itemId);
        return (bool) $this->db->update('stock_items', $update);
    }

    /**
     * Soft-delete a stock item type
     */
    public function deleteItem(int $itemId): bool
    {
        $this->db->where('id', $itemId);
        return (bool) $this->db->update('stock_items', [
            'deleted' => 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get a single stock item type with instance counts
     */
    public function getItem(int $itemId): ?array
    {
        $this->db->where('id', $itemId);
        $this->db->where('deleted', 0);
        $item = $this->db->getOne('stock_items');

        if (!$item) {
            return null;
        }

        // Add instance counts
        $item['counts'] = $this->getInstanceCounts($itemId);

        return $item;
    }

    /**
     * List all stock item types for an instance
     */
    public function listItems(int $instanceId, ?string $category = null, bool $includeInactive = false): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('deleted', 0);

        if ($category) {
            $this->db->where('category', $category);
        }
        if (!$includeInactive) {
            $this->db->where('active', 1);
        }

        $this->db->orderBy('category', 'ASC');
        $this->db->orderBy('name', 'ASC');
        $items = $this->db->get('stock_items') ?: [];

        // Add instance counts for each
        foreach ($items as &$item) {
            $item['counts'] = $this->getInstanceCounts($item['id']);
        }

        return $items;
    }

    /**
     * Get distinct categories for an instance
     */
    public function getCategories(int $instanceId): array
    {
        $sql = "SELECT DISTINCT category
                FROM stock_items
                WHERE instances_id = ? AND deleted = 0
                ORDER BY category ASC";

        $rows = $this->db->rawQuery($sql, [$instanceId]) ?: [];
        return array_column($rows, 'category');
    }

    /**
     * Get instance counts by status for a stock item
     */
    private function getInstanceCounts(int $itemId): array
    {
        $this->db->where('stock_item_id', $itemId);
        $this->db->where('deleted', 0);
        $total = $this->db->getValue('stock_instances', 'count(*)');

        $this->db->where('stock_item_id', $itemId);
        $this->db->where('deleted', 0);
        $this->db->where('status', 'available');
        $available = $this->db->getValue('stock_instances', 'count(*)');

        $this->db->where('stock_item_id', $itemId);
        $this->db->where('deleted', 0);
        $this->db->where('status', 'checked_out');
        $checkedOut = $this->db->getValue('stock_instances', 'count(*)');

        $this->db->where('stock_item_id', $itemId);
        $this->db->where('deleted', 0);
        $this->db->where('status', 'damaged');
        $damaged = $this->db->getValue('stock_instances', 'count(*)');

        return [
            'total'       => (int) $total,
            'available'   => (int) $available,
            'checked_out' => (int) $checkedOut,
            'damaged'     => (int) $damaged,
        ];
    }

    // ═══════════════════════════════════════════
    // Stock Instance Management (Einzelexemplare)
    // ═══════════════════════════════════════════

    /**
     * Create individual instances for a stock item
     *
     * @param int $itemId Stock item type ID
     * @param int $instanceId Tenant instance ID
     * @param int $quantity How many instances to create
     * @return array Created instance IDs
     */
    public function createInstances(int $itemId, int $instanceId, int $quantity): array
    {
        $now = date('Y-m-d H:i:s');
        $createdIds = [];

        // Get next instance number
        $this->db->where('stock_item_id', $itemId);
        $this->db->orderBy('instance_number', 'DESC');
        $last = $this->db->getOne('stock_instances', null, ['instance_number']);
        $nextNumber = $last ? $last['instance_number'] + 1 : 1;

        for ($i = 0; $i < $quantity; $i++) {
            // rfid_tag kept for backward compat (old barcode labels).
            // rfid_tid is set later via TID pairing (scan tag → assign to instance).
            $id = $this->db->insert('stock_instances', [
                'stock_item_id'   => $itemId,
                'instances_id'    => $instanceId,
                'instance_number' => $nextNumber + $i,
                'rfid_tag'        => null,
                'rfid_tid'        => null,
                'status'          => 'available',
                'condition'       => 'good',
                'location'        => '',
                'notes'           => null,
                'last_scan_at'    => null,
                'created_at'      => $now,
                'updated_at'      => $now,
                'deleted'         => 0,
            ]);

            if ($id) {
                $createdIds[] = $id;
            }
        }

        return $createdIds;
    }

    /**
     * Get the next available EPC number for stock instances.
     * Supports both old format (RMS-I-000001) and new format (RMS-a3f7b2c1-I-000001).
     */
    private function getNextEpcNumber(int $instanceId): int
    {
        $maxNumber = 0;

        // Search old format: RMS-I-000001
        $this->db->where('instances_id', $instanceId);
        $this->db->where('rfid_tag', 'RMS-I-%', 'LIKE');
        $this->db->orderBy('rfid_tag', 'DESC');
        $lastOld = $this->db->getOne('stock_instances', null, ['rfid_tag']);

        if ($lastOld && preg_match('/RMS-I-(\d+)/', $lastOld['rfid_tag'], $m)) {
            $maxNumber = max($maxNumber, (int)$m[1]);
        }

        // Search new format: RMS-{8hex}-I-000001 (e.g. RMS-a3f7b2c1-I-000042)
        $this->db->where('instances_id', $instanceId);
        $this->db->where('rfid_tag', 'RMS-________-I-%', 'LIKE');
        $this->db->orderBy('rfid_tag', 'DESC');
        $lastNew = $this->db->getOne('stock_instances', null, ['rfid_tag']);

        if ($lastNew && preg_match('/RMS-[a-f0-9]{8}-I-(\d+)/i', $lastNew['rfid_tag'], $m)) {
            $maxNumber = max($maxNumber, (int)$m[1]);
        }

        // Also check the simple instance_number max as ultimate fallback
        $this->db->where('instances_id', $instanceId);
        $highestNumber = $this->db->getValue('stock_instances', 'MAX(instance_number)');
        if ($highestNumber) {
            $maxNumber = max($maxNumber, (int)$highestNumber);
        }

        return $maxNumber + 1;
    }

    /**
     * Find a stock instance by RFID tag
     */
    public function findByRfidTag(int $instanceId, string $rfidTag): ?array
    {
        $rfidTag = trim($rfidTag);

        $this->db->where('rfid_tag', $rfidTag);
        $this->db->where('instances_id', $instanceId);
        $this->db->where('deleted', 0);
        $instance = $this->db->getOne('stock_instances');

        if (!$instance) {
            return null;
        }

        // Get parent stock item info
        $this->db->where('id', $instance['stock_item_id']);
        $item = $this->db->getOne('stock_items', null, ['name', 'category', 'sku', 'description']);

        $instance['item_name'] = $item ? $item['name'] : 'Unknown';
        $instance['item_category'] = $item ? $item['category'] : '';
        $instance['item_sku'] = $item ? $item['sku'] : '';
        $instance['item_description'] = $item ? $item['description'] : '';
        $instance['entity_type'] = 'stock_instance';

        // Check current assignment
        $this->db->where('stock_instance_id', $instance['id']);
        $this->db->where('assignment_end', null);
        $this->db->orderBy('id', 'DESC');
        $assignment = $this->db->getOne('stock_assignments');

        $instance['current_assignment'] = $assignment ?: null;

        return $instance;
    }

    /**
     * Get a single stock instance by ID
     */
    public function getInstance(int $instanceDbId): ?array
    {
        $this->db->where('id', $instanceDbId);
        $this->db->where('deleted', 0);
        $instance = $this->db->getOne('stock_instances');

        if (!$instance) {
            return null;
        }

        // Get parent stock item
        $this->db->where('id', $instance['stock_item_id']);
        $item = $this->db->getOne('stock_items', null, ['name', 'category', 'sku']);
        $instance['item_name'] = $item ? $item['name'] : 'Unknown';
        $instance['item_category'] = $item ? $item['category'] : '';
        $instance['item_sku'] = $item ? $item['sku'] : '';

        return $instance;
    }

    /**
     * List instances for a stock item
     */
    public function listInstances(int $itemId, ?string $status = null): array
    {
        $this->db->where('stock_item_id', $itemId);
        $this->db->where('deleted', 0);

        if ($status) {
            $this->db->where('status', $status);
        }

        $this->db->orderBy('instance_number', 'ASC');
        return $this->db->get('stock_instances') ?: [];
    }

    /**
     * Update a stock instance
     */
    public function updateInstance(int $instanceDbId, array $data): bool
    {
        $allowed = ['status', 'condition', 'location', 'notes', 'rfid_tag'];
        $update = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }
        $update['updated_at'] = date('Y-m-d H:i:s');

        $this->db->where('id', $instanceDbId);
        return (bool) $this->db->update('stock_instances', $update);
    }

    /**
     * Assign RFID tag to a stock instance
     */
    public function assignRfidTag(int $instanceDbId, string $rfidTag, int $instanceId): bool
    {
        $rfidTag = trim($rfidTag);

        // Check uniqueness across stock_instances
        $this->db->where('rfid_tag', $rfidTag);
        $this->db->where('id', $instanceDbId, '!=');
        $existing = $this->db->getOne('stock_instances');
        if ($existing) {
            return false;
        }

        // Also check assets table
        $this->db->where('asset_definableFields_1', $rfidTag);
        $existingAsset = $this->db->getOne('assets');
        if ($existingAsset) {
            return false;
        }

        $this->db->where('id', $instanceDbId);
        return (bool) $this->db->update('stock_instances', [
            'rfid_tag' => $rfidTag,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    // ═══════════════════════════════════════════
    // Checkout / Checkin for Stock Instances
    // ═══════════════════════════════════════════

    /**
     * Check out a stock instance to a project
     */
    public function checkOut(int $instanceDbId, int $instanceId, int $projectId, int $userId): array
    {
        $inst = $this->getInstance($instanceDbId);

        if (!$inst) {
            return ['success' => false, 'message' => 'Instanz nicht gefunden'];
        }

        if ($inst['status'] === 'checked_out') {
            return ['success' => false, 'message' => 'Bereits ausgeliehen', 'instance' => $inst];
        }

        // Create assignment
        $assignId = $this->db->insert('stock_assignments', [
            'stock_instance_id' => $instanceDbId,
            'instances_id'      => $instanceId,
            'projects_id'       => $projectId,
            'assigned_by'       => $userId,
            'assignment_start'  => date('Y-m-d H:i:s'),
            'assignment_end'    => null,
            'notes'             => '',
        ]);

        if (!$assignId) {
            return ['success' => false, 'message' => 'Fehler beim Erstellen der Zuweisung'];
        }

        // Update instance status
        $this->db->where('id', $instanceDbId);
        $this->db->update('stock_instances', [
            'status' => 'checked_out',
            'last_scan_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return ['success' => true, 'message' => 'Ausgeliehen', 'instance' => $inst];
    }

    /**
     * Check in a stock instance
     */
    public function checkIn(int $instanceDbId, int $instanceId, int $userId): array
    {
        $inst = $this->getInstance($instanceDbId);

        if (!$inst) {
            return ['success' => false, 'message' => 'Instanz nicht gefunden'];
        }

        if ($inst['status'] !== 'checked_out') {
            return ['success' => false, 'message' => 'Nicht ausgeliehen', 'instance' => $inst];
        }

        // End active assignment
        $this->db->where('stock_instance_id', $instanceDbId);
        $this->db->where('assignment_end', null);
        $this->db->update('stock_assignments', [
            'assignment_end' => date('Y-m-d H:i:s'),
        ]);

        // Update instance status
        $this->db->where('id', $instanceDbId);
        $this->db->update('stock_instances', [
            'status' => 'available',
            'last_scan_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return ['success' => true, 'message' => 'Zurückgegeben', 'instance' => $inst];
    }

    // ═══════════════════════════════════════════
    // Box Scan (Kisten-Scan)
    // ═══════════════════════════════════════════

    /**
     * Process a batch of RFID tags (box scan)
     * Returns grouped results: assets, stock instances, unknown
     *
     * @param int $instanceId Tenant instance ID
     * @param array $rfidTags Array of scanned RFID EPCs
     * @return array Grouped and aggregated scan result
     */
    public function processBoxScan(int $instanceId, array $rfidTags): array
    {
        $assets = [];
        $stockGroups = [];  // Grouped by stock_item_id
        $unknown = [];
        $partner_items = [];
        $seen = [];  // Deduplicate

        foreach ($rfidTags as $tag) {
            $tag = trim($tag);
            if (empty($tag) || isset($seen[$tag])) {
                continue;
            }
            $seen[$tag] = true;

            // Try stock instance first (RMS-I-xxxxxx pattern)
            $stockInst = $this->findByRfidTag($instanceId, $tag);
            if ($stockInst) {
                $itemId = $stockInst['stock_item_id'];
                if (!isset($stockGroups[$itemId])) {
                    $stockGroups[$itemId] = [
                        'item_name'     => $stockInst['item_name'],
                        'item_category' => $stockInst['item_category'],
                        'item_sku'      => $stockInst['item_sku'],
                        'entity_type'   => 'stock',
                        'count'         => 0,
                        'instances'     => [],
                    ];
                }
                $stockGroups[$itemId]['count']++;
                $stockGroups[$itemId]['instances'][] = [
                    'id'              => $stockInst['id'],
                    'instance_number' => $stockInst['instance_number'],
                    'rfid_tag'        => $tag,
                    'status'          => $stockInst['status'],
                    'condition'       => $stockInst['condition'],
                ];
                continue;
            }

            // Try asset (RMS-A-xxxxxx or any other tag in asset_definableFields_1)
            $this->db->where('asset_definableFields_1', $tag);
            $this->db->where('instances_id', $instanceId);
            $asset = $this->db->getOne('assets', null, [
                'assets_id', 'assets_name', 'assetTypes_id', 'asset_definableFields_1',
            ]);

            if ($asset) {
                // Get type name
                $this->db->where('assetTypes_id', $asset['assetTypes_id']);
                $type = $this->db->getOne('assetTypes', null, ['assetTypes_name']);

                // Get current assignment status
                $this->db->where('assets_id', $asset['assets_id']);
                $this->db->where('assetsAssignments_end', null);
                $assignment = $this->db->getOne('assetsAssignments', null, ['projects_id']);

                $assets[] = [
                    'assets_id'   => $asset['assets_id'],
                    'assets_name' => $asset['assets_name'],
                    'type_name'   => $type ? $type['assetTypes_name'] : 'Unknown',
                    'rfid_tag'    => $tag,
                    'entity_type' => 'asset',
                    'status'      => $assignment ? 'checked_out' : 'available',
                    'project_id'  => $assignment ? $assignment['projects_id'] : null,
                ];
                continue;
            }

            // Try cross-instance lookup
            if ($this->crossLookup) {
                $crossResult = $this->crossLookup->lookupTag($tag);
                if ($crossResult) {
                    $partner_items[] = [
                        'rfid_tag' => $tag,
                        'entity_type' => $crossResult['entity_type'],
                        'display_name' => $crossResult['entity']['display_name'] ?? 'Unbekannt',
                        'owner_name' => $crossResult['owner_instance_name'],
                        'source' => $crossResult['source'],
                    ];
                    continue;
                }
            }

            // Unknown tag
            $unknown[] = $tag;
        }

        // Sort stock groups by category then name
        uasort($stockGroups, function ($a, $b) {
            $cmp = strcmp($a['item_category'], $b['item_category']);
            return $cmp !== 0 ? $cmp : strcmp($a['item_name'], $b['item_name']);
        });

        return [
            'assets'        => $assets,
            'stock'         => array_values($stockGroups),
            'partner_items' => $partner_items,
            'unknown'       => $unknown,
            'summary'       => [
                'total_scanned'   => count($seen),
                'total_assets'    => count($assets),
                'total_stock'     => array_sum(array_column(array_values($stockGroups), 'count')),
                'total_partner'   => count($partner_items),
                'total_unknown'   => count($unknown),
                'stock_types'     => count($stockGroups),
            ],
        ];
    }

    /**
     * Save a box scan session for history
     */
    public function saveBoxScanSession(int $instanceId, int $userId, string $name, array $scanResult, ?int $projectId = null, string $mode = 'count'): int
    {
        return $this->db->insert('box_scan_sessions', [
            'instances_id'   => $instanceId,
            'users_userid'   => $userId,
            'name'           => $name,
            'projects_id'    => $projectId,
            'scan_mode'      => $mode,
            'total_scanned'  => $scanResult['summary']['total_scanned'],
            'total_assets'   => $scanResult['summary']['total_assets'],
            'total_stock'    => $scanResult['summary']['total_stock'],
            'total_unknown'  => $scanResult['summary']['total_unknown'],
            'scan_data'      => json_encode($scanResult),
            'session_started' => date('Y-m-d H:i:s'),
            'session_ended'  => null,
        ]);
    }

    /**
     * Execute box scan with checkout/checkin action
     */
    public function processBoxScanWithAction(int $instanceId, array $rfidTags, string $action, int $userId, ?int $projectId = null): array
    {
        $scanResult = $this->processBoxScan($instanceId, $rfidTags);
        $actionResults = ['successes' => 0, 'errors' => 0, 'details' => []];

        if ($action === 'checkout' && $projectId) {
            // Checkout all assets
            foreach ($scanResult['assets'] as $asset) {
                if ($asset['status'] === 'available') {
                    // Use RfidService pattern for assets
                    $actionResults['successes']++;
                } else {
                    $actionResults['errors']++;
                    $actionResults['details'][] = $asset['assets_name'] . ': bereits ausgeliehen';
                }
            }

            // Checkout all stock instances
            foreach ($scanResult['stock'] as $group) {
                foreach ($group['instances'] as $inst) {
                    if ($inst['status'] === 'available') {
                        $result = $this->checkOut($inst['id'], $instanceId, $projectId, $userId);
                        if ($result['success']) {
                            $actionResults['successes']++;
                        } else {
                            $actionResults['errors']++;
                            $actionResults['details'][] = $group['item_name'] . ' #' . $inst['instance_number'] . ': ' . $result['message'];
                        }
                    }
                }
            }
        } elseif ($action === 'checkin') {
            // Checkin all stock instances
            foreach ($scanResult['stock'] as $group) {
                foreach ($group['instances'] as $inst) {
                    if ($inst['status'] === 'checked_out') {
                        $result = $this->checkIn($inst['id'], $instanceId, $userId);
                        if ($result['success']) {
                            $actionResults['successes']++;
                        } else {
                            $actionResults['errors']++;
                        }
                    }
                }
            }
        }

        $scanResult['action_results'] = $actionResults;
        return $scanResult;
    }

    // ═══════════════════════════════════════════
    // Statistics & Warnings
    // ═══════════════════════════════════════════

    /**
     * Get low stock warnings
     */
    public function getLowStockWarnings(int $instanceId): array
    {
        $items = $this->listItems($instanceId);
        $warnings = [];

        foreach ($items as $item) {
            if ($item['min_stock'] > 0 && $item['counts']['available'] <= $item['min_stock']) {
                $warnings[] = [
                    'item_id'    => $item['id'],
                    'name'       => $item['name'],
                    'category'   => $item['category'],
                    'available'  => $item['counts']['available'],
                    'min_stock'  => $item['min_stock'],
                    'total'      => $item['counts']['total'],
                    'shortage'   => $item['min_stock'] - $item['counts']['available'],
                ];
            }
        }

        return $warnings;
    }

    /**
     * Get stock overview statistics
     */
    public function getStockOverview(int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('deleted', 0);
        $totalTypes = $this->db->getValue('stock_items', 'count(*)');

        $this->db->where('instances_id', $instanceId);
        $this->db->where('deleted', 0);
        $totalInstances = $this->db->getValue('stock_instances', 'count(*)');

        $this->db->where('instances_id', $instanceId);
        $this->db->where('deleted', 0);
        $this->db->where('status', 'available');
        $available = $this->db->getValue('stock_instances', 'count(*)');

        $this->db->where('instances_id', $instanceId);
        $this->db->where('deleted', 0);
        $this->db->where('status', 'checked_out');
        $checkedOut = $this->db->getValue('stock_instances', 'count(*)');

        $this->db->where('instances_id', $instanceId);
        $this->db->where('deleted', 0);
        $this->db->where('status', 'damaged');
        $damaged = $this->db->getValue('stock_instances', 'count(*)');

        $this->db->where('instances_id', $instanceId);
        $this->db->where('deleted', 0);
        $this->db->where('rfid_tag', null, 'IS NOT');
        $withRfid = $this->db->getValue('stock_instances', 'count(*)');

        return [
            'total_types'      => (int) $totalTypes,
            'total_instances'  => (int) $totalInstances,
            'available'        => (int) $available,
            'checked_out'      => (int) $checkedOut,
            'damaged'          => (int) $damaged,
            'with_rfid'        => (int) $withRfid,
            'without_rfid'     => (int) $totalInstances - (int) $withRfid,
        ];
    }
}
