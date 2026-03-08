<?php
/**
 * Shipping Management API - DHL/DPD Versand
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/ShippingService.php';
require_once __DIR__ . '/../../services/ErrorHandlerService.php';
require_once __DIR__ . '/../../services/InputValidationService.php';

ErrorHandlerService::wrap(function () use ($DBLIB, $AUTH) {
    if (!$AUTH->instancePermissionCheck("PROJECTS:EDIT")) {
        finish(false, ["code" => "FORBIDDEN", "message" => "Keine Berechtigung"]);
    }

    $service = new ShippingService($DBLIB);
    $action = InputValidationService::string($_POST['action'] ?? '', 1, 50);

    switch ($action) {
        case 'create':
            $provider = InputValidationService::enum($_POST['provider'] ?? '', ['dhl', 'dpd']);
            $recipient = [
                'name' => InputValidationService::string($_POST['recipient_name'] ?? '', 1, 100),
                'street' => InputValidationService::string($_POST['recipient_street'] ?? '', 1, 200),
                'zip' => InputValidationService::string($_POST['recipient_zip'] ?? '', 1, 10),
                'city' => InputValidationService::string($_POST['recipient_city'] ?? '', 1, 100),
                'country' => InputValidationService::string($_POST['recipient_country'] ?? 'DEU', 2, 3),
            ];
            $parcel = [
                'weight' => floatval($_POST['weight'] ?? 5),
                'length' => intval($_POST['length'] ?? 60),
                'width' => intval($_POST['width'] ?? 40),
                'height' => intval($_POST['height'] ?? 30),
            ];
            $projectId = intval($_POST['project_id'] ?? 0);
            $result = $service->createShipment($provider, $recipient, $parcel, $projectId);
            finish($result['success'], $result['success'] ? null : ["code" => "ERROR", "message" => $result['error']], $result);
            break;

        case 'track':
            $trackingNumber = InputValidationService::string($_POST['tracking_number'] ?? '', 1, 50);
            $provider = InputValidationService::enum($_POST['provider'] ?? 'dhl', ['dhl', 'dpd']);
            $result = $service->trackShipment($trackingNumber, $provider);
            finish($result['success'], $result['success'] ? null : ["code" => "ERROR", "message" => $result['error'] ?? ''], $result);
            break;

        case 'list':
            $projectId = InputValidationService::positiveInt($_POST['project_id'] ?? 0);
            $result = $service->getProjectShipments($projectId);
            finish(true, null, ['shipments' => $result]);
            break;

        default:
            finish(false, ["code" => "INVALID_ACTION", "message" => "Unbekannte Aktion"]);
    }
}, 'Shipping');
