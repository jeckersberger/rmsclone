<?php
/**
 * Workflows API - List Workflows
 *
 * GET: List all workflows for the instance
 * Query params:
 *   - active_only: bool (optional) - filter to active workflows only
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
    $instanceId = (int)($user['instance']['instances_id'] ?? 0);
    if (!$instanceId) {
        finish(false, ['message' => 'Keine Instanz-ID']);
    }

    $activeOnly = isset($_GET['active_only']) ? (bool)$_GET['active_only'] : null;

    $workflowService = new WorkflowEngineService($db);
    $workflows = $workflowService->getWorkflows($instanceId, $activeOnly);

    // Enrich with execution stats
    foreach ($workflows as &$workflow) {
        $executions = $workflowService->getExecutions($workflow['id'], 1);
        $workflow['last_execution'] = $executions[0] ?? null;

        $db->where('workflow_id', $workflow['id']);
        $totalExecutions = (int)($db->getValue('workflow_executions', 'COUNT(*)') ?? 0);
        $workflow['execution_count'] = $totalExecutions;
    }
    unset($workflow);

    finish(true, null, [
        'workflows' => $workflows,
        'count' => count($workflows),
    ]);
} catch (Exception $e) {
    finish(false, ['message' => 'Fehler beim Laden der Workflows: ' . $e->getMessage()]);
}
