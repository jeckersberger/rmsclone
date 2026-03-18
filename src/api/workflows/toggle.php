<?php
/**
 * Workflows API - Toggle Active Status
 *
 * POST: Enable or disable a workflow
 * Params:
 *   - id: int (required)
 *   - active: bool (required)
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
    $active = (bool)($_POST['active'] ?? false);

    if (!$workflowId) {
        finish(false, ['message' => 'Workflow-ID erforderlich']);
    }

    $instanceId = (int)($user['instance']['instances_id'] ?? 0);

    $workflowService = new WorkflowEngineService($db);
    $workflow = $workflowService->getWorkflow($workflowId);

    if (!$workflow || $workflow['instances_id'] != $instanceId) {
        finish(false, ['message' => 'Workflow nicht gefunden oder Zugriff verweigert']);
    }

    $success = $workflowService->toggleActive($workflowId, $active);

    if (!$success) {
        finish(false, ['message' => 'Fehler beim Aktualisieren des Workflow-Status']);
    }

    $updatedWorkflow = $workflowService->getWorkflow($workflowId);

    finish(true, null, [
        'workflow' => $updatedWorkflow,
        'message' => 'Workflow ' . ($active ? 'aktiviert' : 'deaktiviert'),
    ]);
} catch (Exception $e) {
    finish(false, ['message' => 'Fehler beim Aktualisieren des Workflow-Status: ' . $e->getMessage()]);
}
