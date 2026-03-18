<?php

/**
 * POST /api/errors/clear.php
 * Clear old resolved errors
 *
 * POST body (JSON):
 *   - days_to_keep: Number of days to keep (default 90)
 */

require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/ErrorTerminalService.php';

use Rms\Services\ErrorTerminalService;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Permission check - only admins can clear
if (!$AUTH || !$AUTH->instancePermissionCheck('SYSTEM:ADMIN')) {
    http_response_code(403);
    echo json_encode(['error' => 'Permission denied']);
    exit;
}

try {
    $instanceId = (int)($AUTH->data['instance']['instances_id'] ?? 0);
    if (!$instanceId) {
        http_response_code(400);
        echo json_encode(['error' => 'Instance not set']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $daysToKeep = (int)($input['days_to_keep'] ?? 90);

    // Sanity check - keep at least 1 day, at most 1 year
    $daysToKeep = max(1, min(365, $daysToKeep));

    $service = new ErrorTerminalService($DBLIB);
    $deleted = $service->clearOld($daysToKeep);

    echo json_encode([
        'success' => true,
        'message' => "Deleted $deleted old error records",
        'count' => $deleted,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
