<?php
/**
 * Packing List API
 *
 * Loads all items assigned to a project and tracks check-off status.
 * Supports assets (checked out to project), stock instances (assigned to project),
 * and external items (linked to project).
 *
 * POST Parameters:
 *   action = get_list | check_item | uncheck_item | scan_check | export_pdf | get_progress
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/ExternalItemService.php';
require_once __DIR__ . '/../../services/StockItemService.php';

if (!$AUTH->instancePermissionCheck("ASSETS:VIEW")) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$userId = (int)$AUTH->data['users']['users_id'];
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'get_list':
        handleGetList($instanceId);
        break;

    case 'check_item':
        handleCheckItem($userId);
        break;

    case 'uncheck_item':
        handleUncheckItem();
        break;

    case 'scan_check':
        handleScanCheck($instanceId, $userId);
        break;

    case 'get_progress':
        handleGetProgress($instanceId);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        exit;
}

function handleGetList(int $instanceId): void
{
    global $DBLIB;
    $projectId = (int)($_POST['project_id'] ?? 0);
    if (!$projectId) {
        echo json_encode(['success' => false, 'message' => 'project_id required']);
        exit;
    }

    // 1. Get assets checked out to this project
    // Assets are linked via assetAssignments table
    $DBLIB->where('aa.projects_id', $projectId);
    $DBLIB->where('a.instances_id', $instanceId);
    $DBLIB->where('a.assets_deleted', 0);
    $DBLIB->where('aa.assetAssignments_deleted', 0);
    $DBLIB->join('assets a', 'aa.assets_id=a.assets_id', 'INNER');
    $DBLIB->join('assetTypes at', 'a.assetTypes_id=at.assetTypes_id', 'LEFT');
    $DBLIB->join('packing_checks pc', 'pc.entity_type="asset" AND pc.entity_id=a.assets_id AND pc.project_id=' . $projectId, 'LEFT');
    $DBLIB->orderBy('at.assetTypes_name', 'ASC');
    $DBLIB->orderBy('a.assets_tag', 'ASC');
    $assets = $DBLIB->get('assetAssignments aa', null, [
        'a.assets_id AS id',
        'at.assetTypes_name AS type_name',
        'a.assets_tag AS tag',
        'a.assets_name AS name',
        'a.asset_definableFields_1 AS rfid_tag',
        'CONCAT("RMS-A-", LPAD(a.assets_id, 6, "0")) AS barcode',
        'IF(pc.id IS NOT NULL, 1, 0) AS checked',
        'pc.checked_at',
        'pc.checked_by'
    ]);
    if (!$assets) $assets = [];

    // Format asset names
    foreach ($assets as &$asset) {
        $asset['display_name'] = trim(($asset['type_name'] ?: '') . ' ' . ($asset['name'] ?: '#' . $asset['tag']));
        $asset['entity_type'] = 'asset';
        $asset['scan_code'] = $asset['rfid_tag'] ?: $asset['barcode'];
        $asset['checked'] = (bool)$asset['checked'];
    }
    unset($asset);

    // 2. Get stock instances assigned to this project
    $DBLIB->where('sa.project_id', $projectId);
    $DBLIB->where('sa.returned_at IS NULL');
    $DBLIB->join('stock_instances si', 'sa.stock_instance_id=si.id', 'INNER');
    $DBLIB->join('stock_items sit', 'si.stock_item_id=sit.id', 'LEFT');
    $DBLIB->join('packing_checks pc', 'pc.entity_type="stock_instance" AND pc.entity_id=si.id AND pc.project_id=' . $projectId, 'LEFT');
    $DBLIB->orderBy('sit.name', 'ASC');
    $DBLIB->orderBy('si.instance_number', 'ASC');
    $stockInstances = $DBLIB->get('stock_assignments sa', null, [
        'si.id AS id',
        'sit.name AS item_name',
        'sit.category',
        'si.instance_number',
        'si.rfid_tag',
        'CONCAT("RMS-I-", LPAD(si.instance_number, 6, "0")) AS barcode',
        'IF(pc.id IS NOT NULL, 1, 0) AS checked',
        'pc.checked_at',
        'pc.checked_by'
    ]);
    if (!$stockInstances) $stockInstances = [];

    foreach ($stockInstances as &$si) {
        $si['display_name'] = ($si['item_name'] ?: 'Artikel') . ' #' . str_pad($si['instance_number'], 4, '0', STR_PAD_LEFT);
        $si['entity_type'] = 'stock_instance';
        $si['scan_code'] = $si['rfid_tag'] ?: $si['barcode'];
        $si['checked'] = (bool)$si['checked'];
    }
    unset($si);

    // 3. Get external items linked to this project
    $DBLIB->where('e.project_id', $projectId);
    $DBLIB->where('e.status', 'bei_uns');
    $DBLIB->join('packing_checks pc', 'pc.entity_type="external" AND pc.entity_id=e.id AND pc.project_id=' . $projectId, 'LEFT');
    $DBLIB->orderBy('e.description', 'ASC');
    $externalItems = $DBLIB->get('external_items e', null, [
        'e.id',
        'e.description',
        'e.owner_name',
        'e.barcode',
        'e.rfid_tag',
        'e.quantity',
        'IF(pc.id IS NOT NULL, 1, 0) AS checked',
        'pc.checked_at',
        'pc.checked_by'
    ]);
    if (!$externalItems) $externalItems = [];

    foreach ($externalItems as &$ext) {
        $ext['display_name'] = $ext['description'] . ' (' . $ext['owner_name'] . ')';
        $ext['entity_type'] = 'external';
        $ext['scan_code'] = $ext['rfid_tag'] ?: $ext['barcode'];
        $ext['checked'] = (bool)$ext['checked'];
    }
    unset($ext);

    // Calculate totals
    $totalItems = count($assets) + count($stockInstances) + count($externalItems);
    $checkedItems = count(array_filter($assets, fn($a) => $a['checked']))
                  + count(array_filter($stockInstances, fn($s) => $s['checked']))
                  + count(array_filter($externalItems, fn($e) => $e['checked']));

    echo json_encode([
        'success' => true,
        'project_id' => $projectId,
        'assets' => $assets,
        'stock_instances' => $stockInstances,
        'external_items' => $externalItems,
        'progress' => [
            'total' => $totalItems,
            'checked' => $checkedItems,
            'percent' => $totalItems > 0 ? round(($checkedItems / $totalItems) * 100) : 0
        ]
    ]);
    exit;
}

function handleCheckItem(int $userId): void
{
    global $DBLIB;
    $entityType = $_POST['entity_type'] ?? '';
    $entityId = (int)($_POST['entity_id'] ?? 0);
    $projectId = (int)($_POST['project_id'] ?? 0);

    if (!$entityType || !$entityId || !$projectId) {
        echo json_encode(['success' => false, 'message' => 'entity_type, entity_id, project_id required']);
        exit;
    }

    if (!in_array($entityType, ['asset', 'stock_instance', 'external'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid entity_type']);
        exit;
    }

    // Check if already checked
    $DBLIB->where('entity_type', $entityType);
    $DBLIB->where('entity_id', $entityId);
    $DBLIB->where('project_id', $projectId);
    $existing = $DBLIB->getOne('packing_checks', ['id']);

    if ($existing) {
        echo json_encode(['success' => true, 'message' => 'Bereits abgehakt', 'already_checked' => true]);
        exit;
    }

    $id = $DBLIB->insert('packing_checks', [
        'entity_type' => $entityType,
        'entity_id'   => $entityId,
        'project_id'  => $projectId,
        'checked_by'  => $userId,
        'checked_at'  => date('Y-m-d H:i:s')
    ]);

    echo json_encode(['success' => (bool)$id, 'message' => $id ? 'Abgehakt' : 'Fehler']);
    exit;
}

function handleUncheckItem(): void
{
    global $DBLIB;
    $entityType = $_POST['entity_type'] ?? '';
    $entityId = (int)($_POST['entity_id'] ?? 0);
    $projectId = (int)($_POST['project_id'] ?? 0);

    if (!$entityType || !$entityId || !$projectId) {
        echo json_encode(['success' => false, 'message' => 'entity_type, entity_id, project_id required']);
        exit;
    }

    $DBLIB->where('entity_type', $entityType);
    $DBLIB->where('entity_id', $entityId);
    $DBLIB->where('project_id', $projectId);
    $result = $DBLIB->delete('packing_checks');

    echo json_encode(['success' => $result, 'message' => $result ? 'Haken entfernt' : 'Fehler']);
    exit;
}

function handleScanCheck(int $instanceId, int $userId): void
{
    global $DBLIB;
    $scanValue = trim($_POST['scan_value'] ?? '');
    $projectId = (int)($_POST['project_id'] ?? 0);

    if (empty($scanValue) || !$projectId) {
        echo json_encode(['success' => false, 'message' => 'scan_value und project_id required']);
        exit;
    }

    // Try to find the item by RFID tag or barcode across all entity types
    $found = null;

    // 1. Check assets (RFID tag in asset_definableFields_1, or barcode pattern RMS-A-xxxxxx)
    $DBLIB->where('(a.asset_definableFields_1 = ? OR CONCAT("RMS-A-", LPAD(a.assets_id, 6, "0")) = ?)', [$scanValue, $scanValue]);
    $DBLIB->where('a.instances_id', $instanceId);
    $DBLIB->where('a.assets_deleted', 0);
    $asset = $DBLIB->getOne('assets a', ['a.assets_id AS id']);
    if ($asset) {
        $found = ['entity_type' => 'asset', 'entity_id' => (int)$asset['id']];
    }

    // 2. Check stock instances
    if (!$found) {
        $DBLIB->where('(si.rfid_tag = ? OR CONCAT("RMS-I-", LPAD(si.instance_number, 6, "0")) = ?)', [$scanValue, $scanValue]);
        $si = $DBLIB->getOne('stock_instances si', ['si.id']);
        if ($si) {
            $found = ['entity_type' => 'stock_instance', 'entity_id' => (int)$si['id']];
        }
    }

    // 3. Check external items
    if (!$found) {
        $DBLIB->where('(e.barcode = ? OR e.rfid_tag = ?)', [$scanValue, $scanValue]);
        $ext = $DBLIB->getOne('external_items e', ['e.id']);
        if ($ext) {
            $found = ['entity_type' => 'external', 'entity_id' => (int)$ext['id']];
        }
    }

    if (!$found) {
        echo json_encode(['success' => false, 'message' => 'Kein Item mit diesem Code gefunden', 'scan_value' => $scanValue]);
        exit;
    }

    // Check off the item
    $DBLIB->where('entity_type', $found['entity_type']);
    $DBLIB->where('entity_id', $found['entity_id']);
    $DBLIB->where('project_id', $projectId);
    $existing = $DBLIB->getOne('packing_checks', ['id']);

    if (!$existing) {
        $DBLIB->insert('packing_checks', [
            'entity_type' => $found['entity_type'],
            'entity_id'   => $found['entity_id'],
            'project_id'  => $projectId,
            'checked_by'  => $userId,
            'checked_at'  => date('Y-m-d H:i:s')
        ]);
    }

    echo json_encode([
        'success' => true,
        'entity_type' => $found['entity_type'],
        'entity_id' => $found['entity_id'],
        'message' => 'Abgehakt',
        'already_checked' => (bool)$existing
    ]);
    exit;
}

function handleGetProgress(int $instanceId): void
{
    global $DBLIB;
    $projectId = (int)($_POST['project_id'] ?? 0);
    if (!$projectId) {
        echo json_encode(['success' => false, 'message' => 'project_id required']);
        exit;
    }

    // Count total items in project
    // Assets
    $DBLIB->where('aa.projects_id', $projectId);
    $DBLIB->where('aa.assetAssignments_deleted', 0);
    $DBLIB->join('assets a', 'aa.assets_id=a.assets_id', 'INNER');
    $DBLIB->where('a.instances_id', $instanceId);
    $DBLIB->where('a.assets_deleted', 0);
    $totalAssets = (int)$DBLIB->getValue('assetAssignments aa', 'COUNT(*)');

    // Stock instances
    $DBLIB->where('sa.project_id', $projectId);
    $DBLIB->where('sa.returned_at IS NULL');
    $totalStock = (int)$DBLIB->getValue('stock_assignments sa', 'COUNT(*)');

    // External
    $DBLIB->where('project_id', $projectId);
    $DBLIB->where('status', 'bei_uns');
    $totalExternal = (int)$DBLIB->getValue('external_items', 'COUNT(*)');

    $total = $totalAssets + $totalStock + $totalExternal;

    // Count checked
    $DBLIB->where('project_id', $projectId);
    $checked = (int)$DBLIB->getValue('packing_checks', 'COUNT(*)');

    echo json_encode([
        'success' => true,
        'progress' => [
            'total' => $total,
            'checked' => min($checked, $total),
            'percent' => $total > 0 ? round((min($checked, $total) / $total) * 100) : 0,
            'assets' => $totalAssets,
            'stock' => $totalStock,
            'external' => $totalExternal
        ]
    ]);
    exit;
}
