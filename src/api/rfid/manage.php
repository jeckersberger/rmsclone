<?php
/**
 * RFID Management API
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/RfidService.php';
require_once __DIR__ . '/../../services/ErrorHandlerService.php';
require_once __DIR__ . '/../../services/InputValidationService.php';

ErrorHandlerService::wrap(function () use ($DBLIB, $AUTH) {
    if (!$AUTH->instancePermissionCheck("ASSETS:VIEW")) {
        finish(false, ["code" => "FORBIDDEN", "message" => "Keine Berechtigung"]);
    }

    $service = new RfidService($DBLIB);
    $action = InputValidationService::string($_POST['action'] ?? '', 1, 50);
    $instanceId = (int) $AUTH->data['instance']['instances_id'];

    switch ($action) {
        case 'assign_tag':
            if (!$AUTH->instancePermissionCheck("ASSETS:EDIT")) finish(false, ["code" => "FORBIDDEN"]);
            $assetId = InputValidationService::positiveInt($_POST['asset_id'] ?? 0);
            $tagEpc = InputValidationService::string($_POST['tag_epc'] ?? '', 1, 48);
            $result = $service->assignTag($assetId, $tagEpc, $AUTH->data['users_userid']);
            finish($result['success'], $result['success'] ? null : ["code" => "ERROR", "message" => $result['error']], $result);
            break;

        case 'find_asset':
            $tagEpc = InputValidationService::string($_POST['tag_epc'] ?? '', 1, 48);
            $result = $service->findAssetByTag($tagEpc);
            finish((bool) $result, null, $result ?: []);
            break;

        case 'bulk_scan':
            $tags = json_decode($_POST['tags'] ?? '[]', true) ?: [];
            $gatewayId = InputValidationService::string($_POST['gateway_id'] ?? '', 1, 50);
            $result = $service->processBulkScan($tags, $gatewayId, $instanceId);
            finish(true, null, $result);
            break;

        case 'inventory_check':
            $tags = json_decode($_POST['tags'] ?? '[]', true) ?: [];
            $result = $service->inventoryCheck($tags, $instanceId);
            finish(true, null, $result);
            break;

        case 'register_gateway':
            if (!$AUTH->instancePermissionCheck("INSTANCES:EDIT")) finish(false, ["code" => "FORBIDDEN"]);
            $gatewayId = InputValidationService::string($_POST['gateway_id'] ?? '', 1, 50);
            $name = InputValidationService::string($_POST['name'] ?? '', 1, 100);
            $location = InputValidationService::string($_POST['location'] ?? '', 0, 200);
            $result = $service->registerGateway($instanceId, $gatewayId, $name, $location);
            finish($result['success'], $result['success'] ? null : ["code" => "ERROR", "message" => $result['error']], $result);
            break;

        case 'print_label':
            $assetId = InputValidationService::positiveInt($_POST['asset_id'] ?? 0);
            $zpl = $service->generateRfidLabel($assetId);
            if (!$zpl) finish(false, ["code" => "NOT_FOUND", "message" => "Asset nicht gefunden"]);
            finish(true, null, ['zpl' => $zpl]);
            break;

        default:
            finish(false, ["code" => "INVALID_ACTION", "message" => "Unbekannte Aktion"]);
    }
}, 'RFID');
