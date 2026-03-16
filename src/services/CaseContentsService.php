<?php
/**
 * Case Contents Service — Manages case/container content definitions and verification
 *
 * Type-based matching: Items in cases are interchangeable by type.
 * For example, any Moving Head can go in a case that expects "Moving Head type".
 * Quantity is tracked per type, not per specific item instance.
 */
class CaseContentsService
{
    private $db;
    private int $instanceId;

    public function __construct($db, int $instanceId)
    {
        $this->db = $db;
        $this->instanceId = $instanceId;
    }

    /**
     * Get case contents definition with type names
     * Returns: [{id, content_type, content_type_id, type_name, manufacturer, quantity, notes, sort_order}]
     */
    public function getCaseContents(int $caseAssetId): array
    {
        // Get asset_type entries
        $this->db->where('cc.case_asset_id', $caseAssetId);
        $this->db->where('cc.instances_id', $this->instanceId);
        $this->db->where('cc.content_type', 'asset_type');
        $this->db->join('assetTypes at', 'at.assetTypes_id = cc.content_type_id', 'LEFT');
        $this->db->join('manufacturers m', 'm.manufacturers_id = at.manufacturers_id', 'LEFT');
        $this->db->orderBy('cc.sort_order', 'ASC');

        $assetTypeContents = $this->db->get('case_contents cc', null, [
            'cc.id',
            'cc.case_asset_id',
            'cc.content_type',
            'cc.content_type_id',
            'at.assetTypes_name as type_name',
            'm.manufacturers_name as manufacturer',
            'cc.quantity',
            'cc.notes',
            'cc.sort_order'
        ]);

        // Get stock_item entries
        $this->db->where('cc.case_asset_id', $caseAssetId);
        $this->db->where('cc.instances_id', $this->instanceId);
        $this->db->where('cc.content_type', 'stock_item');
        $this->db->join('stock_items si', 'si.id = cc.content_type_id', 'LEFT');
        $this->db->orderBy('cc.sort_order', 'ASC');

        $stockItemContents = $this->db->get('case_contents cc', null, [
            'cc.id',
            'cc.case_asset_id',
            'cc.content_type',
            'cc.content_type_id',
            'si.name as type_name',
            'NULL as manufacturer',
            'cc.quantity',
            'cc.notes',
            'cc.sort_order'
        ]);

        // Merge and sort by sort_order
        $all = [];
        if (is_array($assetTypeContents)) {
            $all = array_merge($all, $assetTypeContents);
        }
        if (is_array($stockItemContents)) {
            $all = array_merge($all, $stockItemContents);
        }

        usort($all, function($a, $b) {
            return $a['sort_order'] <=> $b['sort_order'];
        });

        return $all;
    }

