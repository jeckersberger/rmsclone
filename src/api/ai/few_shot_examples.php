<?php
/**
 * AI Few-Shot Examples API
 *
 * GET /api/ai/few_shot_examples?task_type=email_draft&limit=5
 * POST /api/ai/few_shot_examples - Add new example (admin only)
 * PUT /api/ai/few_shot_examples/{id} - Vote/activate example
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
    case 'PUT':
        handlePut($db, $user, $instanceId, $perms);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

function handleGet($db, $instanceId)
{
    $taskType = $_GET['task_type'] ?? '';
    $limit = (int)($_GET['limit'] ?? 5);

    if (empty($taskType)) {
        http_response_code(400);
        die(json_encode(['error' => 'Missing task_type parameter']));
    }

    $service = new FeedbackLearningService($db, new AiProviderRegistry($db));
    $examples = $service->getBestFewShotExamples($taskType, $limit, $instanceId);

    echo json_encode([
        'success' => true,
        'task_type' => $taskType,
        'examples' => $examples,
    ]);
}

function handlePost($db, $user, $instanceId, $perms)
{
    if (!$perms->hasPerm('AI:CONFIGURE')) {
        http_response_code(403);
        die(json_encode(['error' => 'Permission denied']));
    }

    $input = json_decode(file_get_contents('php://input'), true);

    $required = ['task_type', 'input_context', 'output_example'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            die(json_encode(['error' => "Missing required field: {$field}"]));
        }
    }

    $service = new FeedbackLearningService($db, new AiProviderRegistry($db));
    $exampleId = $service->addFewShotCandidate(
        $input['task_type'],
        $input['input_context'],
        $input['output_example'],
        $instanceId,
    );

    echo json_encode([
        'success' => true,
        'example_id' => $exampleId,
    ]);
}

function handlePut($db, $user, $instanceId, $perms)
{
    if (!$perms->hasPerm('AI:CONFIGURE')) {
        http_response_code(403);
        die(json_encode(['error' => 'Permission denied']));
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['id'])) {
        http_response_code(400);
        die(json_encode(['error' => 'Missing id']));
    }

    $db->where('id', $input['id']);
    $db->where('instance_id', $instanceId);
    $example = $db->getOne('ai_few_shot_examples');

    if (!$example) {
        http_response_code(404);
        die(json_encode(['error' => 'Example not found']));
    }

    $updateData = [];

    // Handle votes
    if (isset($input['vote'])) {
        if ($input['vote'] === 'positive') {
            $updateData['positive_votes'] = $example['positive_votes'] + 1;
        } elseif ($input['vote'] === 'negative') {
            $updateData['negative_votes'] = $example['negative_votes'] + 1;
        }
    }

    // Handle activation
    if (isset($input['is_active'])) {
        $updateData['is_active'] = (int)$input['is_active'];
    }

    if (!empty($updateData)) {
        $db->where('id', $input['id']);
        $db->update('ai_few_shot_examples', $updateData);
    }

    echo json_encode(['success' => true]);
}
