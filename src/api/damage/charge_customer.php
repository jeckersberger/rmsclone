<?php
/**
 * POST /api/damage/charge_customer.php
 * Charge damage cost to customer
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/DamageWorkflowService.php';

if (!$AUTH->instancePermissionCheck("DAMAGE:CHARGE")) {
    finish(false, ["message" => "Permission denied"]);
}

$workflowId = intval($_POST['workflow_id'] ?? 0);
$amount = floatval($_POST['amount'] ?? 0);
$invoiceId = !empty($_POST['invoice_id']) ? intval($_POST['invoice_id']) : null;
$chargeType = $_POST['charge_type'] ?? 'customer'; // customer or deposit

if (!$workflowId) {
    finish(false, ["message" => "workflow_id required"]);
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

if ($chargeType === 'deposit') {
    $result = $service->deductFromDeposit($workflowId, $amount);
} else {
    $result = $service->chargeCustomer($workflowId, $amount, $invoiceId);
}

if (!$result) {
    finish(false, ["message" => "Failed to charge customer"]);
}

finish(true, null, ['workflow_id' => $workflowId, 'amount' => $amount, 'type' => $chargeType]);
