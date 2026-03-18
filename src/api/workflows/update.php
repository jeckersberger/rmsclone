<?php
/**
 * Workflows API - Update Workflow
 *
 * POST: Update an existing workflow
 * Params:
 *   - id: int (required)
 *   - name: string (optional)
 *   - description: string (optional)
 *   - trigger_type: enum (optional)
 *   - trigger_config: JSON object (optional)
 *   - steps: JSON array (optional)
 *   - is_active: bool (optional)
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

    $data = [];
    if (isset($_POST['name'])) {
        $data['name'] = trim($_POST['name']);
    }
    if (isset($_POST['description'])) {
        $data['description'] = trim($_POST['description']);
    }
    if (isset($_POST['trigger_type'])) {
        $data['trigger_type'] = $_POST['trigger_type'];
    }
    if (isset($_POST['trigger_config'])) {
        $triggerConfig = json_decode($_POST['trigger_config'], true);
        if (is_array($triggerConfig)) {
            $data['trigger_config'] = $triggerConfig;
        }
    }
    if (isset($_POST['is_active'])) {
        $data['is_active'] = (int)$_POST['is_active'];
    }

    // If steps are provided, parse them
    $steps = $workflow['steps'];
    if (isset($_POST['steps'])) {
        $stepsJson = json_decode($_POST['steps'], true);
        if (is_array($stepsJson) && !empty($stepsJson)) {
            $steps = $stepsJson;
        } else {
            finish(false, ['message' => 'Ungültiges Steps-Format']);
        }
    }

    $success = $workflowService->updateWorkflow($workflowId, $data, $steps);

    if (!$success) {
        finish(false, ['message' => 'Fehler beim Aktualisieren des Workflows']);
    }

    $updatedWorkflow = $workflowService->getWorkflow($workflowId);

    finish(true, null, [
        'workflow' => $updatedWorkflow,
        'message' => 'Workflow aktualisiert',
    ]);
} catch (Exception $e) {
    finish(false, ['message' => 'Fehler beim Aktualisieren des Workflows: ' . $e->getMessage()]);
}
