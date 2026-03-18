<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/InsuranceService.php';

if (!$AUTH->instancePermissionCheck("INSURANCE:VIEW")) {
    finish(false, ["code" => "PERMISSIONS"]);
}

$svc = new InsuranceService($DBLIB);
$instanceId = $AUTH->data['instance']['instances_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$AUTH->instancePermissionCheck("INSURANCE:EDIT")) {
        finish(false, ["code" => "PERMISSIONS"]);
    }

    $claimId = $svc->createClaim([
        'instances_id' => $instanceId,
        'policy_id' => intval($_POST['policy_id'] ?? 0),
        'damage_workflow_id' => isset($_POST['damage_workflow_id']) ? intval($_POST['damage_workflow_id']) : null,
        'claim_number' => $_POST['claim_number'] ?? '',
        'claim_date' => $_POST['claim_date'] ?? date('Y-m-d'),
        'description' => $_POST['description'] ?? null,
        'claimed_amount' => floatval($_POST['claimed_amount'] ?? 0),
    ]);

    if ($claimId) {
        finish(true, ["id" => $claimId]);
    } else {
        finish(false, ["code" => "CREATE_FAILED"]);
    }
} else {
    $status = $_GET['status'] ?? null;
    $claims = $svc->getClaims($instanceId, $status);
    finish(true, null, ['claims' => $claims]);
}
