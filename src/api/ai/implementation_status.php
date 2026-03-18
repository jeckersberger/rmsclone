<?php
/**
 * Implementation Status API
 *
 * GET /api/ai/implementation_status - Get full implementation checklist
 * GET /api/ai/implementation_status?module=L1 - Get status for one module
 * GET /api/ai/implementation_status?summary=1 - Get progress summary only
 * GET /api/ai/implementation_status?dashboard=1 - Get dashboard widget data
 */

require_once __DIR__ . '/../apiHeadSecure.php';

if (!$user || !$perms->hasPerm('AI:VIEW')) {
    http_response_code(403);
    die(json_encode(['error' => 'Permission denied']));
}

$instanceId = (int)$_SESSION['instance_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    http_response_code(405);
    die(json_encode(['error' => 'Method not allowed']));
}

try {
    $service = new ImplementationTrackerService($db);

    // Check query parameters
    $moduleCode = $_GET['module'] ?? null;
    $summaryOnly = isset($_GET['summary']);
    $dashboardWidget = isset($_GET['dashboard']);

    if ($dashboardWidget) {
        // Return dashboard widget data
        $data = $service->getDashboardWidget($instanceId);
        echo json_encode([
            'success' => true,
            'data' => $data,
        ]);

    } elseif ($summaryOnly) {
        // Return progress summary only
        $summary = $service->getProgressSummary($instanceId);
        echo json_encode([
            'success' => true,
            'summary' => $summary,
        ]);

    } elseif ($moduleCode) {
        // Return status for one module
        $bausteine = $service->getModuleStatus($moduleCode, $instanceId);
        echo json_encode([
            'success' => true,
            'module_code' => $moduleCode,
            'count' => count($bausteine),
            'bausteine' => $bausteine,
        ]);

    } else {
        // Return full checklist
        $checklist = $service->getChecklist($instanceId);
        $summary = $service->getProgressSummary($instanceId);

        echo json_encode([
            'success' => true,
            'summary' => $summary,
            'checklist' => $checklist,
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'API error: ' . $e->getMessage()]);
}
