<?php
/**
 * Transport Dashboard Statistics API
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/TransportLogisticsService.php';
require_once __DIR__ . '/../../services/ErrorHandlerService.php';

ErrorHandlerService::wrap(function () use ($DBLIB, $AUTH) {
    if (!$AUTH->instancePermissionCheck("TRANSPORT:VIEW")) {
        finish(false, ["code" => "FORBIDDEN", "message" => "Keine Berechtigung"]);
    }

    $service = new TransportLogisticsService($DBLIB);
    $instanceId = $AUTH->data['instance']['instances_id'];

    $stats = $service->getDashboardStats($instanceId);

    finish(true, null, ['stats' => $stats]);
}, 'Transport');
