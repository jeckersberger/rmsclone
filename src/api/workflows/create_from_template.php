<?php
/**
 * Workflows API - Create From Template
 *
 * POST: Create a new workflow from a template
 * Params:
 *   - template_id: int (required)
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
    $templateId = (int)($_POST['template_id'] ?? 0);
    if (!$templateId) {
        finish(false, ['message' => 'Template-ID erforderlich']);
    }

    $instanceId = (int)($user['instance']['instances_id'] ?? 0);
    if (!$instanceId) {
        finish(false, ['message' => 'Keine Instanz-ID']);
    }

    $workflowService = new WorkflowEngineService($db);
    $workflowId = $workflowService->createFromTemplate($templateId, $instanceId, (int)$user['users_userid']);

    if (!$workflowId) {
        finish(false, ['message' => 'Fehler beim Erstellen des Workflows aus der Vorlage']);
    }

    $workflow = $workflowService->getWorkflow($workflowId);

    finish(true, null, [
        'workflow' => $workflow,
        'message' => 'Workflow aus Vorlage erstellt',
    ]);
} catch (Exception $e) {
    finish(false, ['message' => 'Fehler beim Erstellen des Workflows: ' . $e->getMessage()]);
}
