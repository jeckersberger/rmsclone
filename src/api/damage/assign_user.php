<?php
/**
 * POST /api/damage/assign_user.php
 * Assign workflow to a user
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/DamageWorkflowService.php';

if (!$AUTH->instancePermissionCheck("DAMAGE:EDIT")) {
    finish(false, ["message" => "Permission denied"]);
}

$workflowId = intval($_POST['workflow_id'] ?? 0);
$userId = intval($_POST['user_id'] ?? 0);

if (!$workflowId) {
    finish(false, ["message" => "workflow_id required"]);
}
if (!$userId) {
    finish(false, ["message" => "user_id required"]);
}

// Verify workflow belongs to instance
$DBLIB->where('id', $workflowId);
$DBLIB->where('instances_id', $AUTH->data['instance']['instances_id']);
$workflow = $DBLIB->getOne('damage_workflows', ['id']);
if (!$workflow) {
    finish(false, ["code" => "NOT_FOUND"]);
}

// Verify user exists in instance
$DBLIB->where('users_userid', $userId);
$DBLIB->where('instances_id', $AUTH->data['instance']['instances_id']);
$user = $DBLIB->getOne('users', ['users_userid']);
if (!$user) {
    finish(false, ["code" => "NOT_FOUND", "message" => "User not found"]);
}

$service = new DamageWorkflowService($DBLIB);
$result = $service->assignTo($workflowId, $userId);

if (!$result) {
    finish(false, ["message" => "Failed to assign workflow"]);
}

finish(true, null, ['workflow_id' => $workflowId, 'assigned_to' => $userId]);
