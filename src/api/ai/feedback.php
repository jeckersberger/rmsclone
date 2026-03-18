<?php
/**
 * AI Feedback API
 *
 * POST /api/ai/feedback - Record feedback on AI output
 *
 * Request body:
 * {
 *   "task_type": "email_draft",
 *   "ai_output": "...",
 *   "user_edited": "..." (optional, if user corrected it),
 *   "rating": "positive|negative|neutral",
 *   "reason": "too_short" (optional),
 *   "feedback_text": "..." (optional),
 *   "provider": "openai",
 *   "model": "gpt-4",
 *   "tokens_input": 150,
 *   "tokens_output": 280,
 *   "edit_time_ms": 5000 (optional)
 * }
 */

require_once __DIR__ . '/../apiHeadSecure.php';

if (!$user || !$perms->hasPerm('AI:VIEW')) {
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

// Validate required fields
$required = ['task_type', 'ai_output', 'rating', 'provider', 'model', 'tokens_input', 'tokens_output'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        die(json_encode(['error' => "Missing required field: {$field}"]));
    }
}

try {
    $service = new FeedbackLearningService($db, new AiProviderRegistry($db));

    $feedbackId = $service->recordFeedback(
        userId: $user['users_id'],
        taskType: $input['task_type'],
        aiOutput: $input['ai_output'],
        userEdited: $input['user_edited'] ?? null,
        rating: $input['rating'],
        reason: $input['reason'] ?? null,
        feedbackText: $input['feedback_text'] ?? null,
        provider: $input['provider'],
        model: $input['model'],
        instanceId: $instanceId,
        editTimeMs: $input['edit_time_ms'] ?? null,
        tokensInput: (int)$input['tokens_input'],
        tokensOutput: (int)$input['tokens_output'],
    );

    echo json_encode([
        'success' => true,
        'feedback_id' => $feedbackId,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to record feedback: ' . $e->getMessage()]);
}
