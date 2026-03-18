<?php

/**
 * GET /api/errors/detail.php
 * Get detailed error information
 *
 * Query parameters:
 *   - id: Error log ID (required)
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

    $errorId = (int)($_GET['id'] ?? 0);
    if (!$errorId) {
        http_response_code(400);
        echo json_encode(['error' => 'Error ID required']);
        exit;
    }

    $service = new ErrorTerminalService($DBLIB);
    $error = $service->getError($errorId);

    if (!$error || $error['instances_id'] != $instanceId) {
        http_response_code(404);
        echo json_encode(['error' => 'Error not found']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'data' => $error,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
