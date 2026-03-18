<?php
/**
 * Transport Drivers API - GET/POST
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/TransportLogisticsService.php';
require_once __DIR__ . '/../../services/ErrorHandlerService.php';
require_once __DIR__ . '/../../services/InputValidationService.php';

ErrorHandlerService::wrap(function () use ($DBLIB, $AUTH) {
    if (!$AUTH->instancePermissionCheck("TRANSPORT:VIEW")) {
        finish(false, ["code" => "FORBIDDEN", "message" => "Keine Berechtigung"]);
    }

    $service = new TransportLogisticsService($DBLIB);
    $instanceId = $AUTH->data['instance']['instances_id'];

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get drivers
        $drivers = $service->getDrivers($instanceId);
        finish(true, null, ['drivers' => $drivers]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$AUTH->instancePermissionCheck("TRANSPORT:EDIT")) {
            finish(false, ["code" => "FORBIDDEN", "message" => "Keine Berechtigung zum Bearbeiten"]);
        }

        $action = InputValidationService::string($_POST['action'] ?? '', 1, 50);

        switch ($action) {
            case 'create':
                $userId = InputValidationService::positiveInt($_POST['users_userid'] ?? 0);
                $licenseTypes = InputValidationService::string($_POST['license_types'] ?? '', 0, 100);
                $phone = InputValidationService::string($_POST['phone'] ?? '', 0, 20);
                $isAvailable = isset($_POST['is_available']) ? (bool)$_POST['is_available'] : true;

                if (!$userId) {
                    finish(false, ["code" => "INVALID_USER", "message" => "Benutzer erforderlich"]);
                }

                $data = [
                    'instances_id' => $instanceId,
                    'users_userid' => $userId,
                    'license_types' => $licenseTypes ?: null,
                    'phone' => $phone ?: null,
                    'is_available' => $isAvailable,
                ];

                $driverId = $service->createDriver($data);
                finish(true, null, ['driver_id' => $driverId, 'id' => $driverId]);
                break;

            default:
                finish(false, ["code" => "INVALID_ACTION", "message" => "Unbekannte Aktion"]);
        }
    }
}, 'Transport');
