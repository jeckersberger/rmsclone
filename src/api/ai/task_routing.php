<?php
/**
 * Task Routing Configuration
 *
 * GET  /api/ai/task_routing - List task routing rules
 * POST /api/ai/task_routing - Create/update routing rule
 */

require_once __DIR__ . '/../apiHeadSecure.php';

if (!$user || !$perms->hasPerm('AI:CONFIGURE')) {
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
        handlePost($db, $instanceId);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

function handleGet($db, $instanceId)
{
    $db->where('instances_id', $instanceId);
    $db->orderBy('task_type', 'ASC');
    $routings = $db->get('ai_task_routing') ?: [];

    // Enrich with provider information
    foreach ($routings as &$routing) {
        $db->where('id', $routing['provider_id']);
        $provider = $db->getOne('ai_providers', null, ['id', 'name', 'provider_type', 'default_model']);
        $routing['provider'] = $provider;
    }

    echo json_encode(['success' => true, 'data' => $routings]);
}

function handlePost($db, $instanceId)
{
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['task_type']) || empty($input['provider_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields: task_type, provider_id']);
        return;
    }

    // Check if provider exists
    $db->where('id', $input['provider_id']);
    $db->where('instances_id', $instanceId);
    $provider = $db->getOne('ai_providers');

    if (!$provider) {
        http_response_code(404);
        echo json_encode(['error' => 'Provider not found']);
        return;
    }

    // Check if routing already exists
    $db->where('instances_id', $instanceId);
    $db->where('task_type', $input['task_type']);
    $existing = $db->getOne('ai_task_routing');

    $data = [
        'instances_id' => $instanceId,
        'task_type' => $input['task_type'],
        'provider_id' => $input['provider_id'],
        'model_override' => $input['model_override'] ?? null,
        'priority' => $input['priority'] ?? 0,
    ];

    if ($existing) {
        // Update
        $db->where('instances_id', $instanceId);
        $db->where('task_type', $input['task_type']);
        $db->update('ai_task_routing', $data);
        $id = $existing['id'];
    } else {
        // Create
        $id = $db->insert('ai_task_routing', $data);
    }

    echo json_encode(['success' => true, 'id' => $id]);
}
