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

$result = $svc->updatePolicy($policyId, [
    'name' => $_POST['name'] ?? null,
    'provider' => $_POST['provider'] ?? null,
    'policy_number' => $_POST['policy_number'] ?? null,
    'coverage_type' => $_POST['coverage_type'] ?? null,
    'coverage_amount' => isset($_POST['coverage_amount']) ? $_POST['coverage_amount'] : null,
    'deductible' => isset($_POST['deductible']) ? $_POST['deductible'] : null,
    'premium_monthly' => isset($_POST['premium_monthly']) ? $_POST['premium_monthly'] : null,
    'valid_from' => $_POST['valid_from'] ?? null,
    'valid_until' => $_POST['valid_until'] ?? null,
    'document_path' => $_POST['document_path'] ?? null,
    'notes' => $_POST['notes'] ?? null,
    'is_active' => isset($_POST['is_active']) ? (bool) $_POST['is_active'] : null,
]);

finish($result ? true : false, $result ? null : ["code" => "UPDATE_FAILED"]);
