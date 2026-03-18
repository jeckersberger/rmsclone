<?php
/**
 * Workflows API - Create Workflow
 *
 * POST: Create a new workflow
 * Params:
 *   - name: string (required)
 *   - description: string (optional)
 *   - trigger_type: enum (manual|event|cron)
 *   - trigger_config: JSON object (optional)
 *   - steps: JSON array of step objects
 *   - is_active: bool (optional, default 0)
 */

require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/WorkflowEngineService.php';

$user = $AUTH->data;
if (!$user) {
    finish(false, ['message' => 'Nicht angemeldet']);
}

if (!$AUTH->instancePermissionCheck('WORKFLOWS:EDIT')) {
    finish(false, ['message' => 'Keine Berechtigung']);
}

try {
    $instanceId = (int)($user['instance']['instances_id'] ?? 0);
    if (!$instanceId) {
        finish(false, ['message' => 'Keine Instanz-ID']);
    }

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $triggerType = $_POST['trigger_type'] ?? 'manual';
    $triggerConfig = [];
    $steps = [];
    $isActive = (int)($_POST['is_active'] ?? 0);

    if (empty($name)) {
        finish(false, ['message' => 'Workflow-Name erforderlich']);
    }

    // Parse trigger config
    if (!empty($_POST['trigger_config'])) {
        $triggerConfig = json_decode($_POST['trigger_config'], true);
        if (!is_array($triggerConfig)) {
            $triggerConfig = [];
        }
    }

    // Parse steps
    if (!empty($_POST['steps'])) {
        $stepsJson = json_decode($_POST['steps'], true);
        if (is_array($stepsJson)) {
            $steps = $stepsJson;
        }
    }

    if (empty($steps)) {
        finish(false, ['message' => 'Mindestens ein Schritt erforderlich']);
    }

    $data = [
        'instances_id' => $instanceId,
        'name' => $name,
        'description' => $description,
        'trigger_type' => $triggerType,
        'trigger_config' => $triggerConfig,
        'is_active' => $isActive,
        'created_by' => (int)$user['users_userid'],
    ];

    $workflowService = new WorkflowEngineService($DBLIB);
    $workflowId = $workflowService->createWorkflow($data, $steps);

    if (!$workflowId) {
        finish(false, ['message' => 'Fehler beim Erstellen des Workflows']);
    }

    $workflow = $workflowService->getWorkflow($workflowId);

    finish(true, null, [
        'workflow' => $workflow,
        'message' => 'Workflow erstellt',
    ]);
} catch (Exception $e) {
    finish(false, ['message' => 'Fehler beim Erstellen des Workflows: ' . $e->getMessage()]);
}
