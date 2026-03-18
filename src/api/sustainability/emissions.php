<?php

/**
 * Emissions Summary API
 *
 * GET: Retrieve emissions summary with optional period and date filters
 *
 * Query parameters:
 *   - period: 'day', 'week', 'month', 'quarter', 'year' (default: month)
 *   - from: Start date (Y-m-d)
 *   - to: End date (Y-m-d)
 */

require_once __DIR__ . '/../apiHeadSecure.php';

$sustainabilityService = new SustainabilityService($DBLIB);

header('Content-Type: application/json');

// Permission check
if (!checkAccess('SUSTAINABILITY', 'VIEW')) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$instanceId = (int)($AUTH->data['instance']['instances_id'] ?? 0);
if ($instanceId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Instance not set']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $period = $_GET['period'] ?? 'month';
        $from = !empty($_GET['from']) ? $_GET['from'] : null;
        $to = !empty($_GET['to']) ? $_GET['to'] : null;

        $summary = $sustainabilityService->getEmissionsSummary($instanceId, $period, $from, $to);

        echo json_encode(['success' => true, 'data' => $summary]);
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
