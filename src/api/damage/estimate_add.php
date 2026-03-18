<?php
/**
 * POST /api/damage/estimate_add.php
 * Add cost estimate to workflow
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/DamageWorkflowService.php';

if (!$AUTH->instancePermissionCheck("DAMAGE:EDIT")) {
    finish(false, ["message" => "Permission denied"]);
}

$workflowId = intval($_POST['workflow_id'] ?? 0);
$vendorName = $_POST['vendor_name'] ?? '';
$amount = floatval($_POST['amount'] ?? 0);
$description = $_POST['description'] ?? null;
$isAccepted = intval($_POST['is_accepted'] ?? 0) === 1;
$documentPath = $_POST['document_path'] ?? null;

if (!$workflowId) {
    finish(false, ["message" => "workflow_id required"]);
}
if (!$vendorName) {
    finish(false, ["message" => "vendor_name required"]);
}
if ($amount <= 0) {
    finish(false, ["message" => "Valid amount required"]);
}

// Verify workflow belongs to instance
$DBLIB->where('id', $workflowId);
$DBLIB->where('instances_id', $AUTH->data['instance']['instances_id']);
$workflow = $DBLIB->getOne('damage_workflows', ['id']);
if (!$workflow) {
    finish(false, ["code" => "NOT_FOUND"]);
}

$service = new DamageWorkflowService($DBLIB);
$estimateId = $service->addCostEstimate($workflowId, [
    'vendor_name' => $vendorName,
    'description' => $description,
    'amount' => $amount,
    'is_accepted' => $isAccepted,
    'document_path' => $documentPath,
]);

if (!$estimateId) {
    finish(false, ["message" => "Failed to add estimate"]);
}

finish(true, null, ['estimate_id' => $estimateId]);
