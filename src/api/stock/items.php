<?php
/**
 * Stock Items & Instances API
 *
 * Manages article types (Artikeltypen) and their individual instances.
 * Provides CRUD for items, bulk instance creation, and box scan functionality.
 *
 * POST actions:
 *   list_items       - List all stock item types
 *   get_item         - Get item type with instance counts
 *   create_item      - Create new item type
 *   update_item      - Update item type
 *   delete_item      - Soft-delete item type
 *   list_instances   - List instances for an item type
 *   create_instances - Bulk create instances
 *   update_instance  - Update a single instance
 *   assign_rfid      - Assign RFID tag to instance
 *   box_scan         - Process box scan (multiple tags)
 *   box_scan_action  - Box scan with checkout/checkin
 *   categories       - List distinct categories
 *   overview         - Stock statistics overview
 *   low_stock        - Low stock warnings
 */

require_once __DIR__ . '/../../services/StockItemService.php';

header('Content-Type: application/json');

// Auth check
if (!isset($AUTH) || !$AUTH->instancePermissionCheck("ASSETS:VIEW")) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$stockService = new StockItemService($DBLIB);
$instanceId = $AUTH->data['instances_id'];
$userId = $AUTH->data['users_userid'];

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {

        case 'list_items':
            $category = $_POST['category'] ?? null;
            $includeInactive = ($_POST['include_inactive'] ?? '0') === '1';
            $items = $stockService->listItems($instanceId, $category, $includeInactive);
            echo json_encode(['success' => true, 'items' => $items]);
            break;

        case 'get_item':
            $itemId = (int) ($_POST['item_id'] ?? 0);
            $item = $stockService->getItem($itemId);
            if ($item) {
                echo json_encode(['success' => true, 'item' => $item]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Artikeltyp nicht gefunden']);
            }
            break;

        case 'create_item':
            if (!$AUTH->instancePermissionCheck("ASSETS:ASSET_TYPES:CREATE")) {
                http_response_code(403);
                echo json_encode(['error' => 'Keine Berechtigung']);
                exit;
            }

            $data = [
                'name'        => $_POST['name'] ?? '',
                'description' => $_POST['description'] ?? null,
                'category'    => $_POST['category'] ?? '',
                'sku'         => $_POST['sku'] ?? '',
                'unit_value'  => (float) ($_POST['unit_value'] ?? 0),
                'day_rate'    => (float) ($_POST['day_rate'] ?? 0),
                'week_rate'   => (float) ($_POST['week_rate'] ?? 0),
                'min_stock'   => (int) ($_POST['min_stock'] ?? 0),
                'notes'       => $_POST['notes'] ?? null,
            ];

            if (empty($data['name'])) {
                echo json_encode(['success' => false, 'message' => 'Name ist erforderlich']);
                break;
            }

            $id = $stockService->createItem($instanceId, $data);
            echo json_encode(['success' => (bool) $id, 'item_id' => $id]);
            break;

        case 'update_item':
            if (!$AUTH->instancePermissionCheck("ASSETS:ASSET_TYPES:EDIT")) {
                http_response_code(403);
                echo json_encode(['error' => 'Keine Berechtigung']);
                exit;
            }

            $itemId = (int) ($_POST['item_id'] ?? 0);
            $data = array_filter($_POST, fn($k) => in_array($k, [
                'name', 'description', 'category', 'sku', 'unit_value',
                'day_rate', 'week_rate', 'min_stock', 'notes', 'active',
            ]), ARRAY_FILTER_USE_KEY);

            $ok = $stockService->updateItem($itemId, $data);
            echo json_encode(['success' => $ok]);
            break;

        case 'delete_item':
            if (!$AUTH->instancePermissionCheck("ASSETS:ASSET_TYPES:DELETE")) {
                http_response_code(403);
                echo json_encode(['error' => 'Keine Berechtigung']);
                exit;
            }

            $itemId = (int) ($_POST['item_id'] ?? 0);
            $ok = $stockService->deleteItem($itemId);
            echo json_encode(['success' => $ok]);
            break;

        case 'list_instances':
            $itemId = (int) ($_POST['item_id'] ?? 0);
            $status = $_POST['status'] ?? null;
            $instances = $stockService->listInstances($itemId, $status ?: null);
            echo json_encode(['success' => true, 'instances' => $instances]);
            break;

        case 'create_instances':
            if (!$AUTH->instancePermissionCheck("ASSETS:ASSET_TYPES:CREATE")) {
                http_response_code(403);
                echo json_encode(['error' => 'Keine Berechtigung']);
                exit;
            }

            $itemId = (int) ($_POST['item_id'] ?? 0);
            $quantity = (int) ($_POST['quantity'] ?? 1);
            $autoRfid = ($_POST['auto_rfid'] ?? '1') === '1';

            if ($quantity < 1 || $quantity > 500) {
                echo json_encode(['success' => false, 'message' => 'Menge muss zwischen 1 und 500 liegen']);
                break;
            }

            $ids = $stockService->createInstances($itemId, $instanceId, $quantity, $autoRfid);
            echo json_encode(['success' => true, 'created' => count($ids), 'instance_ids' => $ids]);
            break;

        case 'update_instance':
            $instanceDbId = (int) ($_POST['instance_id'] ?? 0);
            $data = array_filter($_POST, fn($k) => in_array($k, [
                'status', 'condition', 'location', 'notes',
            ]), ARRAY_FILTER_USE_KEY);

            $ok = $stockService->updateInstance($instanceDbId, $data);
            echo json_encode(['success' => $ok]);
            break;

        case 'assign_rfid':
            if (!$AUTH->instancePermissionCheck("ASSETS:ASSET_BARCODES:SCAN")) {
                http_response_code(403);
                echo json_encode(['error' => 'Keine Berechtigung']);
                exit;
            }

            $instanceDbId = (int) ($_POST['instance_id'] ?? 0);
            $rfidTag = trim($_POST['rfid_tag'] ?? '');

            if (empty($rfidTag)) {
                echo json_encode(['success' => false, 'message' => 'RFID-Tag erforderlich']);
                break;
            }

            $ok = $stockService->assignRfidTag($instanceDbId, $rfidTag, $instanceId);
            if ($ok) {
                echo json_encode(['success' => true, 'message' => 'RFID-Tag zugewiesen']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Tag bereits vergeben oder Instanz nicht gefunden']);
            }
            break;

        case 'box_scan':
            if (!$AUTH->instancePermissionCheck("ASSETS:ASSET_BARCODES:SCAN")) {
                http_response_code(403);
                echo json_encode(['error' => 'Keine Berechtigung']);
                exit;
            }

            $tagsJson = $_POST['rfid_tags'] ?? '[]';
            $tags = json_decode($tagsJson, true);

            if (!is_array($tags) || empty($tags)) {
                echo json_encode(['success' => false, 'message' => 'Keine Tags übermittelt']);
                break;
            }

            $result = $stockService->processBoxScan($instanceId, $tags);

            // Optionally save session
            $saveName = $_POST['session_name'] ?? '';
            if (!empty($saveName)) {
                $sessionId = $stockService->saveBoxScanSession(
                    $instanceId,
                    $userId,
                    $saveName,
                    $result,
                    (int) ($_POST['project_id'] ?? 0) ?: null,
                    'count'
                );
                $result['session_id'] = $sessionId;
            }

            echo json_encode(['success' => true, 'result' => $result]);
            break;

        case 'box_scan_action':
            if (!$AUTH->instancePermissionCheck("ASSETS:ASSET_BARCODES:SCAN")) {
                http_response_code(403);
                echo json_encode(['error' => 'Keine Berechtigung']);
                exit;
            }

            $tagsJson = $_POST['rfid_tags'] ?? '[]';
            $tags = json_decode($tagsJson, true);
            $scanAction = $_POST['scan_action'] ?? 'count';
            $projectId = (int) ($_POST['project_id'] ?? 0) ?: null;

            if (!is_array($tags) || empty($tags)) {
                echo json_encode(['success' => false, 'message' => 'Keine Tags übermittelt']);
                break;
            }

            $result = $stockService->processBoxScanWithAction(
                $instanceId,
                $tags,
                $scanAction,
                $userId,
                $projectId
            );

            echo json_encode(['success' => true, 'result' => $result]);
            break;

        case 'categories':
            $categories = $stockService->getCategories($instanceId);
            echo json_encode(['success' => true, 'categories' => $categories]);
            break;

        case 'overview':
            $overview = $stockService->getStockOverview($instanceId);
            echo json_encode(['success' => true, 'overview' => $overview]);
            break;

        case 'low_stock':
            $warnings = $stockService->getLowStockWarnings($instanceId);
            echo json_encode(['success' => true, 'warnings' => $warnings]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Unbekannte Aktion: ' . $action]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server-Fehler: ' . $e->getMessage()]);
}
