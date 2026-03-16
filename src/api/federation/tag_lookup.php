<?php
/**
 * Federation Tag Lookup — allows partner servers to look up tags on our instance.
 *
 * Called by remote partner servers with X-Federation-Key header.
 * Returns entity info if the tag belongs to our instance.
 *
 * Supports:
 *   - New format:  RMS-a3f7b2c1-A-000042
 *   - Old formats: RMS-A-000042, RMS-XXXX-A-000042
 *   - QR format:   RMS://a3f7b2c1/A/000042
 *   - Binary EPC:  52A3F7B2C14100000042xxxx (96-bit or 128-bit hex)
 *   - Raw RFID:    stored in asset_definableFields_1 or stock rfid_tag
 */
require_once __DIR__ . '/federationHead.php';
require_once __DIR__ . '/../../services/TagFormatService.php';

// Authenticate request and get partner server info
$federationServer = federationAuth();

// Get the local instance ID for this federation connection
global $DBLIB;
$DBLIB->where('partner_servers_id', $federationServer['partner_servers_id']);
$federationRecord = $DBLIB->getOne('partner_servers', ['instances_id']);
if (!$federationRecord) {
    finish(false, ['code' => 'NOT_FOUND', 'message' => 'Federation connection not found']);
}

$localInstanceId = (int)$federationRecord['instances_id'];
$tagService = new TagFormatService($DBLIB, $localInstanceId);

$tagValue = trim($_POST['tag_value'] ?? '');
if (empty($tagValue)) {
    finish(false, ['code' => 'INVALID', 'message' => 'tag_value required']);
}

// ── Step 1: Try to resolve binary EPC hex (from Chafon reader or similar) ──
$resolvedTag = $tagValue;
$binaryDecoded = null;
$cleanHex = strtoupper(trim($tagValue));

if (preg_match('/^[0-9A-F]{24}$/', $cleanHex) || preg_match('/^[0-9A-F]{32}$/', $cleanHex)) {
    if (substr($cleanHex, 0, 2) === '52') {
        $binaryDecoded = $tagService->decodeBinaryEpc($cleanHex);
        if ($binaryDecoded && $binaryDecoded['crc_valid']) {
            $resolvedTag = $binaryDecoded['human_readable'];
        }
    }
}

// ── Step 2: Try to parse as RMS format (new or legacy) ──
$parsed = $tagService->parse($resolvedTag);

if ($parsed) {
    // We have a structured RMS tag — look up by entity type + ID
    $entityType = $parsed['entity_type'];
    $entityId = $parsed['entity_id'];

    if ($entityType === 'asset') {
        $DBLIB->where('a.assets_id', $entityId);
        $DBLIB->where('a.instances_id', $localInstanceId);
        $DBLIB->where('a.assets_deleted', 0);
        $DBLIB->join('assetTypes at', 'a.assetTypes_id=at.assetTypes_id', 'LEFT');
        $asset = $DBLIB->getOne('assets a', [
            'a.assets_id', 'a.assets_tag', 'a.assets_name',
            'a.assets_serialInternal', 'a.assets_serialManufacturer',
            'at.assetTypes_name AS type_name'
        ]);

        if ($asset) {
            finish(true, null, [
                'found' => true,
                'entity_type' => 'asset',
                'company_code' => $tagService->getCompanyCode(),
                'entity' => [
                    'display_name' => trim(($asset['type_name'] ?: '') . ' ' . ($asset['assets_name'] ?: '#' . $asset['assets_tag'])),
                    'type_name' => $asset['type_name'],
                    'asset_tag' => $asset['assets_tag'],
                    'serial_internal' => $asset['assets_serialInternal'] ?? null,
                ]
            ]);
        }
    } elseif ($entityType === 'stock_instance') {
        $DBLIB->where('si.instance_number', $entityId);
        $DBLIB->join('stock_items sit', 'si.stock_item_id=sit.id', 'LEFT');
        $DBLIB->where('sit.instances_id', $localInstanceId);
        $stockInst = $DBLIB->getOne('stock_instances si', [
            'si.id', 'si.instance_number',
            'sit.name AS item_name', 'sit.category'
        ]);

        if ($stockInst) {
            finish(true, null, [
                'found' => true,
                'entity_type' => 'stock_instance',
                'company_code' => $tagService->getCompanyCode(),
                'entity' => [
                    'display_name' => ($stockInst['item_name'] ?: 'Artikel') . ' #' . str_pad($stockInst['instance_number'], 4, '0', STR_PAD_LEFT),
                    'item_name' => $stockInst['item_name'],
                    'category' => $stockInst['category'],
                ]
            ]);
        }
    } elseif ($entityType === 'external') {
        $DBLIB->where('e.id', $entityId);
        $DBLIB->where('e.instances_id', $localInstanceId);
        $ext = $DBLIB->getOne('external_items e', [
            'e.id', 'e.description', 'e.owner_name'
        ]);

        if ($ext) {
            finish(true, null, [
                'found' => true,
                'entity_type' => 'external',
                'company_code' => $tagService->getCompanyCode(),
                'entity' => [
                    'display_name' => $ext['description'] . ' (' . $ext['owner_name'] . ')',
                ]
            ]);
        }
    }
}

