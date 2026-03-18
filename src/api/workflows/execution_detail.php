<?php
/**
 * Workflows API - Execution Detail
 *
 * GET: Get detailed execution information with logs
 * Query params:
 *   - id: int (required) - execution ID
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
    $executionId = (int)($_GET['id'] ?? 0);
    if (!$executionId) {
        finish(false, ['message' => 'Ausführungs-ID erforderlich']);
    }

    $instanceId = (int)($user['instance']['instances_id'] ?? 0);

    $workflowService = new WorkflowEngineService($db);
    $execution = $workflowService->getExecutionDetail($executionId);

    if (!$execution || $execution['instances_id'] != $instanceId) {
        finish(false, ['message' => 'Ausführung nicht gefunden oder Zugriff verweigert']);
    }

    finish(true, null, ['execution' => $execution]);
} catch (Exception $e) {
    finish(false, ['message' => 'Fehler beim Laden der Ausführungsdetails: ' . $e->getMessage()]);
}
