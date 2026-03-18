<?php
/**
 * GET /api/damage/dashboard.php
 * Get dashboard statistics for damage management
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/DamageWorkflowService.php';

if (!$AUTH->instancePermissionCheck("DAMAGE:VIEW")) {
    finish(false, ["message" => "Permission denied"]);
}

$service = new DamageWorkflowService($DBLIB);
$instanceId = $AUTH->data['instance']['instances_id'];

$stats = $service->getDashboardStats($instanceId);

finish(true, null, ['stats' => $stats]);