// ── Step 3: Fallback — search by raw RFID tag value in all entity tables ──

// Check assets by RFID tag field
$DBLIB->where('a.instances_id', $localInstanceId);
$DBLIB->where('a.assets_deleted', 0);
$DBLIB->where('a.asset_definableFields_1', $tagValue);
$DBLIB->join('assetTypes at', 'a.assetTypes_id=at.assetTypes_id', 'LEFT');
$asset = $DBLIB->getOne('assets a', [
    'a.assets_id', 'a.assets_tag', 'a.assets_name',
    'at.assetTypes_name AS type_name'
]);

if ($asset) {
    finish(true, null, [
        'found' => true,
        'entity_type' => 'asset',
        'company_code' => $tagService->getCompanyCode(),
        'entity' => [
            'display_name' => trim(($asset['type_name'] ?: '') . ' ' . ($asset['assets_name'] ?: '#' . $asset['assets_tag'])),
            'type_name' => $asset['type_name'],
            'asset_tag' => $asset['assets_tag'],
        ]
    ]);
}

// Check stock_instances by RFID tag
$DBLIB->where('si.rfid_tag', $tagValue);
$DBLIB->join('stock_items sit', 'si.stock_item_id=sit.id', 'LEFT');
$DBLIB->where('sit.instances_id', $localInstanceId);
$stockInst = $DBLIB->getOne('stock_instances si', [
    'si.id', 'si.instance_number',
    'sit.name AS item_name', 'sit.category'
]);

if ($stockInst) {
    finish(true, null, [
        'found' => true,
        'entity_type' => 'stock_instance',
        'company_code' => $tagService->getCompanyCode(),
        'entity' => [
            'display_name' => ($stockInst['item_name'] ?: 'Artikel') . ' #' . str_pad($stockInst['instance_number'], 4, '0', STR_PAD_LEFT),
            'item_name' => $stockInst['item_name'],
            'category' => $stockInst['category'],
        ]
    ]);
}

// Check external_items by barcode or RFID
$DBLIB->where('e.instances_id', $localInstanceId);
$DBLIB->where('(e.barcode = ? OR e.rfid_tag = ?)', [$tagValue, $tagValue]);
$ext = $DBLIB->getOne('external_items e', [
    'e.id', 'e.description', 'e.owner_name'
]);

if ($ext) {
    finish(true, null, [
        'found' => true,
        'entity_type' => 'external',
        'company_code' => $tagService->getCompanyCode(),
        'entity' => [
            'display_name' => $ext['description'] . ' (' . $ext['owner_name'] . ')',
        ]
    ]);
}

// Not found
finish(true, null, ['found' => false]);
