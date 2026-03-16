<?php
/**
 * Federation Tag Lookup — allows partner servers to look up tags on our instance.
 *
 * Called by remote partner servers with X-Federation-Key header.
 * Returns entity info if the tag belongs to our instance.
 */
require_once __DIR__ . '/federationHead.php';

// Authenticate request and get partner server info
$federationServer = federationAuth();

// Get the local instance ID for this federation connection
// (We store it when the federation was established)
global $DBLIB;
$DBLIB->where('partner_servers_id', $federationServer['partner_servers_id']);
$federationRecord = $DBLIB->getOne('partner_servers', ['instances_id']);
if (!$federationRecord) {
    finish(false, ['code' => 'NOT_FOUND', 'message' => 'Federation connection not found']);
}

$localInstanceId = $federationRecord['instances_id'];

$tagValue = trim($_POST['tag_value'] ?? '');
if (empty($tagValue)) {
    echo json_encode(['found' => false, 'message' => 'tag_value required']);
    exit;
}

// Check assets
$DBLIB->where('a.instances_id', $localInstanceId);
$DBLIB->where('a.assets_deleted', 0);
$DBLIB->where('(a.asset_definableFields_1 = ? OR CONCAT("RMS-A-", LPAD(a.assets_id, 6, "0")) = ?)', [$tagValue, $tagValue]);
$DBLIB->join('assetTypes at', 'a.assetTypes_id=at.assetTypes_id', 'LEFT');
$asset = $DBLIB->getOne('assets a', [
    'a.assets_id', 'a.assets_tag', 'a.assets_name',
    'at.assetTypes_name AS type_name'
]);

if ($asset) {
    echo json_encode([
        'found' => true,
        'entity_type' => 'asset',
        'entity' => [
            'display_name' => trim(($asset['type_name'] ?: '') . ' ' . ($asset['assets_name'] ?: '#' . $asset['assets_tag'])),
            'type_name' => $asset['type_name'],
            'asset_tag' => $asset['assets_tag'],
        ]
    ]);
    exit;
}

// Check stock_instances
$DBLIB->where('(si.rfid_tag = ? OR CONCAT("RMS-I-", LPAD(si.instance_number, 6, "0")) = ?)', [$tagValue, $tagValue]);
$DBLIB->join('stock_items sit', 'si.stock_item_id=sit.id', 'LEFT');
$DBLIB->where('sit.instances_id', $localInstanceId);
$stockInst = $DBLIB->getOne('stock_instances si', [
    'si.id', 'si.instance_number',
    'sit.name AS item_name', 'sit.category'
]);

if ($stockInst) {
    echo json_encode([
        'found' => true,
        'entity_type' => 'stock_instance',
        'entity' => [
            'display_name' => ($stockInst['item_name'] ?: 'Artikel') . ' #' . str_pad($stockInst['instance_number'], 4, '0', STR_PAD_LEFT),
            'item_name' => $stockInst['item_name'],
            'category' => $stockInst['category'],
        ]
    ]);
    exit;
}

// Check external_items
$DBLIB->where('e.instances_id', $localInstanceId);
$DBLIB->where('(e.barcode = ? OR e.rfid_tag = ?)', [$tagValue, $tagValue]);
$ext = $DBLIB->getOne('external_items e', [
    'e.id', 'e.description', 'e.owner_name'
]);

if ($ext) {
    echo json_encode([
        'found' => true,
        'entity_type' => 'external',
        'entity' => [
            'display_name' => $ext['description'] . ' (' . $ext['owner_name'] . ')',
        ]
    ]);
    exit;
}

echo json_encode(['found' => false]);
