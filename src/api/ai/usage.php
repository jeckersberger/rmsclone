<?php
/**
 * AI Usage Statistics API - Multi-Provider System
 *
 * GET /api/ai/usage?period=month&provider_id={id}&task_type={type}
 * Returns usage summary, budget status, and detailed breakdowns
 */

require_once __DIR__ . '/../apiHeadSecure.php';

if (!$user || !$perms->hasPerm('AI:VIEW')) {
    http_response_code(403);
    die(json_encode(['error' => 'Permission denied']));
}

$instanceId = (int)$_SESSION['instance_id'];
$period = $_GET['period'] ?? 'month';
$providerId = isset($_GET['provider_id']) ? (int)$_GET['provider_id'] : null;
$taskType = $_GET['task_type'] ?? null;

$tracker = new AiUsageTracker($db);

$response = [
    'summary' => $tracker->getUsageSummary($instanceId, $period),
    'monthly_budget' => $tracker->getMonthlyBudgetStatus($instanceId),
    'by_task_type' => $tracker->getTaskTypeStats($instanceId, $period),
];

// If specific provider requested
if ($providerId) {
    $response['provider_stats'] = $tracker->getProviderStats($providerId, $instanceId, $period);
}

echo json_encode(['success' => true, 'data' => $response]);
