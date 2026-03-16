<?php
/**
 * RFID Scanner REST API
 *
 * Endpoints for the Chafon CF-H906 UHF RFID handheld reader to process scans,
 * manage tag assignments, and handle inventory operations.
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/StockItemService.php';
require_once __DIR__ . '/../../services/CrossInstanceLookupService.php';

// Check permissions
if (!$AUTH->instancePermissionCheck("ASSETS:ASSET_BARCODES:SCAN")) {
    finish(false, ["code" => "PERMISSIONS", "message" => "Insufficient permissions"]);
}

$instanceId = $AUTH->data['instance']['instances_id'];
$userId = $AUTH->data['users_userid'];

$rfidService = new RfidService($DBLIB);
$stockService = new StockItemService($DBLIB);
$crossLookup = new CrossInstanceLookupService($DBLIB, $instanceId);
$rfidService->setStockService($stockService);
$rfidService->setCrossLookupService($crossLookup);

// Get the action parameter
$action = $_POST['action'] ?? null;

if (!$action) {
    finish(false, ["code" => "INVALID", "message" => "Action parameter required"]);
}

switch ($action) {
    case 'scan':
        handleScan($rfidService, $instanceId, $userId);
        break;

    case 'bulk_scan':
        handleBulkScan($rfidService, $instanceId, $userId);
        break;

    case 'lookup':
        handleLookup($rfidService, $instanceId);
        break;

    case 'assign_tag':
        handleAssignTag($rfidService, $instanceId, $userId);
        break;

    case 'unassign_tag':
        handleUnassignTag($rfidService, $instanceId, $userId);
        break;

    case 'start_inventory':
        handleStartInventory($rfidService, $instanceId, $userId);
        break;

    case 'inventory_scan':
        handleInventoryScan($rfidService);
        break;

    case 'complete_inventory':
        handleCompleteInventory($rfidService);
        break;

    case 'list_assets':
        handleListAssets($instanceId);
        break;

    // ── New: Universal scan (handles both assets and stock instances) ──
    case 'universal_scan':
        handleUniversalScan($rfidService, $instanceId, $userId);
        break;

    case 'universal_lookup':
        handleUniversalLookup($rfidService, $instanceId);
        break;

    // ── New: Box scan (Kisten-Scan) ──
    case 'box_scan':
        handleBoxScan($stockService, $instanceId, $userId);
        break;

    case 'box_scan_action':
        handleBoxScanAction($stockService, $rfidService, $instanceId, $userId);
        break;

    default:
        finish(false, ["code" => "INVALID", "message" => "Unknown action"]);
}

/**
 * Process a single scan
 */
function handleScan($rfidService, $instanceId, $userId)
{
    $rfidTag = $_POST['rfid_tag'] ?? null;
    $scanAction = $_POST['scan_action'] ?? null;
    $projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : null;

    if (!$rfidTag || !$scanAction) {
        finish(false, ["code" => "INVALID", "message" => "rfid_tag and scan_action required"]);
    }

    $result = $rfidService->processScan($instanceId, $rfidTag, $scanAction, $userId, $projectId);

    if ($result['success']) {
        finish(true, null, [
            'asset' => $result['asset'],
            'message' => $result['message'],
            'action_taken' => $result['action_taken'],
        ]);
    } else {
        finish(false, [
            "code" => "SCAN_FAILED",
            "message" => $result['message'],
            "asset" => $result['asset'],
        ]);
    }
}

/**
 * Process multiple tag scans
 */
function handleBulkScan($rfidService, $instanceId, $userId)
{
    $rfidTagsJson = $_POST['rfid_tags'] ?? null;
    $scanAction = $_POST['scan_action'] ?? null;
    $projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : null;

    if (!$rfidTagsJson || !$scanAction) {
        finish(false, ["code" => "INVALID", "message" => "rfid_tags and scan_action required"]);
    }

    $rfidTags = json_decode($rfidTagsJson, true);

    if (!is_array($rfidTags)) {
        finish(false, ["code" => "INVALID", "message" => "rfid_tags must be a JSON array"]);
    }

    $results = [];
    foreach ($rfidTags as $tag) {
        $result = $rfidService->processScan($instanceId, $tag, $scanAction, $userId, $projectId);
        $results[] = [
            'tag' => $tag,
            'success' => $result['success'],
            'asset' => $result['asset'],
            'message' => $result['message'],
        ];
    }

    finish(true, null, ['scans' => $results]);
}

