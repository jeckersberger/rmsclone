<?php
/**
 * AI Learning Dashboard API
 *
 * GET /api/ai/learning_dashboard
 *
 * Returns comprehensive dashboard data:
 * - Acceptance rate trends (last 6 months)
 * - Top improvements
 * - Areas needing attention
 * - Learning profile
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
    $service = new FeedbackLearningService($db, new AiProviderRegistry($db));
    $data = $service->getDashboardData($instanceId);

    echo json_encode([
        'success' => true,
        'data' => $data,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to get dashboard data: ' . $e->getMessage()]);
}
