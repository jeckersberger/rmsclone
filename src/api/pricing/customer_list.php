<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/PricingEngineService.php';

$instanceId = $AUTH->data['instance']['instances_id'];

// GET: Get customer price list
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!$AUTH->instancePermissionCheck("PRICING:VIEW")) {
        finish(false, ["message" => "Permission denied"]);
    }

    $clientId = intval($_GET['client_id'] ?? 0);
    if (!$clientId) {
        finish(false, ["message" => "client_id required"]);
    }

    $service = new PricingEngineService($DBLIB);
    $list = $service->getCustomerPriceList($clientId, $instanceId);

    finish($list ? true : false, null, $list ? ['list' => $list] : null);
}

// POST: Save customer price list
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$AUTH->instancePermissionCheck("PRICING:EDIT")) {
        finish(false, ["message" => "Permission denied"]);
    }

    $clientId = intval($_POST['client_id'] ?? 0);
    if (!$clientId) {
        finish(false, ["message" => "client_id required"]);
    }

    $items = json_decode($_POST['items'] ?? '[]', true);
    $globalDiscount = !empty($_POST['global_discount']) ? floatval($_POST['global_discount']) : null;

    if (!is_array($items) || empty($items)) {
        finish(false, ["message" => "items array required"]);
    }

    $service = new PricingEngineService($DBLIB);
    $id = $service->saveCustomerPriceList($clientId, $items, $globalDiscount, $instanceId);

    finish(true, null, ['id' => $id]);
}
