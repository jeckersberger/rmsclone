<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/CustomerPricingService.php';

if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_FINANCE:EDIT")) finish(false, ["message" => "Permission denied"]);

$clientId = intval($_POST['client_id'] ?? 0);
if (!$clientId) finish(false, ["message" => "client_id required"]);

$assetTypeId = !empty($_POST['asset_type_id']) ? intval($_POST['asset_type_id']) : null;

$data = [
    'rule_name' => $_POST['rule_name'] ?? null,
    'day_rate' => isset($_POST['day_rate']) ? floatval($_POST['day_rate']) : null,
    'week_rate' => isset($_POST['week_rate']) ? floatval($_POST['week_rate']) : null,
    'discount_pct' => floatval($_POST['discount_pct'] ?? 0),
    'min_days' => isset($_POST['min_days']) ? intval($_POST['min_days']) : null,
];

$service = new CustomerPricingService($DBLIB);
$id = $service->setRule($clientId, $assetTypeId, $data);

finish($id > 0, null, ['id' => $id]);
