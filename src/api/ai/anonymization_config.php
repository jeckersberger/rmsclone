<?php
/**
 * Anonymization Configuration API
 *
 * GET  /api/ai/anonymization_config - Get current configuration
 * POST /api/ai/anonymization_config - Update configuration
 */

require_once __DIR__ . '/../apiHeadSecure.php';

if (!$user || !$perms->hasPerm('AI:CONFIGURE')) {
    http_response_code(403);
    die(json_encode(['error' => 'Permission denied']));
}

$instanceId = (int)$_SESSION['instance_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get current configuration
    $db->where('instances_id', $instanceId);
    $config = $db->getOne('ai_anonymization_config');

    if (!$config) {
        // Return default if not yet configured
        $config = [
            'instances_id' => $instanceId,
            'mode' => 'strict',
            'custom_rules' => null,
            'provider_overrides' => null,
            'updated_at' => null,
        ];
    }

    // Parse JSON fields
    if ($config['custom_rules']) {
        $config['custom_rules'] = json_decode($config['custom_rules'], true);
    }
    if ($config['provider_overrides']) {
        $config['provider_overrides'] = json_decode($config['provider_overrides'], true);
    }

    echo json_encode(['success' => true, 'config' => $config]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update configuration
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['mode']) || !in_array($data['mode'], ['strict', 'standard', 'minimal', 'off'])) {
        http_response_code(400);
        die(json_encode(['error' => 'Invalid mode. Must be strict, standard, minimal, or off']));
    }

    $update = [
        'instances_id' => $instanceId,
        'mode' => $data['mode'],
        'custom_rules' => !empty($data['custom_rules']) ? json_encode($data['custom_rules']) : null,
        'provider_overrides' => !empty($data['provider_overrides']) ? json_encode($data['provider_overrides']) : null,
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    // Check if config exists
    $db->where('instances_id', $instanceId);
    $existing = $db->getOne('ai_anonymization_config');

    try {
        if ($existing) {
            // Update
            $db->where('instances_id', $instanceId);
            $db->update('ai_anonymization_config', $update);
        } else {
            // Insert
            $db->insert('ai_anonymization_config', $update);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Configuration updated',
            'config' => $update,
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save configuration: ' . $e->getMessage()]);
    }

} else {
    http_response_code(405);
    die(json_encode(['error' => 'Method not allowed']));
}
