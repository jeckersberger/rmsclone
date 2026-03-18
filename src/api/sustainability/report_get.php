<?php

/**
 * Single Report Details API
 *
 * GET: Retrieve a specific report with full details
 *
 * Query parameters:
 *   - id: Report ID (required)
 */

require_once dirname(__DIR__, 3) . '/bootstrap.php';

$sustainabilityService = new SustainabilityService($db);

header('Content-Type: application/json');

// Permission check
if (!checkAccess('SUSTAINABILITY', 'VIEW')) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $reportId = (int)($_GET['id'] ?? 0);
        if ($reportId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Report ID required']);
            exit;
        }

        $report = $sustainabilityService->getReport($reportId);

        if ($report) {
            echo json_encode(['success' => true, 'data' => $report]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Report not found']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
