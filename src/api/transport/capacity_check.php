<?php
/**
 * Vehicle Capacity Check API
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
    $vehicleId = InputValidationService::positiveInt($_GET['vehicle_id'] ?? 0);
    $requiredWeight = floatval($_GET['weight_kg'] ?? 0);
    $requiredVolume = floatval($_GET['volume_m3'] ?? 0);

    if (!$vehicleId) {
        finish(false, ["code" => "INVALID_PARAM", "message" => "Vehicle ID erforderlich"]);
    }

    $vehicle = $service->getVehicle($vehicleId);
    if (!$vehicle) {
        finish(false, ["code" => "NOT_FOUND", "message" => "Fahrzeug nicht gefunden"]);
    }

    $canFit = $service->checkVehicleCapacity($vehicleId, $requiredWeight, $requiredVolume);

    $response = [
        'vehicle' => $vehicle,
        'can_fit' => $canFit,
        'warnings' => [],
    ];

    if ($vehicle['max_weight_kg'] && $requiredWeight > $vehicle['max_weight_kg']) {
        $response['warnings'][] = "Gewicht überschreitet Kapazität: {$requiredWeight}kg > {$vehicle['max_weight_kg']}kg";
    }
    if ($vehicle['cargo_volume_m3'] && $requiredVolume > $vehicle['cargo_volume_m3']) {
        $response['warnings'][] = "Volumen überschreitet Kapazität: {$requiredVolume}m³ > {$vehicle['cargo_volume_m3']}m³";
    }

    finish(true, null, $response);
}, 'Transport');
