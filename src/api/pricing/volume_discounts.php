<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/PricingEngineService.php';

$instanceId = $AUTH->data['instance']['instances_id'];

// GET: List volume discounts
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!$AUTH->instancePermissionCheck("PRICING:VIEW")) {
        finish(false, ["message" => "Permission denied"]);
    }

    $service = new PricingEngineService($DBLIB);
    $discounts = $service->getVolumeDiscounts($instanceId);

    finish(true, null, ['discounts' => $discounts]);
}

// POST: Create/Update volume discount
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$AUTH->instancePermissionCheck("PRICING:EDIT")) {
        finish(false, ["message" => "Permission denied"]);
    }

    $data = [
        'id' => intval($_POST['id'] ?? 0) ?: null,
        'min_quantity' => intval($_POST['min_quantity'] ?? 1),
        'max_quantity' => !empty($_POST['max_quantity']) ? intval($_POST['max_quantity']) : null,
        'discount_percent' => floatval($_POST['discount_percent'] ?? 0),
        'instances_id' => $instanceId,
    ];

    if (!$data['min_quantity']) {
        finish(false, ["message" => "min_quantity required"]);
    }

    $service = new PricingEngineService($DBLIB);
    $id = $service->saveVolumeDiscount($data);

    finish(true, null, ['id' => $id]);
}