    /**
     * Add a type+quantity to a case definition
     * contentType: 'asset_type' or 'stock_item'
     * contentTypeId: assetTypes_id or stock_items.id
     */
    public function addContent(int $caseAssetId, string $contentType, int $contentTypeId, int $quantity = 1, ?string $notes = null): int
    {
        // Validate content type
        if (!in_array($contentType, ['asset_type', 'stock_item'])) {
            throw new Exception('Invalid content_type: ' . $contentType);
        }

        // Validate the type exists
        if ($contentType === 'asset_type') {
            $this->db->where('assetTypes_id', $contentTypeId);
            $this->db->where('instances_id', $this->instanceId);
            $type = $this->db->getOne('assetTypes', ['assetTypes_id']);
            if (!$type) {
                throw new Exception('Asset type not found');
            }
        } elseif ($contentType === 'stock_item') {
            $this->db->where('id', $contentTypeId);
            $this->db->where('instances_id', $this->instanceId);
            $type = $this->db->getOne('stock_items', ['id']);
            if (!$type) {
                throw new Exception('Stock item not found');
            }
        }

        // Check for duplicate (same case + type + typeId)
        $this->db->where('case_asset_id', $caseAssetId);
        $this->db->where('content_type', $contentType);
        $this->db->where('content_type_id', $contentTypeId);
        $this->db->where('instances_id', $this->instanceId);

        $existing = $this->db->getOne('case_contents', ['id', 'quantity']);

        if ($existing) {
            // Already exists, just update quantity
            $newQuantity = $existing['quantity'] + $quantity;
            $this->db->where('id', $existing['id']);
            $this->db->update('case_contents', ['quantity' => $newQuantity]);
            return $existing['id'];
        }

        // Get next sort order
        $this->db->where('case_asset_id', $caseAssetId);
        $this->db->where('instances_id', $this->instanceId);
        $this->db->orderBy('sort_order', 'DESC');

        $lastItem = $this->db->getOne('case_contents', ['sort_order']);
        $nextSort = ($lastItem && isset($lastItem['sort_order'])) ? $lastItem['sort_order'] + 1 : 1;

        $data = [
            'case_asset_id' => $caseAssetId,
            'content_type' => $contentType,
            'content_type_id' => $contentTypeId,
            'quantity' => $quantity,
            'notes' => $notes,
            'sort_order' => $nextSort,
            'instances_id' => $this->instanceId,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $id = $this->db->insert('case_contents', $data);

        if (!$id) {
            throw new Exception('Failed to add content to case');
        }

        return $id;
    }

    /**
     * Remove an item from a case definition
     */
    public function removeContent(int $contentId): bool
    {
        $this->db->where('id', $contentId);
        $this->db->where('instances_id', $this->instanceId);

        $result = $this->db->delete('case_contents');

        return $result > 0;
    }

    /**
     * Update a case content entry
     */
    public function updateContent(int $contentId, array $data): bool
    {
        // Only allow updating specific fields
        $allowed = ['quantity', 'notes', 'sort_order'];
        $updateData = [];

        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        if (empty($updateData)) {
            return true; // Nothing to update
        }

        $this->db->where('id', $contentId);
        $this->db->where('instances_id', $this->instanceId);

        $result = $this->db->update('case_contents', $updateData);

        return $result > 0;
    }

    /**
     * Mark an asset as a case/container
     */
    public function markAsCase(int $assetId): bool
    {
        $this->db->where('assets_id', $assetId);
        $this->db->where('instances_id', $this->instanceId);

        $data = [
            'is_case' => 1,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $result = $this->db->update('assets', $data);

        return $result > 0;
    }

    /**
     * Unmark an asset as a case (only if no contents defined)
     */
    public function unmarkAsCase(int $assetId): bool
    {
        // Check if any contents are defined
        $this->db->where('case_asset_id', $assetId);
        $this->db->where('instances_id', $this->instanceId);

        $count = $this->db->getValue('case_contents', 'COUNT(*)');

        if ($count > 0) {
            throw new Exception('Cannot unmark as case - contents are still defined for this case');
        }

        $this->db->where('assets_id', $assetId);
        $this->db->where('instances_id', $this->instanceId);

        $data = [
            'is_case' => 0,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $result = $this->db->update('assets', $data);

        return $result > 0;
    }

    /**
     * List all cases (assets where is_case = 1)
     */
    public function listCases(): array
    {
        $this->db->where('a.is_case', 1);
        $this->db->where('a.instances_id', $this->instanceId);

        // Count contents for each case
        $this->db->leftJoin(
            'case_contents cc',
            'a.assets_id = cc.case_asset_id AND cc.instances_id = a.instances_id',
            'cc'
        );

        $this->db->orderBy('a.asset_name', 'ASC');
        $this->db->groupBy('a.assets_id');

        $results = $this->db->get('assets a', null, [
            'a.assets_id',
            'a.asset_name',
            'a.asset_code',
            'COUNT(cc.id) as content_count'
        ]);

        return is_array($results) ? $results : [];
    }

    /**
     * CORE VERIFICATION METHOD
     *
     * Verify case contents against scanned items.
     *
     * $scannedItems = array of items found in the case:
     *   For assets: ['type' => 'asset', 'id' => assets_id]
     *   For stock instances: ['type' => 'stock_instance', 'id' => stock_instances.id]
     *
     * Process:
     * 1. Resolve each scanned asset to its assetTypes_id
     * 2. Resolve each scanned stock_instance to its stock_item_id
     * 3. Count by type: {'asset_type:5' => 2, 'stock_item:3' => 4}
     * 4. Compare against case definition
     * 5. Return: items (expected vs found), missing, extras
     */
    public function verifyCaseContents(int $caseAssetId, array $scannedItems): array
    {
        // Get definition
        $definition = $this->getCaseContents($caseAssetId);

        // Resolve scanned items to types
        $scannedByType = []; // 'asset_type:X' => count, 'stock_item:Y' => count
        foreach ($scannedItems as $item) {
            if ($item['type'] === 'asset') {
                // Look up assetTypes_id from assets table
                $this->db->where('assets_id', $item['id']);
                $asset = $this->db->getOne('assets', ['assetTypes_id']);
                if ($asset) {
                    $key = 'asset_type:' . $asset['assetTypes_id'];
                    $scannedByType[$key] = ($scannedByType[$key] ?? 0) + 1;
                }
            } elseif ($item['type'] === 'stock_instance') {
                $this->db->where('id', $item['id']);
                $inst = $this->db->getOne('stock_instances', ['stock_item_id']);
                if ($inst) {
                    $key = 'stock_item:' . $inst['stock_item_id'];
                    $scannedByType[$key] = ($scannedByType[$key] ?? 0) + 1;
                }
            }
        }

        // Compare with definition
        $results = [];
        $allComplete = true;
        $missing = [];
        $extras = [];
        $foundTypes = [];

        foreach ($definition as $req) {
            $key = $req['content_type'] . ':' . $req['content_type_id'];
            $foundCount = $scannedByType[$key] ?? 0;
            $foundTypes[] = $key;

            $results[] = [
                'type_name' => $req['type_name'],
                'manufacturer' => $req['manufacturer'],
                'content_type' => $req['content_type'],
                'content_type_id' => $req['content_type_id'],
                'expected' => $req['quantity'],
                'found' => $foundCount,
                'complete' => $foundCount >= $req['quantity'],
            ];

            if ($foundCount < $req['quantity']) {
                $allComplete = false;
                $missing[] = [
                    'type_name' => $req['type_name'],
                    'manufacturer' => $req['manufacturer'],
                    'expected' => $req['quantity'],
                    'found' => $foundCount,
                    'short' => $req['quantity'] - $foundCount,
                ];
            }
        }

        // Find extras (scanned types not in definition)
        foreach ($scannedByType as $key => $count) {
            if (!in_array($key, $foundTypes)) {
                // Resolve name
                $parts = explode(':', $key);
                $typeName = $this->resolveTypeName($parts[0], (int)$parts[1]);
                $extras[] = [
                    'type_name' => $typeName,
                    'content_type' => $parts[0],
                    'content_type_id' => (int)$parts[1],
                    'count' => $count,
                ];
            }
        }

        return [
            'all_complete' => $allComplete,
            'items' => $results,
            'missing' => $missing,
            'extras' => $extras,
            'total_expected' => array_sum(array_column($definition, 'quantity')),
            'total_found' => array_sum($scannedByType),
        ];
    }

    /**
     * Resolve type name from content_type and content_type_id
     */
    private function resolveTypeName(string $contentType, int $typeId): string
    {
        if ($contentType === 'asset_type') {
            $this->db->where('assetTypes_id', $typeId);
            $t = $this->db->getOne('assetTypes', ['assetTypes_name']);
            return $t ? $t['assetTypes_name'] : 'Unbekannt';
        } else {
            $this->db->where('id', $typeId);
            $t = $this->db->getOne('stock_items', ['name']);
            return $t ? $t['name'] : 'Unbekannt';
        }
    }

    /**
     * Log a case content check (during checkin/checkout)
     */
    public function logCheck(int $caseAssetId, int $projectId, string $checkType, int $userId, array $result): int
    {
        $data = [
            'case_asset_id' => $caseAssetId,
            'project_id' => $projectId,
            'check_type' => $checkType,
            'checked_by' => $userId,
            'instances_id' => $this->instanceId,
            'missing_items' => !empty($result['missing']) ? json_encode($result['missing']) : null,
            'extra_items' => !empty($result['extras']) ? json_encode($result['extras']) : null,
            'all_complete' => (int)$result['all_complete'],
            'created_at' => date('Y-m-d H:i:s'),
            'acknowledged' => 0
        ];

        $id = $this->db->insert('case_content_checks', $data);

        if (!$id) {
            throw new Exception('Failed to log case content check');
        }

        return $id;
    }

    /**
     * Acknowledge a discrepancy (user confirms they're aware items are missing)
     */
    public function acknowledgeDiscrepancy(int $checkId, int $userId): bool
    {
        $this->db->where('id', $checkId);
        $this->db->where('instances_id', $this->instanceId);

        $data = [
            'acknowledged' => 1,
            'acknowledged_by' => $userId
        ];

        $result = $this->db->update('case_content_checks', $data);

        return $result > 0;
    }

    /**
     * Get check history for a case
     */
    public function getCheckHistory(int $caseAssetId, int $limit = 20): array
    {
        $this->db->where('case_asset_id', $caseAssetId);
        $this->db->where('instances_id', $this->instanceId);
        $this->db->orderBy('created_at', 'DESC');

        $results = $this->db->get('case_content_checks', $limit, [
            'id',
            'case_asset_id',
            'project_id',
            'check_type',
            'checked_by',
            'missing_items',
            'extra_items',
            'all_complete',
            'acknowledged',
            'acknowledged_by',
            'created_at'
        ]);

        // Decode JSON fields
        if (is_array($results)) {
            foreach ($results as &$record) {
                $record['missing_items'] = $record['missing_items'] ? json_decode($record['missing_items'], true) : [];
                $record['extra_items'] = $record['extra_items'] ? json_decode($record['extra_items'], true) : [];
            }
        }

        return is_array($results) ? $results : [];
    }

    /**
     * Copy contents from one case to another (for identical cases)
     */
    public function copyContents(int $fromCaseId, int $toCaseId): int
    {
        $sourceContents = $this->getCaseContents($fromCaseId);

        if (empty($sourceContents)) {
            return 0;
        }

        $count = 0;

        foreach ($sourceContents as $item) {
            try {
                $this->addContent(
                    $toCaseId,
                    $item['content_type'],
                    $item['content_type_id'],
                    $item['quantity'],
                    $item['notes']
                );
                $count++;
            } catch (Exception $e) {
                // Item might already exist in target case, continue
                continue;
            }
        }

        return $count;
    }

    /**
     * Get summary stats for a case
     */
    public function getCaseSummary(int $caseAssetId): array
    {
        $contents = $this->getCaseContents($caseAssetId);

        $totalItems = count($contents);
        $totalQuantity = array_sum(array_column($contents, 'quantity'));

        // Get last check
        $this->db->where('case_asset_id', $caseAssetId);
        $this->db->where('instances_id', $this->instanceId);
        $this->db->orderBy('created_at', 'DESC');

        $lastCheck = $this->db->getOne('case_content_checks', [
            'id',
            'check_type',
            'all_complete',
            'acknowledged',
            'created_at'
        ]);

        return [
            'case_asset_id' => $caseAssetId,
            'total_types' => $totalItems,
            'total_quantity' => $totalQuantity,
            'last_check' => $lastCheck,
            'contents' => $contents
        ];
    }

    /**
     * Get all available asset types for the dropdown (to add to case definition)
     */
    public function getAvailableAssetTypes(): array
    {
        $this->db->join('assetCategories ac', 'ac.assetCategories_id = at.assetCategories_id', 'LEFT');
        $this->db->join('manufacturers m', 'm.manufacturers_id = at.manufacturers_id', 'LEFT');
        $this->db->where('at.instances_id', $this->instanceId);
        $this->db->orderBy('ac.assetCategories_name', 'ASC');
        $this->db->orderBy('at.assetTypes_name', 'ASC');
        $results = $this->db->get('assetTypes at', null, [
            'at.assetTypes_id',
            'at.assetTypes_name',
            'ac.assetCategories_name',
            'm.manufacturers_name',
        ]);

        return is_array($results) ? $results : [];
    }

    /**
     * Get all available stock item types for the dropdown
     */
    public function getAvailableStockItems(): array
    {
        $this->db->where('instances_id', $this->instanceId);
        $this->db->where('deleted', 0);
        $this->db->orderBy('name', 'ASC');
        $results = $this->db->get('stock_items', null, ['id', 'name', 'sku']);

        return is_array($results) ? $results : [];
    }
}
