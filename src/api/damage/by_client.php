<?php
/**
 * GET /api/damage/by_client.php
 * Get damage history for specific client
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/DamageWorkflowService.php';

if (!$AUTH->instancePermissionCheck("DAMAGE:VIEW")) {
    finish(false, ["message" => "Permission denied"]);
}

$clientId = intval($_POST['client_id'] ?? 0);
if (!$clientId) {
    finish(false, ["message" => "client_id required"]);
}

// Verify client belongs to instance
$DBLIB->where('companies_id', $clientId);
$DBLIB->where('instances_id', $AUTH->data['instance']['instances_id']);
if (!$DBLIB->getOne('companies', ['companies_id'])) {
    finish(false, ["code" => "NOT_FOUND"]);
}

$service = new DamageWorkflowService($DBLIB);
$workflows = $service->getWorkflowsByClient($clientId);

finish(true, null, ['workflows' => $workflows]);
