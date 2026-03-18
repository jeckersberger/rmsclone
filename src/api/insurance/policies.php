<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/InsuranceService.php';

if (!$AUTH->instancePermissionCheck("INSURANCE:VIEW")) {
    finish(false, ["code" => "PERMISSIONS"]);
}

$svc = new InsuranceService($DBLIB);
$instanceId = $AUTH->data['instance']['instances_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create new policy
    if (!$AUTH->instancePermissionCheck("INSURANCE:EDIT")) {
        finish(false, ["code" => "PERMISSIONS"]);
    }

    $policyId = $svc->createPolicy([
        'instances_id' => $instanceId,
        'name' => $_POST['name'] ?? '',
        'provider' => $_POST['provider'] ?? '',
        'policy_number' => $_POST['policy_number'] ?? '',
        'coverage_type' => $_POST['coverage_type'] ?? 'all_risk',
        'coverage_amount' => $_POST['coverage_amount'] ?? 0,
        'deductible' => $_POST['deductible'] ?? null,
        'premium_monthly' => $_POST['premium_monthly'] ?? 0,
        'valid_from' => $_POST['valid_from'] ?? date('Y-m-d'),
        'valid_until' => $_POST['valid_until'] ?? date('Y-m-d'),
        'document_path' => $_POST['document_path'] ?? null,
        'notes' => $_POST['notes'] ?? null,
        'is_active' => isset($_POST['is_active']) ? (bool) $_POST['is_active'] : true,
    ]);

    if ($policyId) {
        finish(true, ["id" => $policyId]);
    } else {
        finish(false, ["code" => "CREATE_FAILED"]);
    }
} else {
    // Get policies
    $activeOnly = isset($_GET['active_only']) ? (bool) $_GET['active_only'] : true;
    $policies = $svc->getPolicies($instanceId, $activeOnly);

    finish(true, null, ['policies' => $policies]);
}
