<?php

/**
 * Sustainability Configuration API
 *
 * GET: Retrieve current configuration
 * POST: Update configuration (emission factors, energy pricing)
 */

require_once dirname(__DIR__, 3) . '/bootstrap.php';

$sustainabilityService = new SustainabilityService($db);

header('Content-Type: application/json');

// Permission check
if (!checkAccess('SUSTAINABILITY', 'CONFIGURE')) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$instanceId = $_SESSION['instances_id'] ?? 0;
if ($instanceId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Instance not set']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $config = $sustainabilityService->getConfig($instanceId);
        echo json_encode(['success' => true, 'data' => $config]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        $result = $sustainabilityService->saveConfig($instanceId, $input);

        if ($result) {
            $config = $sustainabilityService->getConfig($instanceId);
            echo json_encode(['success' => true, 'data' => $config]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save configuration']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
