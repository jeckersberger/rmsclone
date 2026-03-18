<?php

/**
 * Client Sustainability Report API
 *
 * GET: Generate a client-facing sustainability report
 *
 * Query parameters:
 *   - client_id: Client ID (required)
 *   - from: Period start date (Y-m-d)
 *   - to: Period end date (Y-m-d)
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

        $from = $_GET['from'] ?? date('Y-m-01');
        $to = $_GET['to'] ?? date('Y-m-t');

        $report = $sustainabilityService->generateClientReport($clientId, $from, $to, $instanceId);

        echo json_encode(['success' => true, 'data' => $report]);
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
