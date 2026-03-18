<?php
/**
 * Complete Tour Stop with Signature/Photo
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/TransportLogisticsService.php';
require_once __DIR__ . '/../../services/ErrorHandlerService.php';
require_once __DIR__ . '/../../services/InputValidationService.php';

ErrorHandlerService::wrap(function () use ($DBLIB, $AUTH) {
    if (!$AUTH->instancePermissionCheck("TRANSPORT:DRIVE")) {
        finish(false, ["code" => "FORBIDDEN", "message" => "Keine Berechtigung"]);
    }

    $service = new TransportLogisticsService($DBLIB);
    $stopId = InputValidationService::positiveInt($_POST['id'] ?? 0);
    $action = InputValidationService::string($_POST['action'] ?? 'complete', 1, 50);

    if (!$stopId) {
        finish(false, ["code" => "INVALID_PARAM", "message" => "Stop ID erforderlich"]);
    }

    switch ($action) {
        case 'arrive':
            $success = $service->arriveAtStop($stopId);
            finish($success, $success ? null : ["code" => "UPDATE_FAILED", "message" => "Ankunft konnte nicht registriert werden"]);
            break;

        case 'complete':
            $signatureData = isset($_POST['signature_data']) ? filter_var($_POST['signature_data'], FILTER_SANITIZE_STRING) : null;
            $photoPath = isset($_POST['photo_path']) ? filter_var($_POST['photo_path'], FILTER_SANITIZE_STRING) : null;

            $success = $service->completeStop($stopId, $signatureData, $photoPath);
            finish($success, $success ? null : ["code" => "UPDATE_FAILED", "message" => "Haltestelle konnte nicht abgeschlossen werden"]);
            break;

        default:
            finish(false, ["code" => "INVALID_ACTION", "message" => "Unbekannte Aktion"]);
    }
}, 'Transport');
