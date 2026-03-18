<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/InsuranceService.php';

if (!$AUTH->instancePermissionCheck("INSURANCE:EDIT")) {
    finish(false, ["code" => "PERMISSIONS"]);
}

$svc = new InsuranceService($DBLIB);
$policyId = intval($_POST['policy_id'] ?? 0);

if (!$policyId) {
    finish(false, ["code" => "MISSING_ID"]);
}

$result = $svc->deletePolicy($policyId);
finish($result ? true : false, $result ? null : ["code" => "DELETE_FAILED"]);
