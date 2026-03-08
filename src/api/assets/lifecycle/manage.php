<?php
/**
 * Equipment Lifecycle API - Lebenszyklus-Verwaltung
 */
require_once __DIR__ . '/../../apiHeadSecure.php';
require_once __DIR__ . '/../../../services/EquipmentLifecycleService.php';
require_once __DIR__ . '/../../../services/ErrorHandlerService.php';
require_once __DIR__ . '/../../../services/InputValidationService.php';

ErrorHandlerService::wrap(function () use ($DBLIB, $AUTH) {
    if (!$AUTH->instancePermissionCheck("ASSETS:VIEW")) {
        finish(false, ["code" => "FORBIDDEN", "message" => "Keine Berechtigung"]);
    }

    $service = new EquipmentLifecycleService($DBLIB);
    $action = InputValidationService::string($_POST['action'] ?? '', 1, 50);
    $instanceId = (int) $AUTH->data['instance']['instances_id'];

    switch ($action) {
        case 'status':
            $assetId = InputValidationService::positiveInt($_POST['asset_id'] ?? 0);
            $result = $service->getStatus($assetId);
            if (!$result) finish(false, ["code" => "NOT_FOUND", "message" => "Asset nicht gefunden"]);
            finish(true, null, $result);
            break;

        case 'transition':
            if (!$AUTH->instancePermissionCheck("ASSETS:EDIT")) {
                finish(false, ["code" => "FORBIDDEN", "message" => "Keine Berechtigung"]);
            }
            $assetId = InputValidationService::positiveInt($_POST['asset_id'] ?? 0);
            $newStatus = InputValidationService::string($_POST['new_status'] ?? '', 1, 30);
            $data = [
                'notes' => $_POST['notes'] ?? '',
                'purchase_price' => $_POST['purchase_price'] ?? null,
                'sale_price' => $_POST['sale_price'] ?? null,
            ];
            $result = $service->transition($assetId, $newStatus, $AUTH->data['users_userid'], $data);
            finish($result['success'], $result['success'] ? null : ["code" => "TRANSITION_FAILED", "message" => $result['error']], $result);
            break;

        case 'history':
            $assetId = InputValidationService::positiveInt($_POST['asset_id'] ?? 0);
            $result = $service->getHistory($assetId);
            finish(true, null, ['entries' => $result]);
            break;

        case 'by_status':
            $status = InputValidationService::string($_POST['status'] ?? '', 1, 30);
            $result = $service->getByStatus($status, $instanceId);
            finish(true, null, ['assets' => $result]);
            break;

        case 'summary':
            $result = $service->getSummary($instanceId);
            finish(true, null, ['summary' => $result]);
            break;

        default:
            finish(false, ["code" => "INVALID_ACTION", "message" => "Unbekannte Aktion"]);
    }
}, 'Equipment Lifecycle');
