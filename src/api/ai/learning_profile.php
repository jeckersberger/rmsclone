<?php
/**
 * AI Learning Profile API
 *
 * GET /api/ai/learning_profile - Get user/instance learning profile
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
    $profile = $service->getLearningProfile($instanceId);

    echo json_encode([
        'success' => true,
        'profile' => $profile,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to get profile: ' . $e->getMessage()]);
}
