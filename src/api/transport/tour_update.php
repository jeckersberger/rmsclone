<?php
/**
 * Update Tour or Tour Status
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
    $tourId = InputValidationService::positiveInt($_POST['id'] ?? 0);

    if (!$tourId) {
        finish(false, ["code" => "INVALID_PARAM", "message" => "Tour ID erforderlich"]);
    }

    $data = [];

    if (isset($_POST['status'])) {
        $status = InputValidationService::enum($_POST['status'], ['planned', 'loading', 'in_transit', 'delivering', 'completed', 'cancelled']);
        $success = $service->updateTourStatus($tourId, $status);
        finish($success, $success ? null : ["code" => "UPDATE_FAILED", "message" => "Status konnte nicht aktualisiert werden"]);
    }

    // Update tour fields
    if (isset($_POST['name'])) {
        $data['name'] = InputValidationService::string($_POST['name'], 1, 200);
    }
    if (isset($_POST['date'])) {
        $data['date'] = InputValidationService::string($_POST['date'], 10, 10);
    }
    if (isset($_POST['driver_id'])) {
        $data['driver_id'] = intval($_POST['driver_id']) ?: null;
    }
    if (isset($_POST['vehicle_id'])) {
        $data['vehicle_id'] = intval($_POST['vehicle_id']) ?: null;
    }
    if (isset($_POST['total_distance_km'])) {
        $data['total_distance_km'] = floatval($_POST['total_distance_km']);
    }
    if (isset($_POST['total_cost'])) {
        $data['total_cost'] = floatval($_POST['total_cost']);
    }
    if (isset($_POST['notes'])) {
        $data['notes'] = InputValidationService::string($_POST['notes'], 0, 5000) ?: null;
    }

    if (empty($data)) {
        finish(false, ["code" => "NO_DATA", "message" => "Keine Daten zum Aktualisieren"]);
    }

    $success = $service->updateTour($tourId, $data);
    finish($success, $success ? null : ["code" => "UPDATE_FAILED", "message" => "Tour konnte nicht aktualisiert werden"]);
}, 'Transport');
