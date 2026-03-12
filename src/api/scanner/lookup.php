<?php
/**
 * Scanner Lookup API - Asset per Barcode/QR-Code finden
 * Fuer Browser-Scanner und Android-App
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/BarcodeScannerService.php';
require_once __DIR__ . '/../../services/ErrorHandlerService.php';
require_once __DIR__ . '/../../services/InputValidationService.php';

ErrorHandlerService::wrap(function () use ($DBLIB, $AUTH) {
    if (!$AUTH->instancePermissionCheck("ASSETS:VIEW")) {
        finish(false, ["code" => "FORBIDDEN", "message" => "Keine Berechtigung"]);
    }

    $service = new BarcodeScannerService($DBLIB);
    $action = InputValidationService::string($_POST['action'] ?? 'lookup', 1, 50);
    $instanceId = (int) $AUTH->data['instance']['instances_id'];

    switch ($action) {
        case 'lookup':
            $code = InputValidationService::string($_POST['code'] ?? '', 1, 200);
            $result = $service->lookupByCode($code, $instanceId);
            finish($result['found'], $result['found'] ? null : ["code" => "NOT_FOUND", "message" => $result['error']], $result);
            break;

        case 'bulk':
            $codes = json_decode($_POST['codes'] ?? '[]', true) ?: [];
            $result = $service->bulkLookup($codes, $instanceId);
            finish(true, null, $result);
            break;

        case 'scanner_js':
            // JavaScript fuer Browser-Scanner liefern
            header('Content-Type: application/javascript');
            echo BarcodeScannerService::getScannerScript();
            exit;

        default:
            finish(false, ["code" => "INVALID_ACTION", "message" => "Unbekannte Aktion"]);
    }
}, 'Scanner Lookup');
