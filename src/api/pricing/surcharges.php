<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/PricingEngineService.php';

$instanceId = $AUTH->data['instance']['instances_id'];

// GET: List surcharges
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!$AUTH->instancePermissionCheck("PRICING:VIEW")) {
        finish(false, ["message" => "Permission denied"]);
    }

    $service = new PricingEngineService($DBLIB);
    $surcharges = $service->getSeasonalSurcharges($instanceId);

    finish(true, null, ['surcharges' => $surcharges]);
}

// POST: Create/Update surcharge
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$AUTH->instancePermissionCheck("PRICING:EDIT")) {
        finish(false, ["message" => "Permission denied"]);
    }

    $data = [
        'id' => intval($_POST['id'] ?? 0) ?: null,
        'name' => trim($_POST['name'] ?? ''),
        'start_month' => intval($_POST['start_month'] ?? 1),
        'start_day' => intval($_POST['start_day'] ?? 1),
        'end_month' => intval($_POST['end_month'] ?? 12),
        'end_day' => intval($_POST['end_day'] ?? 31),
        'surcharge_percent' => floatval($_POST['surcharge_percent'] ?? 0),
        'instances_id' => $instanceId,
    ];

    if (!$data['name']) {
        finish(false, ["message" => "name required"]);
    }

    $service = new PricingEngineService($DBLIB);
    $id = $service->saveSurcharge($data);

    finish(true, null, ['id' => $id]);
}
