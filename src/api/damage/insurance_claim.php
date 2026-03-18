<?php
/**
 * POST /api/damage/insurance_claim.php
 * Submit or update insurance claim
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/DamageWorkflowService.php';

if (!$AUTH->instancePermissionCheck("DAMAGE:EDIT")) {
    finish(false, ["message" => "Permission denied"]);
}

$workflowId = intval($_POST['workflow_id'] ?? 0);
$claimId = $_POST['claim_id'] ?? null;
$claimStatus = $_POST['claim_status'] ?? null;

if (!$workflowId) {
    finish(false, ["message" => "workflow_id required"]);
}

// Verify workflow belongs to instance
$DBLIB->where('id', $workflowId);
$DBLIB->where('instances_id', $AUTH->data['instance']['instances_id']);
$workflow = $DBLIB->getOne('damage_workflows', ['id']);
if (!$workflow) {
    finish(false, ["code" => "NOT_FOUND"]);
}

$service = new DamageWorkflowService($DBLIB);

if ($claimId) {
    // Submit new claim
    $result = $service->submitInsuranceClaim($workflowId, $claimId);
    if (!$result) {
        finish(false, ["message" => "Failed to submit insurance claim"]);
    }
} elseif ($claimStatus) {
    // Update existing claim status
    $result = $service->updateInsuranceStatus($workflowId, $claimStatus);
    if (!$result) {
        finish(false, ["message" => "Invalid claim status or update failed"]);
    }
} else {
    finish(false, ["message" => "claim_id or claim_status required"]);
}

finish(true, null, ['workflow_id' => $workflowId]);
