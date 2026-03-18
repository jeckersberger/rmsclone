<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/InsuranceService.php';

if (!$AUTH->instancePermissionCheck("INSURANCE:VIEW")) {
    finish(false, ["code" => "PERMISSIONS"]);
}

$svc = new InsuranceService($DBLIB);
$instanceId = $AUTH->data['instance']['instances_id'];
$assetId = intval($_GET['asset_id'] ?? 0);

if (!$assetId) {
    finish(false, ["code" => "MISSING_ASSET_ID"]);
}

$policies = $svc->checkCoverage($assetId, $instanceId);
$status = $svc->getCoverageStatus($assetId);

finish(true, null, [
    'status' => $status,
    'policies' => $policies,
]);