/**
 * Look up asset by RFID tag
 */
function handleLookup($rfidService, $instanceId)
{
    $rfidTag = $_POST['rfid_tag'] ?? null;

    if (!$rfidTag) {
        finish(false, ["code" => "INVALID", "message" => "rfid_tag required"]);
    }

    $asset = $rfidService->findByTag($instanceId, $rfidTag);

    if ($asset) {
        finish(true, null, ['asset' => $asset]);
    } else {
        finish(false, ["code" => "NOT_FOUND", "message" => "Asset not found"]);
    }
}

/**
 * Assign RFID tag to asset
 */
function handleAssignTag($rfidService, $instanceId, $userId)
{
    $assetId = isset($_POST['asset_id']) ? (int)$_POST['asset_id'] : null;
    $rfidTag = $_POST['rfid_tag'] ?? null;

    if (!$assetId || !$rfidTag) {
        finish(false, ["code" => "INVALID", "message" => "asset_id and rfid_tag required"]);
    }

    $success = $rfidService->assignTag($assetId, $rfidTag, $userId);

    if ($success) {
        finish(true, null, ['message' => 'Tag assigned successfully']);
    } else {
        finish(false, ["code" => "ASSIGN_FAILED", "message" => "Failed to assign tag"]);
    }
}

/**
 * Remove RFID tag from asset
 */
function handleUnassignTag($rfidService, $instanceId, $userId)
{
    $assetId = isset($_POST['asset_id']) ? (int)$_POST['asset_id'] : null;

    if (!$assetId) {
        finish(false, ["code" => "INVALID", "message" => "asset_id required"]);
    }

    $success = $rfidService->unassignTag($assetId, $userId);

    if ($success) {
        finish(true, null, ['message' => 'Tag removed successfully']);
    } else {
        finish(false, ["code" => "UNASSIGN_FAILED", "message" => "Failed to remove tag"]);
    }
}

/**
 * Start inventory session
 */
function handleStartInventory($rfidService, $instanceId, $userId)
{
    $sessionId = $rfidService->startInventory($instanceId, $userId);

    finish(true, null, [
        'session_id' => $sessionId,
        'message' => 'Inventory session started',
    ]);
}

/**
 * Process tag scan during inventory
 */
function handleInventoryScan($rfidService)
{
    $sessionId = isset($_POST['session_id']) ? (int)$_POST['session_id'] : null;
    $rfidTag = $_POST['rfid_tag'] ?? null;

    if (!$sessionId || !$rfidTag) {
        finish(false, ["code" => "INVALID", "message" => "session_id and rfid_tag required"]);
    }

    $result = $rfidService->processInventoryScan($sessionId, $rfidTag);

    if ($result['success']) {
        finish(true, null, [
            'asset' => $result['asset'],
            'message' => $result['message'],
        ]);
    } else {
        finish(false, [
            "code" => "SCAN_FAILED",
            "message" => $result['message'],
            "asset" => $result['asset'],
        ]);
    }
}

/**
 * Complete inventory and get results
 */
function handleCompleteInventory($rfidService)
{
    $sessionId = isset($_POST['session_id']) ? (int)$_POST['session_id'] : null;

    if (!$sessionId) {
        finish(false, ["code" => "INVALID", "message" => "session_id required"]);
    }

    $result = $rfidService->completeInventory($sessionId);

    if ($result['success']) {
        finish(true, null, [
            'found_assets' => $result['found_assets'],
            'missing_assets' => $result['missing_assets'],
            'unknown_tags' => $result['unknown_tags'],
            'message' => $result['message'],
        ]);
    } else {
        finish(false, ["code" => "INVENTORY_FAILED", "message" => $result['message']]);
    }
}

/**
 * List all assets for dropdown selectors
 */
function handleListAssets(int $instanceId)
{
    global $DBLIB;
    $DBLIB->where('a.instances_id', $instanceId);
    $DBLIB->where('a.assets_deleted', 0);
    $DBLIB->join('assetTypes at', 'a.assetTypes_id=at.assetTypes_id', 'LEFT');
    $DBLIB->orderBy('at.assetTypes_name', 'ASC');
    $DBLIB->orderBy('a.assets_tag', 'ASC');
    $assets = $DBLIB->get('assets a', 500, [
        'a.assets_id', 'a.assets_tag', 'a.asset_definableFields_1 AS rfid_tag',
        'at.assetTypes_name'
    ]);
    finish(true, null, ["assets" => $assets ?: []]);
}

