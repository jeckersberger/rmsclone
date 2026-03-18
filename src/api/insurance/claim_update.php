<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/InsuranceService.php';

if (!$AUTH->instancePermissionCheck("INSURANCE:EDIT")) {
    finish(false, ["code" => "PERMISSIONS"]);
}

$svc = new InsuranceService($DBLIB);
$claimId = intval($_POST['claim_id'] ?? 0);
$status = $_POST['status'] ?? '';
$approvedAmount = isset($_POST['approved_amount']) ? floatval($_POST['approved_amount']) : null;

if (!$claimId || !$status) {
    finish(false, ["code" => "MISSING_PARAMS"]);
}

$result = $svc->updateClaimStatus($claimId, $status, $approvedAmount);
finish($result ? true : false, $result ? null : ["code" => "UPDATE_FAILED"]);
