<?php
/**
 * Get Single Tour with Stops and Costs
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
    $tourId = InputValidationService::positiveInt($_GET['id'] ?? 0);

    if (!$tourId) {
        finish(false, ["code" => "INVALID_PARAM", "message" => "Tour ID erforderlich"]);
    }

    $tour = $service->getTour($tourId);
    if (!$tour) {
        finish(false, ["code" => "NOT_FOUND", "message" => "Tour nicht gefunden"]);
    }

    finish(true, null, ['tour' => $tour]);
}, 'Transport');
