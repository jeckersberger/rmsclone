<?php
/**
 * Add Stop to Tour
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
    $tourId = InputValidationService::positiveInt($_POST['tour_id'] ?? 0);

    if (!$tourId) {
        finish(false, ["code" => "INVALID_PARAM", "message" => "Tour ID erforderlich"]);
    }

    $type = InputValidationService::enum($_POST['type'] ?? 'delivery', ['pickup', 'delivery', 'return']);
    $stopOrder = isset($_POST['stop_order']) ? intval($_POST['stop_order']) : 1;
    $projectId = isset($_POST['project_id']) ? intval($_POST['project_id']) : null;
    $clientId = isset($_POST['client_id']) ? intval($_POST['client_id']) : null;
    $address = InputValidationService::string($_POST['address'] ?? '', 0, 5000);
    $timeWindowStart = isset($_POST['time_window_start']) ? filter_var($_POST['time_window_start'], FILTER_SANITIZE_STRING) : null;
    $timeWindowEnd = isset($_POST['time_window_end']) ? filter_var($_POST['time_window_end'], FILTER_SANITIZE_STRING) : null;
    $notes = InputValidationService::string($_POST['notes'] ?? '', 0, 5000);

    $data = [
        'stop_order' => $stopOrder,
        'type' => $type,
        'project_id' => $projectId,
        'client_id' => $clientId,
        'address' => $address ?: null,
        'time_window_start' => $timeWindowStart,
        'time_window_end' => $timeWindowEnd,
        'notes' => $notes ?: null,
    ];

    $stopId = $service->addStop($tourId, $data);
    finish(true, null, ['stop_id' => $stopId, 'id' => $stopId]);
}, 'Transport');
