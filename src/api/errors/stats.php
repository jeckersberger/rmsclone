<?php

/**
 * GET /api/errors/stats.php
 * Get error dashboard statistics
 */

require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/ErrorTerminalService.php';

use Rms\Services\ErrorTerminalService;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Permission check
if (!$AUTH || !$AUTH->instancePermissionCheck('SYSTEM:VIEW')) {
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

    $service = new ErrorTerminalService($DBLIB);
    $stats = $service->getStats($instanceId);

    echo json_encode([
        'success' => true,
        'data' => $stats,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
