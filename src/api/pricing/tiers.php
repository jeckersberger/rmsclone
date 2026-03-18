<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/PricingEngineService.php';

$instanceId = $AUTH->data['instance']['instances_id'];

// GET: List tiers
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!$AUTH->instancePermissionCheck("PRICING:VIEW")) {
        finish(false, ["message" => "Permission denied"]);
    }

    $assetTypeId = intval($_GET['asset_type_id'] ?? 0);
    if (!$assetTypeId) {
        finish(false, ["message" => "asset_type_id required"]);
    }

    $service = new PricingEngineService($DBLIB);
    $tiers = $service->getTiersForAssetType($assetTypeId, $instanceId);

    finish(true, null, ['tiers' => $tiers]);
}

// POST: Create/Update tier
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$AUTH->instancePermissionCheck("PRICING:EDIT")) {
        finish(false, ["message" => "Permission denied"]);
    }

    $data = [
        'id' => intval($_POST['id'] ?? 0) ?: null,
        'asset_type_id' => intval($_POST['asset_type_id'] ?? 0),
        'min_days' => intval($_POST['min_days'] ?? 1),
        'max_days' => !empty($_POST['max_days']) ? intval($_POST['max_days']) : null,
        'price_per_day' => floatval($_POST['price_per_day'] ?? 0),
        'instances_id' => $instanceId,
    ];

    if (!$data['asset_type_id'] || !$data['price_per_day']) {
        finish(false, ["message" => "asset_type_id and price_per_day required"]);
    }

    $service = new PricingEngineService($DBLIB);
    $id = $service->saveTier($data);

    finish(true, null, ['id' => $id]);
}
