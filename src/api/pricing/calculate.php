<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/PricingEngineService.php';

if (!$AUTH->instancePermissionCheck("PRICING:VIEW")) {
    finish(false, ["message" => "Permission denied"]);
}

$assetTypeId = intval($_POST['asset_type_id'] ?? 0);
$days = intval($_POST['days'] ?? 1);
$quantity = intval($_POST['quantity'] ?? 1);
$clientId = intval($_POST['client_id'] ?? 0) ?: null;
$startDate = $_POST['start_date'] ?? null;
$instanceId = $AUTH->data['instance']['instances_id'];

if (!$assetTypeId || !$days) {
    finish(false, ["message" => "asset_type_id and days required"]);
}

$service = new PricingEngineService($DBLIB);
$result = $service->calculatePrice($assetTypeId, $days, $quantity, $clientId, $startDate, $instanceId);

finish(true, null, [
    'base_price' => round($result->base_price, 2),
    'tier_discount' => round($result->tier_discount, 2),
    'volume_discount' => round($result->volume_discount, 2),
    'seasonal_surcharge' => round($result->seasonal_surcharge, 2),
    'customer_discount' => round($result->customer_discount, 2),
    'final_price' => round($result->final_price, 2),
    'breakdown' => $result->breakdown,
]);
