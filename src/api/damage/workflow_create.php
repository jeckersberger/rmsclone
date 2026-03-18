<?php
/**
 * POST /api/damage/workflow_create.php
 * Create workflow from damage report
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/DamageWorkflowService.php';

if (!$AUTH->instancePermissionCheck("DAMAGE:EDIT")) {
    finish(false, ["message" => "Permission denied"]);
}

$damageReportId = intval($_POST['damage_report_id'] ?? 0);
$severity = $_POST['severity'] ?? 'minor';
$estimatedCost = !empty($_POST['estimated_cost']) ? floatval($_POST['estimated_cost']) : null;
$assignedTo = !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : null;

if (!$damageReportId) {
    finish(false, ["message" => "damage_report_id required"]);
}

// Validate severity
if (!in_array($severity, ['minor', 'moderate', 'major', 'total_loss'])) {
    finish(false, ["message" => "Invalid severity"]);
}

// Verify report belongs to instance
$DBLIB->where('id', $damageReportId);
$DBLIB->where('instances_id', $AUTH->data['instance']['instances_id']);
$report = $DBLIB->getOne('damage_reports', ['id', 'instances_id']);
if (!$report) {
    finish(false, ["code" => "NOT_FOUND"]);
}

// Check if workflow already exists
$DBLIB->where('damage_report_id', $damageReportId);
$existingWorkflow = $DBLIB->getOne('damage_workflows', ['id']);
if ($existingWorkflow) {
    finish(false, ["message" => "Workflow already exists for this report"]);
}

$service = new DamageWorkflowService($DBLIB);
$workflowId = $service->createWorkflow(
    $damageReportId,
    $severity,
    $estimatedCost,
    $assignedTo,
    $report['instances_id']
);

if (!$workflowId) {
    finish(false, ["message" => "Failed to create workflow"]);
}

finish(true, null, ['workflow_id' => $workflowId]);
