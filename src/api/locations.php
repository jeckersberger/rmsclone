<?php

/**
 * Location Tracking REST API
 *
 * Handles all location tracking operations including CRUD for locations,
 * entity location assignment, history tracking, and RFID-based location scanning.
 */

require_once __DIR__ . '/apiHeadSecure.php';
require_once __DIR__ . '/../services/LocationService.php';

$locationService = new LocationService();
$user = $AUTH->data;
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if (!$user) {
    finish(false, ['message' => 'Nicht angemeldet']);
}

switch ($action) {
    /**
     * LIST_LOCATIONS
     * Returns all active locations
     */
    case 'list_locations':
        try {
            $locations = $locationService->listLocations(true);
            finish(true, null, ['locations' => $locations]);
        } catch (Exception $e) {
            finish(false, ['message' => 'Fehler beim Laden der Standorte']);
        }
        break;

    /**
     * ALL_LOCATIONS
     * Returns all locations including inactive (admin only)
     */
    case 'all_locations':
        try {
            if (!$AUTH->serverPermissionCheck("SETTINGS") && !$AUTH->serverPermissionCheck("ASSETS:ASSET_TYPES:CREATE")) {
                finish(false, ['message' => 'Keine Berechtigung']);
            }

            $locations = $locationService->listLocations(false);
            finish(true, null, ['locations' => $locations]);
        } catch (Exception $e) {
            finish(false, ['message' => 'Fehler beim Laden der Standorte']);
        }
        break;

    /**
     * GET_LOCATION
     * params: id (location ID)
     */
    case 'get_location':
        try {
            $id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
            if (!$id) {
                finish(false, ['message' => 'Standort-ID erforderlich']);
            }

            $location = $locationService->getLocation($id);
            if (!$location) {
                finish(false, ['message' => 'Standort nicht gefunden']);
            }

            finish(true, null, ['location' => $location]);
        } catch (Exception $e) {
            finish(false, ['message' => 'Fehler beim Abrufen des Standorts']);
        }
        break;

    /**
     * CREATE_LOCATION
     * params: name (required), description?, color?, icon?, sort_order?
     * Permission: ASSETS:ASSET_TYPES:CREATE or SETTINGS
     */
    case 'create_location':
        try {
            if (!$AUTH->serverPermissionCheck("ASSETS:ASSET_TYPES:CREATE") && !$AUTH->serverPermissionCheck("SETTINGS")) {
                finish(false, ['message' => 'Keine Berechtigung']);
            }

            $data = [
                'name' => $_POST['name'] ?? '',
                'description' => $_POST['description'] ?? null,
                'color' => $_POST['color'] ?? '#808080',
                'icon' => $_POST['icon'] ?? 'map-pin',
                'sort_order' => intval($_POST['sort_order'] ?? 0),
            ];

            if (empty($data['name'])) {
                finish(false, ['message' => 'Standortname erforderlich']);
            }

            $id = $locationService->createLocation($data);
            $location = $locationService->getLocation($id);

            finish(true, null, ['location' => $location, 'message' => 'Standort erstellt']);
        } catch (Exception $e) {
            finish(false, ['message' => $e->getMessage() ?: 'Fehler beim Erstellen des Standorts']);
        }
        break;

    /**
     * UPDATE_LOCATION
     * params: id, name?, description?, color?, icon?, sort_order?, is_active?
     * Permission: ASSETS:ASSET_TYPES:CREATE or SETTINGS
     */
    case 'update_location':
        try {
            if (!$AUTH->serverPermissionCheck("ASSETS:ASSET_TYPES:CREATE") && !$AUTH->serverPermissionCheck("SETTINGS")) {
                finish(false, ['message' => 'Keine Berechtigung']);
            }

            $id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
            if (!$id) {
                finish(false, ['message' => 'Standort-ID erforderlich']);
            }

            $data = [];
            if (isset($_POST['name'])) $data['name'] = $_POST['name'];
            if (isset($_POST['description'])) $data['description'] = $_POST['description'];
            if (isset($_POST['color'])) $data['color'] = $_POST['color'];
            if (isset($_POST['icon'])) $data['icon'] = $_POST['icon'];
            if (isset($_POST['sort_order'])) $data['sort_order'] = intval($_POST['sort_order']);
            if (isset($_POST['is_active'])) $data['is_active'] = intval($_POST['is_active']);

            $updated = $locationService->updateLocation($id, $data);
            if (!$updated) {
                finish(false, ['message' => 'Standort nicht aktualisiert']);
            }

            $location = $locationService->getLocation($id);
            finish(true, null, ['location' => $location, 'message' => 'Standort aktualisiert']);
        } catch (Exception $e) {
            finish(false, ['message' => $e->getMessage() ?: 'Fehler beim Aktualisieren des Standorts']);
        }
        break;

    /**
     * DELETE_LOCATION
     * params: id
     * Permission: ASSETS:ASSET_TYPES:CREATE or SETTINGS
     */
    case 'delete_location':
        try {
            if (!$AUTH->serverPermissionCheck("ASSETS:ASSET_TYPES:CREATE") && !$AUTH->serverPermissionCheck("SETTINGS")) {
                finish(false, ['message' => 'Keine Berechtigung']);
            }

            $id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
            if (!$id) {
                finish(false, ['message' => 'Standort-ID erforderlich']);
            }

            $deleted = $locationService->deleteLocation($id);
            if (!$deleted) {
                finish(false, ['message' => 'Standort konnte nicht gelöscht werden']);
            }

            finish(true, null, ['message' => 'Standort gelöscht']);
        } catch (Exception $e) {
            finish(false, ['message' => $e->getMessage() ?: 'Fehler beim Löschen des Standorts']);
        }
        break;

    /**
     * ASSIGN
     * Assign location to a specific entity (asset or stock instance)
     * params: entity_type (asset|stock_instance), entity_id, location_id?, location_custom?, notes?
     */
    case 'assign':
        try {
            $entityType = $_POST['entity_type'] ?? '';
            $entityId = intval($_POST['entity_id'] ?? 0);
            $locationId = isset($_POST['location_id']) ? intval($_POST['location_id']) : null;
            $locationCustom = $_POST['location_custom'] ?? null;
            $notes = $_POST['notes'] ?? null;

            if (!in_array($entityType, ['asset', 'stock_instance'])) {
                finish(false, ['message' => 'Ungültiger Entity-Typ']);
            }

            if (!$entityId) {
                finish(false, ['message' => 'Entity-ID erforderlich']);
            }

            if (is_null($locationId) && empty($locationCustom)) {
                finish(false, ['message' => 'Standort-ID oder custom location erforderlich']);
            }

            $assigned = $locationService->assignLocation(
                $entityType,
                $entityId,
                $locationId,
                $locationCustom,
                $user['users_userid'],
                $notes
            );

            if (!$assigned) {
                finish(false, ['message' => 'Fehler beim Zuweisen des Standorts']);
            }

            $location = $locationService->getEntityLocation($entityType, $entityId);
            finish(true, null, ['location' => $location, 'message' => 'Standort zugewiesen']);
        } catch (Exception $e) {
            finish(false, ['message' => $e->getMessage() ?: 'Fehler beim Zuweisen des Standorts']);
        }
        break;

    /**
     * SCAN_ASSIGN
     * Scan RFID tag, find entity, assign location
     * params: rfid_tag, location_id?, location_custom?, notes?
     */
    case 'scan_assign':
        try {
            $rfidTag = $_POST['rfid_tag'] ?? '';
            $locationId = isset($_POST['location_id']) ? intval($_POST['location_id']) : null;
            $locationCustom = $_POST['location_custom'] ?? null;
            $notes = $_POST['notes'] ?? null;

            if (empty($rfidTag)) {
                finish(false, ['message' => 'RFID-Tag erforderlich']);
            }

            if (is_null($locationId) && empty($locationCustom)) {
                finish(false, ['message' => 'Standort-ID oder custom location erforderlich']);
            }

            $result = $locationService->processLocationScan(
                $rfidTag,
                $locationId,
                $locationCustom,
                $user['users_userid'],
                $notes
            );

            if (!$result['success']) {
                finish(false, ['message' => $result['message']]);
            }

            finish(true, null, [
                'entity_type' => $result['entity_type'],
                'entity' => $result['entity'],
                'location' => $result['location'],
                'message' => $result['message'],
            ]);
        } catch (Exception $e) {
            finish(false, ['message' => $e->getMessage() ?: 'Fehler beim Scan-Zuweisen des Standorts']);
        }
        break;

    /**
     * BULK_ASSIGN
     * Process multiple RFID tags at once
     * params: rfid_tags (JSON array), location_id?, location_custom?, notes?
     */
    case 'bulk_assign':
        try {
            $rfidTagsJson = $_POST['rfid_tags'] ?? '[]';
            $rfidTags = json_decode($rfidTagsJson, true);

            if (!is_array($rfidTags) || empty($rfidTags)) {
                finish(false, ['message' => 'Array von RFID-Tags erforderlich']);
            }

            $locationId = isset($_POST['location_id']) ? intval($_POST['location_id']) : null;
            $locationCustom = $_POST['location_custom'] ?? null;
            $notes = $_POST['notes'] ?? null;

            if (is_null($locationId) && empty($locationCustom)) {
                finish(false, ['message' => 'Standort-ID oder custom location erforderlich']);
            }

            $result = $locationService->bulkLocationScan(
                $rfidTags,
                $locationId,
                $locationCustom,
                $user['users_userid'],
                $notes
            );

            finish(true, null, [
                'total' => $result['total'],
                'success' => $result['success'],
                'errors' => $result['errors'],
                'details' => $result['details'],
                'message' => 'Scan abgeschlossen',
            ]);
        } catch (Exception $e) {
            finish(false, ['message' => $e->getMessage() ?: 'Fehler beim Bulk-Scan']);
        }
        break;

    /**
     * ENTITY_LOCATION
     * Get current location of an entity
     * params: entity_type (asset|stock_instance), entity_id
     */
    case 'entity_location':
        try {
            $entityType = $_POST['entity_type'] ?? $_GET['entity_type'] ?? '';
            $entityId = intval($_POST['entity_id'] ?? $_GET['entity_id'] ?? 0);

            if (!in_array($entityType, ['asset', 'stock_instance'])) {
                finish(false, ['message' => 'Ungültiger Entity-Typ']);
            }

            if (!$entityId) {
                finish(false, ['message' => 'Entity-ID erforderlich']);
            }

            $location = $locationService->getEntityLocation($entityType, $entityId);

            if (!$location) {
                finish(true, null, ['location' => null, 'message' => 'Keine Standort-Information']);
            }

            finish(true, null, ['location' => $location]);
        } catch (Exception $e) {
            finish(false, ['message' => $e->getMessage() ?: 'Fehler beim Abrufen der Standort-Information']);
        }
        break;

    /**
     * LOCATION_HISTORY
     * Get location change history for an entity
     * params: entity_type (asset|stock_instance), entity_id, limit?
     */
    case 'location_history':
        try {
            $entityType = $_POST['entity_type'] ?? $_GET['entity_type'] ?? '';
            $entityId = intval($_POST['entity_id'] ?? $_GET['entity_id'] ?? 0);
            $limit = intval($_POST['limit'] ?? $_GET['limit'] ?? 20);

            if (!in_array($entityType, ['asset', 'stock_instance'])) {
                finish(false, ['message' => 'Ungültiger Entity-Typ']);
            }

            if (!$entityId) {
                finish(false, ['message' => 'Entity-ID erforderlich']);
            }

            if ($limit < 1 || $limit > 100) {
                $limit = 20;
            }

            $history = $locationService->getLocationHistory($entityType, $entityId, $limit);

            finish(true, null, ['history' => $history, 'count' => count($history)]);
        } catch (Exception $e) {
            finish(false, ['message' => $e->getMessage() ?: 'Fehler beim Abrufen der Standort-Historie']);
        }
        break;

    /**
     * ENTITIES_AT_LOCATION
     * Get all entities at a given location
     * params: location_id
     */
    case 'entities_at_location':
        try {
            $locationId = intval($_POST['location_id'] ?? $_GET['location_id'] ?? 0);

            if (!$locationId) {
                finish(false, ['message' => 'Standort-ID erforderlich']);
            }

            $entities = $locationService->getEntitiesAtLocation($locationId);

            finish(true, null, [
                'assets' => $entities['assets'],
                'stock_instances' => $entities['stock_instances'],
                'total' => count($entities['assets']) + count($entities['stock_instances']),
            ]);
        } catch (Exception $e) {
            finish(false, ['message' => $e->getMessage() ?: 'Fehler beim Abrufen der Entitäten']);
        }
        break;

    /**
     * LOCATION_SUMMARY
     * Get location summary with asset and stock counts
     */
    case 'location_summary':
        try {
            $instanceId = $user['instance']['instances_id'] ?? 0;
            if (!$instanceId) {
                finish(false, ['message' => 'Keine Instanz-ID']);
            }

            $summary = $locationService->getLocationSummary($instanceId);
            finish(true, null, ['locations' => $summary, 'count' => count($summary)]);
        } catch (Exception $e) {
            finish(false, ['message' => $e->getMessage() ?: 'Fehler beim Abrufen der Zusammenfassung']);
        }
        break;

    /**
     * BULK_MOVE_ASSETS
     * Move multiple assets to target location
     * params: asset_ids (JSON array), target_location_id, notes?
     */
    case 'bulk_move_assets':
        try {
            if (!$AUTH->serverPermissionCheck("ASSETS:EDIT")) {
                finish(false, ['message' => 'Keine Berechtigung']);
            }

            $assetIdsJson = $_POST['asset_ids'] ?? '[]';
            $assetIds = json_decode($assetIdsJson, true);
            $targetLocationId = intval($_POST['target_location_id'] ?? 0);
            $notes = $_POST['notes'] ?? null;

            if (!is_array($assetIds) || empty($assetIds)) {
                finish(false, ['message' => 'Array von Asset-IDs erforderlich']);
            }

            if (!$targetLocationId) {
                finish(false, ['message' => 'Ziel-Standort-ID erforderlich']);
            }

            $result = $locationService->bulkMoveAssets(
                $assetIds,
                $targetLocationId,
                $user['users_userid'],
                $notes
            );

            $bCMS->auditLog('BULK_MOVE', 'assets', json_encode([
                'batch_id' => $result['batch_id'],
                'count' => $result['total'],
                'success' => $result['success'],
                'target_location' => $targetLocationId,
            ]), $user['users_userid']);

            finish(true, null, array_merge($result, ['message' => 'Massen-Verschiebung abgeschlossen']));
        } catch (Exception $e) {
            finish(false, ['message' => $e->getMessage() ?: 'Fehler beim Verschieben der Assets']);
        }
        break;

    /**
     * BULK_MOVE_STOCK
     * Move multiple stock instances to target location
     * params: stock_instance_ids (JSON array), target_location_id, notes?
     */
    case 'bulk_move_stock':
        try {
            if (!$AUTH->serverPermissionCheck("STOCK:EDIT")) {
                finish(false, ['message' => 'Keine Berechtigung']);
            }

            $stockIdsJson = $_POST['stock_instance_ids'] ?? '[]';
            $stockIds = json_decode($stockIdsJson, true);
            $targetLocationId = intval($_POST['target_location_id'] ?? 0);
            $notes = $_POST['notes'] ?? null;

            if (!is_array($stockIds) || empty($stockIds)) {
                finish(false, ['message' => 'Array von Stock-Instance-IDs erforderlich']);
            }

            if (!$targetLocationId) {
                finish(false, ['message' => 'Ziel-Standort-ID erforderlich']);
            }

            $result = $locationService->bulkMoveStockInstances(
                $stockIds,
                $targetLocationId,
                $user['users_userid'],
                $notes
            );

            $bCMS->auditLog('BULK_MOVE', 'stock_instances', json_encode([
                'batch_id' => $result['batch_id'],
                'count' => $result['total'],
                'success' => $result['success'],
                'target_location' => $targetLocationId,
            ]), $user['users_userid']);

            finish(true, null, array_merge($result, ['message' => 'Massen-Verschiebung abgeschlossen']));
        } catch (Exception $e) {
            finish(false, ['message' => $e->getMessage() ?: 'Fehler beim Verschieben der Bestände']);
        }
        break;

    /**
     * ASSETS_AT_LOCATION
     * Get all assets at a specific location
     * params: location_id
     */
    case 'assets_at_location':
        try {
            $locationId = intval($_POST['location_id'] ?? $_GET['location_id'] ?? 0);
            $instanceId = $user['instance']['instances_id'] ?? 0;

            if (!$locationId) {
                finish(false, ['message' => 'Standort-ID erforderlich']);
            }

            $assets = $locationService->getAssetsAtLocation($locationId, $instanceId);
            finish(true, null, ['assets' => $assets, 'count' => count($assets)]);
        } catch (Exception $e) {
            finish(false, ['message' => $e->getMessage() ?: 'Fehler beim Abrufen der Assets']);
        }
        break;

    /**
     * STOCK_AT_LOCATION
     * Get all stock items at a specific location
     * params: location_id
     */
    case 'stock_at_location':
        try {
            $locationId = intval($_POST['location_id'] ?? $_GET['location_id'] ?? 0);
            $instanceId = $user['instance']['instances_id'] ?? 0;

            if (!$locationId) {
                finish(false, ['message' => 'Standort-ID erforderlich']);
            }

            $stocks = $locationService->getStockAtLocation($locationId, $instanceId);
            finish(true, null, ['stocks' => $stocks, 'count' => count($stocks)]);
        } catch (Exception $e) {
            finish(false, ['message' => $e->getMessage() ?: 'Fehler beim Abrufen der Bestände']);
        }
        break;

    /**
     * CREATE_LOCATION_INLINE
     * Quick-create a new location
     * params: name (required), icon?, description?, instances_id?
     */
    case 'create_location_inline':
        try {
            if (!$AUTH->serverPermissionCheck("ASSETS:ASSET_TYPES:CREATE") && !$AUTH->serverPermissionCheck("SETTINGS")) {
                finish(false, ['message' => 'Keine Berechtigung']);
            }

            $name = $_POST['name'] ?? '';
            $icon = $_POST['icon'] ?? '📦';
            $description = $_POST['description'] ?? null;
            $instanceId = intval($_POST['instances_id'] ?? $user['instance']['instances_id'] ?? 0);

            if (empty($name)) {
                finish(false, ['message' => 'Standortname erforderlich']);
            }

            $id = $locationService->createLocationInline($name, $instanceId, $icon, $description);
            $location = $locationService->getLocation($id);

            $bCMS->auditLog('INSERT', 'locations', json_encode([
                'name' => $name,
                'icon' => $icon,
                'description' => $description,
            ]), $user['users_userid']);

            finish(true, null, ['location' => $location, 'message' => 'Standort erstellt']);
        } catch (Exception $e) {
            finish(false, ['message' => $e->getMessage() ?: 'Fehler beim Erstellen des Standorts']);
        }
        break;

    /**
     * Default / unknown action
     */
    default:
        finish(false, ['message' => 'Unbekannte Aktion: ' . htmlspecialchars($action)]);
}
