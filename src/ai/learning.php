<?php
/**
 * AI Learning System Dashboard
 *
 * Displays:
 * - Acceptance rate trends
 * - User feedback statistics
 * - Few-shot example library
 * - Prompt version management
 * - Learning profile
 *
 * Requires: AI:VIEW permission
 */

// Check authentication and permissions
if (!$user) {
    http_response_code(403);
    exit('Access denied');
}

if (!$perms->hasPerm('AI:VIEW')) {
    http_response_code(403);
    exit('You do not have permission to view the AI learning system');
}

$instanceId = (int)$_SESSION['instance_id'];

// Load learning service
$service = new FeedbackLearningService($db, new AiProviderRegistry($db));

// Get all dashboard data
$dashboardData = $service->getDashboardData($instanceId);

// Prepare template data
$templateData = [
    'instance_name' => $_SESSION['instance_name'] ?? 'MyRMS',
    'stats' => $dashboardData['stats'],
    'trend_data' => $dashboardData['trend_data'],
    'improvements' => $dashboardData['improvements'],
    'needs_attention' => $dashboardData['needs_attention'],
    'learning_profile' => $dashboardData['learning_profile'],
    'perms' => $perms,
    'user' => $user,
];

// Render template
echo $twig->render('ai/ai_learning.twig', $templateData);
