<?php

/**
 * Emissions by Client API
 *
 * GET: Retrieve emissions for a specific client
 *
 * Query parameters:
 *   - client_id: Client ID (required)
 */

require_once __DIR__ . '/../apiHeadSecure.php';

$sustainabilityService = new SustainabilityService($db);

header('Content-Type: application/json');

// Permission check
if (!checkAccess('SUSTAINABILITY', 'VIEW')) {
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
        $clientId = (int)($_GET['client_id'] ?? 0);
        if ($clientId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Client ID required']);
            exit;
        }

        $clientEmissions = $sustainabilityService->getEmissionsByClient($clientId, $instanceId);

        echo json_encode(['success' => true, 'data' => $clientEmissions]);
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
