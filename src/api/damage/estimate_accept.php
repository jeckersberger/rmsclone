<?php
/**
 * POST /api/damage/estimate_accept.php
 * Accept cost estimate
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/DamageWorkflowService.php';

if (!$AUTH->instancePermissionCheck("DAMAGE:EDIT")) {
    finish(false, ["message" => "Permission denied"]);
}

$estimateId = intval($_POST['estimate_id'] ?? 0);
if (!$estimateId) {
    finish(false, ["message" => "estimate_id required"]);
}

// Verify estimate exists and belongs to instance's workflow
$DBLIB->where('dce.id', $estimateId);
$DBLIB->join('damage_workflows dw', 'dce.workflow_id = dw.id', 'LEFT');
$estimate = $DBLIB->getOne('damage_cost_estimates dce', null, ['dce.id', 'dw.instances_id']);
if (!$estimate || $estimate['instances_id'] != $AUTH->data['instance']['instances_id']) {
    finish(false, ["code" => "NOT_FOUND"]);
}

$service = new DamageWorkflowService($DBLIB);
$result = $service->acceptEstimate($estimateId);

if (!$result) {
    finish(false, ["message" => "Failed to accept estimate"]);
}

finish(true, null, ['estimate_id' => $estimateId, 'accepted' => true]);
