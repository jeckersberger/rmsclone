<?php

/**
 * GET /api/errors/export.php
 * Export error as developer-friendly text
 *
 * Query parameters:
 *   - id: Error log ID (required)
 */

require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/ErrorTerminalService.php';

use Rms\Services\ErrorTerminalService;

header('Content-Type: text/plain; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo "Method not allowed\n";
    exit;
}

// Permission check
if (!$AUTH || !$AUTH->instancePermissionCheck('SYSTEM:VIEW')) {
    http_response_code(403);
    echo "Permission denied\n";
    exit;
}

try {
    $instanceId = (int)($AUTH->data['instance']['instances_id'] ?? 0);
    if (!$instanceId) {
        http_response_code(400);
        echo "Instance not set\n";
        exit;
    }

    $errorId = (int)($_GET['id'] ?? 0);
    if (!$errorId) {
        http_response_code(400);
        echo "Error ID required\n";
        exit;
    }

    $service = new ErrorTerminalService($DBLIB);
    $error = $service->getError($errorId);

    if (!$error || $error['instances_id'] != $instanceId) {
        http_response_code(404);
        echo "Error not found\n";
        exit;
    }

    $formatted = $service->formatForDeveloper($errorId);

    header('Content-Disposition: attachment; filename="error_' . $errorId . '.txt"');
    echo $formatted;

} catch (Exception $e) {
    http_response_code(500);
    echo "Error: " . $e->getMessage() . "\n";
}
