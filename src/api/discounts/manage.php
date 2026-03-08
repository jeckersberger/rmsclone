<?php
/**
 * Discount Codes API
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/DiscountCodeService.php';
require_once __DIR__ . '/../../services/ErrorHandlerService.php';
require_once __DIR__ . '/../../services/InputValidationService.php';

ErrorHandlerService::wrap(function () use ($DBLIB, $AUTH) {
    $service = new DiscountCodeService($DBLIB);
    $action = InputValidationService::string($_POST['action'] ?? '', 1, 50);
    $instanceId = (int) $AUTH->data['instance']['instances_id'];

    switch ($action) {
        case 'list':
            if (!$AUTH->instancePermissionCheck("INSTANCES:EDIT")) finish(false, ["code" => "FORBIDDEN"]);
            $activeOnly = (bool) ($_POST['active_only'] ?? false);
            finish(true, null, ['codes' => $service->list($instanceId, $activeOnly)]);
            break;

        case 'create':
            if (!$AUTH->instancePermissionCheck("INSTANCES:EDIT")) finish(false, ["code" => "FORBIDDEN"]);
            $result = $service->create($instanceId, $_POST);
            finish($result['success'], $result['success'] ? null : ["code" => "ERROR", "message" => $result['error']], $result);
            break;

        case 'validate':
            $code = InputValidationService::string($_POST['code'] ?? '', 1, 30);
            $orderValue = floatval($_POST['order_value'] ?? 0);
            $assetTypeId = intval($_POST['asset_type_id'] ?? 0) ?: null;
            $result = $service->validate($code, $instanceId, $orderValue, $assetTypeId);
            finish($result['valid'], $result['valid'] ? null : ["code" => "INVALID", "message" => $result['error']], $result);
            break;

        case 'deactivate':
            if (!$AUTH->instancePermissionCheck("INSTANCES:EDIT")) finish(false, ["code" => "FORBIDDEN"]);
            $id = InputValidationService::positiveInt($_POST['id'] ?? 0);
            finish($service->deactivate($id, $instanceId));
            break;

        case 'delete':
            if (!$AUTH->instancePermissionCheck("INSTANCES:EDIT")) finish(false, ["code" => "FORBIDDEN"]);
            $id = InputValidationService::positiveInt($_POST['id'] ?? 0);
            finish($service->delete($id, $instanceId));
            break;

        default:
            finish(false, ["code" => "INVALID_ACTION", "message" => "Unbekannte Aktion"]);
    }
}, 'Discount Codes');
