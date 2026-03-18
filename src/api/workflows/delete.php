<?php
/**
 * Workflows API - Delete Workflow
 *
 * POST: Delete a workflow
 * Params:
 *   - id: int (required)
 */

require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/WorkflowEngineService.php';

$user = $AUTH->data;
if (!$user) {
    finish(false, ['message' => 'Nicht angemeldet']);
}

if (!$AUTH->serverPermissionCheck('WORKFLOWS:EDIT')) {
    finish(false, ['message' => 'Keine Berechtigung']);
}

try {
    $workflowId = (int)($_POST['id'] ?? 0);
    if (!$workflowId) {
        finish(false, ['message' => 'Workflow-ID erforderlich']);
    }

    $instanceId = (int)($user['instance']['instances_id'] ?? 0);

    $workflowService = new WorkflowEngineService($db);
    $workflow = $workflowService->getWorkflow($workflowId);

    if (!$workflow || $workflow['instances_id'] != $instanceId) {
        finish(false, ['message' => 'Workflow nicht gefunden oder Zugriff verweigert']);
    }

    $success = $workflowService->deleteWorkflow($workflowId);

    if (!$success) {
        finish(false, ['message' => 'Fehler beim Löschen des Workflows']);
    }

    finish(true, null, ['message' => 'Workflow gelöscht']);
} catch (Exception $e) {
    finish(false, ['message' => 'Fehler beim Löschen des Workflows: ' . $e->getMessage()]);
}
