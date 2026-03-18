<?php
/**
 * Workflows API - Manual Execution
 *
 * POST: Manually execute a workflow
 * Params:
 *   - id: int (required) - workflow ID
 *   - trigger_data: JSON object (optional) - context data for the workflow
 */

require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/WorkflowEngineService.php';

$user = $AUTH->data;
if (!$user) {
    finish(false, ['message' => 'Nicht angemeldet']);
}

if (!$AUTH->serverPermissionCheck('WORKFLOWS:EXECUTE')) {
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

    // Parse trigger data
    $triggerData = [];
    if (!empty($_POST['trigger_data'])) {
        $triggerDataParsed = json_decode($_POST['trigger_data'], true);
        if (is_array($triggerDataParsed)) {
            $triggerData = $triggerDataParsed;
        }
    }

    // Execute workflow
    $executionId = $workflowService->executeWorkflow($workflowId, $triggerData, $instanceId);

    if (!$executionId) {
        finish(false, ['message' => 'Fehler beim Starten der Workflow-Ausführung']);
    }

    $execution = $workflowService->getExecutionDetail($executionId);

    finish(true, null, [
        'execution' => $execution,
        'message' => 'Workflow-Ausführung gestartet',
    ]);
} catch (Exception $e) {
    finish(false, ['message' => 'Fehler bei der Workflow-Ausführung: ' . $e->getMessage()]);
}
