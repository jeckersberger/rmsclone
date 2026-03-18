<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/InsuranceService.php';

if (!$AUTH->instancePermissionCheck("INSURANCE:VIEW")) {
    finish(false, ["code" => "PERMISSIONS"]);
}

$svc = new InsuranceService($DBLIB);
$instanceId = $AUTH->data['instance']['instances_id'];

$uncoveredAssets = $svc->getUncoveredAssets($instanceId);

finish(true, null, ['uncovered_assets' => $uncoveredAssets]);
