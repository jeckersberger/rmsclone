<?php
/**
 * GET /api/damage/workflow_get.php
 * Get workflow detail with log, estimates, and photos
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/DamageWorkflowService.php';

if (!$AUTH->instancePermissionCheck("DAMAGE:VIEW")) {
    finish(false, ["message" => "Permission denied"]);
}

$damageReportId = intval($_POST['damage_report_id'] ?? 0);
if (!$damageReportId) {
    finish(false, ["message" => "damage_report_id required"]);
}

// Verify report belongs to instance
$DBLIB->where('id', $damageReportId);
$DBLIB->where('instances_id', $AUTH->data['instance']['instances_id']);
$report = $DBLIB->getOne('damage_reports', ['id']);
if (!$report) {
    finish(false, ["code" => "NOT_FOUND"]);
}

$service = new DamageWorkflowService($DBLIB);
$workflow = $service->getWorkflow($damageReportId);

if (!$workflow) {
    finish(false, ["code" => "NOT_FOUND"]);
}

finish(true, null, ['workflow' => $workflow]);
