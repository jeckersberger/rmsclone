<?php
/**
 * AI Providers API
 *
 * GET  /api/ai/providers - List all providers
 * POST /api/ai/providers - Create new provider
 * PUT  /api/ai/providers/{id} - Update provider
 */

require_once __DIR__ . '/../apiHeadSecure.php';

// Check permission
if (!$AUTH || !$AUTH->instancePermissionCheck('AI:CONFIGURE')) {
    http_response_code(403);
    die(json_encode(['error' => 'Permission denied']));
}

$instanceId = (int)($AUTH->data['instance']['instances_id'] ?? 0);
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGet($DBLIB, $instanceId);
        break;
    case 'POST':
        handlePost($DBLIB, $instanceId);
        break;
    case 'PUT':
        handlePut($DBLIB, $instanceId);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

function handleGet($DBLIB, $instanceId)
{
    $DBLIB->where('instances_id', $instanceId);
    $DBLIB->orderBy('is_default', 'DESC');
    $DBLIB->orderBy('created_at', 'DESC');
    $providers = $DBLIB->get('ai_providers') ?: [];

    // Hide encrypted keys
    foreach ($providers as &$p) {
        if (!empty($p['api_key_encrypted'])) {
            $p['api_key_encrypted'] = '***ENCRYPTED***';
        }
    }

    echo json_encode(['success' => true, 'data' => $providers]);
}

function handlePost($DBLIB, $instanceId)
{
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['name']) || empty($input['provider_type']) || empty($input['default_model'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields: name, provider_type, default_model']);
        return;
    }

    // Encrypt API key if provided
    $apiKeyEncrypted = null;
    if (!empty($input['api_key'])) {
        $registry = new AiProviderRegistry($DBLIB);
        $apiKeyEncrypted = $registry->encryptApiKey($input['api_key']);
    }

    $data = [
        'instances_id' => $instanceId,
        'name' => $input['name'],
        'provider_type' => $input['provider_type'],
        'api_key_encrypted' => $apiKeyEncrypted,
        'base_url' => $input['base_url'] ?? null,
        'default_model' => $input['default_model'],
        'is_active' => $input['is_active'] ?? 1,
        'is_default' => $input['is_default'] ?? 0,
        'config' => !empty($input['config']) ? json_encode($input['config']) : null,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    $id = $DBLIB->insert('ai_providers', $data);

    if (!$id) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create provider']);
        return;
    }

    echo json_encode(['success' => true, 'id' => $id]);
}

function handlePut($DBLIB, $instanceId)
{
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing id']);
        return;
    }

    $DBLIB->where('id', $input['id']);
    $DBLIB->where('instances_id', $instanceId);
    $provider = $DBLIB->getOne('ai_providers');

    if (!$provider) {
        http_response_code(404);
        echo json_encode(['error' => 'Provider not found']);
        return;
    }

    $updateData = [];

    if (isset($input['name'])) {
        $updateData['name'] = $input['name'];
    }
    if (isset($input['default_model'])) {
        $updateData['default_model'] = $input['default_model'];
    }
    if (isset($input['is_active'])) {
        $updateData['is_active'] = (int)$input['is_active'];
    }
    if (isset($input['is_default'])) {
        $updateData['is_default'] = (int)$input['is_default'];
        if ($input['is_default']) {
            // Unset all other defaults for this instance
            $DBLIB->where('instances_id', $instanceId);
            $DBLIB->where('id', $input['id'], '!=');
            $DBLIB->update('ai_providers', ['is_default' => 0]);
        }
    }
    if (isset($input['base_url'])) {
        $updateData['base_url'] = $input['base_url'] ?: null;
    }
    if (isset($input['config'])) {
        $updateData['config'] = !empty($input['config']) ? json_encode($input['config']) : null;
    }
    if (isset($input['api_key']) && !empty($input['api_key'])) {
        $registry = new AiProviderRegistry($DBLIB);
        $updateData['api_key_encrypted'] = $registry->encryptApiKey($input['api_key']);
    }

    if (!empty($updateData)) {
        $updateData['updated_at'] = date('Y-m-d H:i:s');
        $DBLIB->where('id', $input['id']);
        $DBLIB->update('ai_providers', $updateData);
    }

    echo json_encode(['success' => true]);
}
