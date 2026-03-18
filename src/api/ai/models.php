<?php
/**
 * List Available Models for a Provider
 *
 * GET /api/ai/models?provider_id={id}
 */

require_once __DIR__ . '/../apiHeadSecure.php';

if (!$user || !$perms->hasPerm('AI:VIEW')) {
    http_response_code(403);
    die(json_encode(['error' => 'Permission denied']));
}

if (empty($_GET['provider_id'])) {
    http_response_code(400);
    die(json_encode(['error' => 'Missing provider_id']));
}

$instanceId = (int)$_SESSION['instance_id'];
$providerId = (int)$_GET['provider_id'];

// Load provider
$db->where('id', $providerId);
$db->where('instances_id', $instanceId);
$providerRecord = $db->getOne('ai_providers');

if (!$providerRecord) {
    http_response_code(404);
    die(json_encode(['error' => 'Provider not found']));
}

try {
    $registry = new AiProviderRegistry($db);
    $provider = $registry->loadProvider($providerRecord);
    $models = $provider->listModels();

    echo json_encode([
        'success' => true,
        'provider' => $providerRecord['name'],
        'models' => $models,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
