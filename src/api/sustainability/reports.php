<?php

/**
 * Sustainability Reports API
 *
 * GET: List all reports for the instance
 * POST: Generate a new report
 *
 * POST parameters:
 *   - report_type: 'monthly', 'quarterly', 'annual', 'project', 'client'
 *   - period_start: Start date (Y-m-d)
 *   - period_end: End date (Y-m-d)
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
        $reports = $sustainabilityService->getReports($instanceId);
        echo json_encode(['success' => true, 'data' => $reports]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Require CONFIGURE permission for generation
        if (!checkAccess('SUSTAINABILITY', 'CONFIGURE')) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        $reportType = $input['report_type'] ?? null;
        $periodStart = $input['period_start'] ?? null;
        $periodEnd = $input['period_end'] ?? null;

        if (!$reportType || !$periodStart || !$periodEnd) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required parameters: report_type, period_start, period_end']);
            exit;
        }

        $userId = $_SESSION['user_id'] ?? 0;
        $reportId = $sustainabilityService->generateReport($reportType, $periodStart, $periodEnd, $instanceId, $userId);

        if ($reportId > 0) {
            $report = $sustainabilityService->getReport($reportId);
            echo json_encode(['success' => true, 'data' => $report]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to generate report']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
