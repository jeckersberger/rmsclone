<?php
/**
 * Workflows API - Get Single Workflow
 *
 * GET: Retrieve a single workflow with all its steps
 * Query params:
 *   - id: int (required) - workflow ID
 */

require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/WorkflowEngineService.php';

$user = $AUTH->data;
if (!$user) {
    finish(false, ['message' => 'Nicht angemeldet']);
}

if (!$AUTH->serverPermissionCheck('WORKFLOWS:VIEW')) {
    finish(false, ['message' => 'Keine Berechtigung']);
}

try {
    $workflowId = (int)($_GET['id'] ?? 0);
    if (!$workflowId) {
        finish(false, ['message' => 'Workflow-ID erforderlich']);
    }

    $workflowService = new WorkflowEngineService($db);
    $workflow = $workflowService->getWorkflow($workflowId);

    if (!$workflow) {
        finish(false, ['message' => 'Workflow nicht gefunden']);
    }

    // Verify instance access
    $instanceId = (int)($user['instance']['instances_id'] ?? 0);
    if ($workflow['instances_id'] != $instanceId) {
        finish(false, ['message' => 'Zugriff verweigert']);
    }

    finish(true, null, ['workflow' => $workflow]);
} catch (Exception $e) {
    finish(false, ['message' => 'Fehler beim Abrufen des Workflows: ' . $e->getMessage()]);
}
