<?php
/**
 * Workflows API - Templates List
 *
 * GET: Get all available workflow templates
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
    $workflowService = new WorkflowEngineService($db);
    $templates = $workflowService->getTemplates();

    finish(true, null, [
        'templates' => $templates,
        'count' => count($templates),
    ]);
} catch (Exception $e) {
    finish(false, ['message' => 'Fehler beim Laden der Vorlagen: ' . $e->getMessage()]);
}
