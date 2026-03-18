<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/InsuranceService.php';

if (!$AUTH->instancePermissionCheck("INSURANCE:EDIT")) {
    finish(false, ["code" => "PERMISSIONS"]);
}

$svc = new InsuranceService($DBLIB);
$action = $_POST['action'] ?? '';
$policyId = intval($_POST['policy_id'] ?? 0);
$entityType = $_POST['entity_type'] ?? ''; // 'asset', 'asset_type', 'stock_item'
$entityId = intval($_POST['entity_id'] ?? 0);

if (!$policyId || !$entityType || !$entityId) {
    finish(false, ["code" => "MISSING_PARAMS"]);
}

$result = false;
if ($action === 'add') {
    $result = $svc->addAssetCoverage($policyId, $entityType, $entityId);
} elseif ($action === 'remove') {
    $result = $svc->removeAssetCoverage($policyId, $entityType, $entityId);
}

finish($result ? true : false, $result ? null : ["code" => "ACTION_FAILED"]);
