<?php
/**
 * AI Prompt Versions API
 *
 * GET /api/ai/prompt_versions?task_type=email_draft
 * POST /api/ai/prompt_versions - Create new version
 */

require_once __DIR__ . '/../apiHeadSecure.php';

if (!$user || !$perms->hasPerm('AI:VIEW')) {
    http_response_code(403);
    die(json_encode(['error' => 'Permission denied']));
}

$instanceId = (int)$_SESSION['instance_id'];
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGet($db, $instanceId);
        break;
    case 'POST':
        handlePost($db, $user, $instanceId, $perms);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

function handleGet($db, $instanceId)
{
    $taskType = $_GET['task_type'] ?? '';

    if (empty($taskType)) {
        http_response_code(400);
        die(json_encode(['error' => 'Missing task_type parameter']));
    }

    $service = new FeedbackLearningService($db, new AiProviderRegistry($db));
    $versions = $service->getPromptVersionHistory($taskType, $instanceId);

    echo json_encode([
        'success' => true,
        'task_type' => $taskType,
        'versions' => $versions,
    ]);
}

function handlePost($db, $user, $instanceId, $perms)
{
    if (!$perms->hasPerm('AI:CONFIGURE')) {
        http_response_code(403);
        die(json_encode(['error' => 'Permission denied']));
    }

    $input = json_decode(file_get_contents('php://input'), true);

    $required = ['task_type', 'system_prompt', 'change_reason', 'change_source'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            die(json_encode(['error' => "Missing required field: {$field}"]));
        }
    }

    if (!in_array($input['change_source'], ['manual', 'automatic', 'ab_test'])) {
        http_response_code(400);
        die(json_encode(['error' => 'Invalid change_source']));
    }

    $service = new FeedbackLearningService($db, new AiProviderRegistry($db));
    $versionId = $service->createPromptVersion(
        $input['task_type'],
        $input['system_prompt'],
        $input['change_reason'],
        $input['change_source'],
        $instanceId,
    );

    echo json_encode([
        'success' => true,
        'version_id' => $versionId,
    ]);
}
