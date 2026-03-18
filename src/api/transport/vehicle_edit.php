<?php
/**
 * Transport Vehicle Edit API - POST
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/TransportLogisticsService.php';
require_once __DIR__ . '/../../services/ErrorHandlerService.php';
require_once __DIR__ . '/../../services/InputValidationService.php';

ErrorHandlerService::wrap(function () use ($DBLIB, $AUTH) {
    if (!$AUTH->instancePermissionCheck("TRANSPORT:EDIT")) {
        finish(false, ["code" => "FORBIDDEN", "message" => "Keine Berechtigung"]);
    }

    $service = new TransportLogisticsService($DBLIB);
    $vehicleId = InputValidationService::positiveInt($_POST['id'] ?? 0);

    if (!$vehicleId) {
        finish(false, ["code" => "INVALID_PARAM", "message" => "Vehicle ID erforderlich"]);
    }

    $data = [];

    if (isset($_POST['name'])) {
        $data['name'] = InputValidationService::string($_POST['name'], 1, 100);
    }
    if (isset($_POST['type'])) {
        $data['type'] = InputValidationService::enum($_POST['type'], ['van', 'truck', 'trailer', 'car']);
    }
    if (isset($_POST['license_plate'])) {
        $data['license_plate'] = InputValidationService::string($_POST['license_plate'], 0, 20) ?: null;
    }
    if (isset($_POST['max_weight_kg'])) {
        $data['max_weight_kg'] = intval($_POST['max_weight_kg']);
    }
    if (isset($_POST['cargo_volume_m3'])) {
        $data['cargo_volume_m3'] = floatval($_POST['cargo_volume_m3']);
    }
    if (isset($_POST['notes'])) {
        $data['notes'] = InputValidationService::string($_POST['notes'], 0, 5000) ?: null;
    }
    if (isset($_POST['is_active'])) {
        $data['is_active'] = (bool)$_POST['is_active'];
    }

    if (empty($data)) {
        finish(false, ["code" => "NO_DATA", "message" => "Keine Daten zum Aktualisieren"]);
    }

    $success = $service->updateVehicle($vehicleId, $data);
    finish($success, $success ? null : ["code" => "UPDATE_FAILED", "message" => "Fahrzeug konnte nicht aktualisiert werden"]);
}, 'Transport');
