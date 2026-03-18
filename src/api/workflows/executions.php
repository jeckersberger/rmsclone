<?php
/**
 * Workflows API - Execution History
 *
 * GET: Get execution history for a workflow
 * Query params:
 *   - workflow_id: int (required)
 *   - limit: int (optional, default 50)
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
    $workflowId = (int)($_GET['workflow_id'] ?? 0);
    if (!$workflowId) {
        finish(false, ['message' => 'Workflow-ID erforderlich']);
    }

    $limit = (int)($_GET['limit'] ?? 50);
    if ($limit < 1 || $limit > 200) {
        $limit = 50;
    }

    $instanceId = (int)($user['instance']['instances_id'] ?? 0);

    $workflowService = new WorkflowEngineService($db);
    $workflow = $workflowService->getWorkflow($workflowId);

    if (!$workflow || $workflow['instances_id'] != $instanceId) {
        finish(false, ['message' => 'Workflow nicht gefunden oder Zugriff verweigert']);
    }

    $executions = $workflowService->getExecutions($workflowId, $limit);

    finish(true, null, [
        'executions' => $executions,
        'count' => count($executions),
    ]);
} catch (Exception $e) {
    finish(false, ['message' => 'Fehler beim Laden der Ausführungshistorie: ' . $e->getMessage()]);
}
