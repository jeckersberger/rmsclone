<?php
/**
 * AI Prompt Activation API
 *
 * POST /api/ai/prompt_activate
 *
 * Request body:
 * {
 *   "version_id": 5
 * }
 */

require_once __DIR__ . '/../apiHeadSecure.php';

if (!$user || !$perms->hasPerm('AI:CONFIGURE')) {
    http_response_code(403);
    die(json_encode(['error' => 'Permission denied']));
}

$instanceId = (int)$_SESSION['instance_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Method not allowed']));
}

$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['version_id'])) {
    http_response_code(400);
    die(json_encode(['error' => 'Missing version_id']));
}

try {
    $service = new FeedbackLearningService($db, new AiProviderRegistry($db));
    $success = $service->activatePromptVersion((int)$input['version_id']);

    if (!$success) {
        http_response_code(404);
        die(json_encode(['error' => 'Version not found']));
    }

    echo json_encode([
        'success' => true,
        'message' => 'Prompt version activated',
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to activate version: ' . $e->getMessage()]);
}
