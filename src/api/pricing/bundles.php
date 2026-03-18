<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/PricingEngineService.php';

$instanceId = $AUTH->data['instance']['instances_id'];

// GET: List bundles
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!$AUTH->instancePermissionCheck("PRICING:VIEW")) {
        finish(false, ["message" => "Permission denied"]);
    }

    $bundleId = intval($_GET['id'] ?? 0);

    $service = new PricingEngineService($DBLIB);

    if ($bundleId) {
        // Get single bundle with items
        $bundle = $service->getBundle($bundleId);
        finish($bundle ? true : false, null, $bundle ? ['bundle' => $bundle] : null);
    } else {
        // List all bundles
        $bundles = $service->getBundles($instanceId);
        finish(true, null, ['bundles' => $bundles]);
    }
}

// POST: Create/Update bundle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$AUTH->instancePermissionCheck("PRICING:EDIT")) {
        finish(false, ["message" => "Permission denied"]);
    }

    $bundleData = [
        'id' => intval($_POST['id'] ?? 0) ?: null,
        'name' => trim($_POST['name'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'bundle_price_per_day' => floatval($_POST['bundle_price_per_day'] ?? 0),
        'is_active' => !empty($_POST['is_active']),
        'instances_id' => $instanceId,
    ];

    if (!$bundleData['name'] || !$bundleData['bundle_price_per_day']) {
        finish(false, ["message" => "name and bundle_price_per_day required"]);
    }

    $items = json_decode($_POST['items'] ?? '[]', true);
    if (!is_array($items) || empty($items)) {
        finish(false, ["message" => "items array required"]);
    }

    $service = new PricingEngineService($DBLIB);
    $id = $service->saveBundle($bundleData, $items);

    finish(true, null, ['id' => $id]);
}