/**
 * Universal scan: automatically detects asset vs stock instance
 */
function handleUniversalScan($rfidService, $instanceId, $userId)
{
    $rfidTag = $_POST['rfid_tag'] ?? null;
    $scanAction = $_POST['scan_action'] ?? null;
    $projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : null;

    if (!$rfidTag || !$scanAction) {
        finish(false, ["code" => "INVALID", "message" => "rfid_tag and scan_action required"]);
    }

    $result = $rfidService->processUniversalScan($instanceId, $rfidTag, $scanAction, $userId, $projectId);

    if ($result['success']) {
        finish(true, null, [
            'entity_type'  => $result['entity_type'],
            'entity'       => $result['entity'] ?? $result['asset'] ?? null,
            'message'      => $result['message'],
            'action_taken' => $result['action_taken'],
        ]);
    } else {
        finish(false, [
            "code"        => "SCAN_FAILED",
            "message"     => $result['message'],
            "entity_type" => $result['entity_type'],
            "entity"      => $result['entity'] ?? $result['asset'] ?? null,
        ]);
    }
}

/**
 * Universal lookup: finds asset or stock instance by RFID tag
 */
function handleUniversalLookup($rfidService, $instanceId)
{
    $rfidTag = $_POST['rfid_tag'] ?? null;

    if (!$rfidTag) {
        finish(false, ["code" => "INVALID", "message" => "rfid_tag required"]);
    }

    $entity = $rfidService->findEntityByTag($instanceId, $rfidTag);

    if ($entity) {
        finish(true, null, [
            'entity_type' => $entity['entity_type'],
            'entity'      => $entity,
        ]);
    } else {
        finish(false, ["code" => "NOT_FOUND", "message" => "Weder Gerät noch Artikel gefunden"]);
    }
}

/**
 * Box scan: process multiple tags and group results
 */
function handleBoxScan($stockService, $instanceId, $userId)
{
    $rfidTagsJson = $_POST['rfid_tags'] ?? null;

    if (!$rfidTagsJson) {
        finish(false, ["code" => "INVALID", "message" => "rfid_tags required"]);
    }

    $rfidTags = json_decode($rfidTagsJson, true);
    if (!is_array($rfidTags) || empty($rfidTags)) {
        finish(false, ["code" => "INVALID", "message" => "rfid_tags must be a non-empty JSON array"]);
    }

    $result = $stockService->processBoxScan($instanceId, $rfidTags);

    // Optionally save session
    $sessionName = $_POST['session_name'] ?? '';
    if (!empty($sessionName)) {
        $projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : null;
        $sessionId = $stockService->saveBoxScanSession(
            $instanceId, $userId, $sessionName, $result, $projectId, 'count'
        );
        $result['session_id'] = $sessionId;
    }

    finish(true, null, ['result' => $result]);
}

/**
 * Box scan with action: checkout/checkin all scanned items
 */
function handleBoxScanAction($stockService, $rfidService, $instanceId, $userId)
{
    $rfidTagsJson = $_POST['rfid_tags'] ?? null;
    $scanAction = $_POST['scan_action'] ?? 'count';
    $projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : null;

    if (!$rfidTagsJson) {
        finish(false, ["code" => "INVALID", "message" => "rfid_tags required"]);
    }

    $rfidTags = json_decode($rfidTagsJson, true);
    if (!is_array($rfidTags) || empty($rfidTags)) {
        finish(false, ["code" => "INVALID", "message" => "rfid_tags must be a non-empty JSON array"]);
    }

    // For assets, use the RfidService for checkout/checkin
    $result = $stockService->processBoxScanWithAction(
        $instanceId, $rfidTags, $scanAction, $userId, $projectId
    );

    // Also process assets through RfidService
    if ($scanAction === 'checkout' && $projectId) {
        foreach ($result['assets'] as $asset) {
            if ($asset['status'] === 'available') {
                $rfidService->processScan(
                    $instanceId, $asset['rfid_tag'], 'checkout', $userId, $projectId
                );
                $result['action_results']['successes']++;
            }
        }
    } elseif ($scanAction === 'checkin') {
        foreach ($result['assets'] as $asset) {
            if ($asset['status'] === 'checked_out') {
                $rfidService->processScan(
                    $instanceId, $asset['rfid_tag'], 'checkin', $userId
                );
                $result['action_results']['successes']++;
            }
        }
    }

    finish(true, null, ['result' => $result]);
}
