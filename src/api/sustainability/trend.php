<?php

/**
 * Emissions Trend API
 *
 * GET: Retrieve month-by-month CO2 emissions trend
 *
 * Query parameters:
 *   - months: Number of months to retrieve (default: 12)
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
        $months = (int)($_GET['months'] ?? 12);
        $months = max(1, min($months, 60)); // Limit between 1 and 60

        $trend = $sustainabilityService->getEmissionsTrend($instanceId, $months);

        echo json_encode(['success' => true, 'data' => $trend]);
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
