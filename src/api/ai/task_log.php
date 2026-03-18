<?php
/**
 * AI Task Log API
 *
 * GET /api/ai/task_log - Get recent AI task log
 * GET /api/ai/task_log?limit=100 - Custom limit (default 50)
 * GET /api/ai/task_log?type=formulate_request - Filter by task type
 *
 * Returns audit trail of what the AI system has done automatically
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

    $limit = min((int)($_GET['limit'] ?? 50), 500);
    $type = $_GET['type'] ?? null;

    $log = $service->getRecentTaskLog($instanceId, $limit);

    // Filter by type if specified
    if ($type) {
        $log = array_filter($log, fn($entry) => $entry['task_type'] === $type);
        $log = array_values($log);
    }

    echo json_encode([
        'success' => true,
        'count' => count($log),
        'limit' => $limit,
        'log' => $log,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'API error: ' . $e->getMessage()]);
}
