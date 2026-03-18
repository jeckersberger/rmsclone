<?php
/**
 * Delete AI Provider
 *
 * POST /api/ai/provider_delete
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
$providerId = (int)$input['provider_id'];

// Check if provider exists and belongs to this instance
$db->where('id', $providerId);
$db->where('instances_id', $instanceId);
$provider = $db->getOne('ai_providers');

if (!$provider) {
    http_response_code(404);
    die(json_encode(['error' => 'Provider not found']));
}

// Check if it's the default provider
if ($provider['is_default']) {
    http_response_code(400);
    die(json_encode(['error' => 'Cannot delete default provider. Set another provider as default first.']));
}

// Delete provider (cascades to task_routing and fallback_chain)
$db->where('id', $providerId);
$success = (bool)$db->delete('ai_providers');

echo json_encode(['success' => $success]);
