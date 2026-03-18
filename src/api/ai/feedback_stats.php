<?php
/**
 * AI Feedback Statistics API
 *
 * GET /api/ai/feedback_stats
 *
 * Returns:
 * {
 *   "total_30_days": 150,
 *   "total_90_days": 450,
 *   "by_rating": {"positive": 120, "negative": 20, "neutral": 10},
 *   "acceptance_rate_30": 0.8,
 *   "acceptance_rate_60": 0.75,
 *   "trend_direction": "up|down|flat",
 *   "task_types": {...},
 *   "top_negative_reasons": {...}
 * }
 */

require_once __DIR__ . '/../apiHeadSecure.php';

if (!$user || !$perms->hasPerm('AI:VIEW')) {
    http_response_code(403);
    die(json_encode(['error' => 'Permission denied']));
}

$instanceId = (int)$_SESSION['instance_id'];

try {
    $service = new FeedbackLearningService($db, new AiProviderRegistry($db));
    $stats = $service->getFeedbackStats($instanceId);

    echo json_encode([
        'success' => true,
        'data' => $stats,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to get stats: ' . $e->getMessage()]);
}
