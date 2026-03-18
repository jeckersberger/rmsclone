<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/PricingEngineService.php';

if (!$AUTH->instancePermissionCheck("PRICING:EDIT")) {
    finish(false, ["message" => "Permission denied"]);
}

$id = intval($_POST['id'] ?? 0);
if (!$id) {
    finish(false, ["message" => "id required"]);
}

$service = new PricingEngineService($DBLIB);
$success = $service->deleteSurcharge($id);

finish($success, null, ['success' => $success]);
