<?php

/**
 * Emissions by Project API
 *
 * GET: Retrieve emissions breakdown by project
 *
 * Query parameters:
 *   - from: Start date (Y-m-d)
 *   - to: End date (Y-m-d)
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
        $from = !empty($_GET['from']) ? $_GET['from'] : null;
        $to = !empty($_GET['to']) ? $_GET['to'] : null;

        $projectEmissions = $sustainabilityService->getEmissionsByProject($instanceId, $from, $to);

        echo json_encode(['success' => true, 'data' => $projectEmissions]);
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
