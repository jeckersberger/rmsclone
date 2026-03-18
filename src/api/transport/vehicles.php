<?php
/**
 * Transport Vehicles API - GET/POST
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
        // Get vehicles
        $activeOnly = isset($_GET['all']) ? false : true;
        $vehicles = $service->getVehicles($instanceId, $activeOnly);
        finish(true, null, ['vehicles' => $vehicles]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$AUTH->instancePermissionCheck("TRANSPORT:EDIT")) {
            finish(false, ["code" => "FORBIDDEN", "message" => "Keine Berechtigung zum Bearbeiten"]);
        }

        $action = InputValidationService::string($_POST['action'] ?? '', 1, 50);

        switch ($action) {
            case 'create':
                $name = InputValidationService::string($_POST['name'] ?? '', 1, 100);
                $type = InputValidationService::enum($_POST['type'] ?? '', ['van', 'truck', 'trailer', 'car']);
                $licensePlate = InputValidationService::string($_POST['license_plate'] ?? '', 0, 20);
                $maxWeight = isset($_POST['max_weight_kg']) ? intval($_POST['max_weight_kg']) : null;
                $cargoVolume = isset($_POST['cargo_volume_m3']) ? floatval($_POST['cargo_volume_m3']) : null;
                $notes = InputValidationService::string($_POST['notes'] ?? '', 0, 5000);
                $isActive = isset($_POST['is_active']) ? (bool)$_POST['is_active'] : true;

                $data = [
                    'instances_id' => $instanceId,
                    'name' => $name,
                    'type' => $type,
                    'license_plate' => $licensePlate ?: null,
                    'max_weight_kg' => $maxWeight,
                    'cargo_volume_m3' => $cargoVolume,
                    'notes' => $notes ?: null,
                    'is_active' => $isActive,
                ];

                $vehicleId = $service->createVehicle($data);
                finish(true, null, ['vehicle_id' => $vehicleId, 'id' => $vehicleId]);
                break;

            default:
                finish(false, ["code" => "INVALID_ACTION", "message" => "Unbekannte Aktion"]);
        }
    }
}, 'Transport');
