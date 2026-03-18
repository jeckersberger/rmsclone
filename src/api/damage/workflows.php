<?php
/**
 * GET /api/damage/workflows.php
 * List open damage workflows for instance
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/DamageWorkflowService.php';

if (!$AUTH->instancePermissionCheck("DAMAGE:VIEW")) {
    finish(false, ["message" => "Permission denied"]);
}

$service = new DamageWorkflowService($DBLIB);
$instanceId = $AUTH->data['instance']['instances_id'];

$severity = $_POST['severity'] ?? null;
$assigned_to = $_POST['assigned_to'] ?? null;

$workflows = $service->getOpenWorkflows($instanceId, $severity);

// Filter by assigned_to if provided
if ($assigned_to) {
    $workflows = array_filter($workflows, function($w) use ($assigned_to) {
        return $w['assigned_to'] == $assigned_to;
    });
}

finish(true, null, ['workflows' => $workflows]);
