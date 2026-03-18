<?php
/**
 * Test AI Provider Connection
 *
 * POST /api/ai/provider_test
 * Request: { "provider_id": 1 }
 */

require_once __DIR__ . '/../apiHeadSecure.php';

if (!$user || !$perms->hasPerm('AI:CONFIGURE')) {
    http_response_code(403);
    die(json_encode(['error' => 'Permission denied']));
}

$input = json_decode(file_get_contents('php://input'), true);
if (empty($input['provider_id'])) {
    http_response_code(400);
    die(json_encode(['error' => 'Missing provider_id']));
}

$instanceId = (int)$_SESSION['instance_id'];

// Load the provider and test it
$handler = new AiRequestHandler($db, new AiProviderRegistry($db), new AiUsageTracker($db));
$result = $handler->testProvider((int)$input['provider_id'], $instanceId);

echo json_encode($result);
