<?php
/**
 * POST /api/damage/workflow_transition.php
 * Transition workflow to new status
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/DamageWorkflowService.php';

if (!$AUTH->instancePermissionCheck("DAMAGE:EDIT")) {
    finish(false, ["message" => "Permission denied"]);
}

$workflowId = intval($_POST['workflow_id'] ?? 0);
$newStatus = $_POST['new_status'] ?? '';
$notes = $_POST['notes'] ?? null;

if (!$workflowId) {
    finish(false, ["message" => "workflow_id required"]);
}
if (!$newStatus) {
    finish(false, ["message" => "new_status required"]);
}

// Verify workflow belongs to instance
$DBLIB->where('id', $workflowId);
$DBLIB->where('instances_id', $AUTH->data['instance']['instances_id']);
$workflow = $DBLIB->getOne('damage_workflows', ['id']);
if (!$workflow) {
    finish(false, ["code" => "NOT_FOUND"]);
}

$service = new DamageWorkflowService($DBLIB);

// Validate transition
$validTransitions = $service->getValidTransitions($workflow['status']);
if (!in_array($newStatus, $validTransitions)) {
    finish(false, [
        "message" => "Invalid transition",
        "current_status" => $workflow['status'],
        "valid_transitions" => $validTransitions
    ]);
}

// Perform transition
$result = $service->transitionStatus($workflowId, $newStatus, $AUTH->data['user']['users_userid'], $notes);

if (!$result) {
    finish(false, ["message" => "Failed to transition status"]);
}

finish(true, null, ['workflow_id' => $workflowId, 'new_status' => $newStatus]);
