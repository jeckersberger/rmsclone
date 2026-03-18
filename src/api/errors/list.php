<?php

/**
 * GET /api/errors/list.php
 * List system errors with filtering
 *
 * Query parameters:
 *   - level: debug|info|warning|error|critical (optional)
 *   - unresolved_only: 1|0 (optional, default 0)
 *   - search: Search in message (optional)
 *   - from: Start date YYYY-MM-DD (optional)
 *   - to: End date YYYY-MM-DD (optional)
 *   - limit: Results per page (default 50)
 *   - offset: Pagination offset (default 0)
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

    $level = $_GET['level'] ?? null;
    $unresolvedOnly = isset($_GET['unresolved_only']) ? (bool)(int)$_GET['unresolved_only'] : false;
    $search = $_GET['search'] ?? null;
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = (int)($_GET['offset'] ?? 0);
    $from = $_GET['from'] ?? null;
    $to = $_GET['to'] ?? null;

    $limit = min($limit, 500); // Max 500 per request

    // Get errors
    $errors = $service->getErrors($instanceId, $level, $unresolvedOnly, $limit, $offset);

    // If search is provided, filter in memory
    if ($search) {
        $search = strtolower($search);
        $errors = array_filter($errors, function ($error) use ($search) {
            return stripos($error['message'], $search) !== false ||
                   stripos($error['source'], $search) !== false;
        });
    }

    // If date range provided, filter in memory
    if ($from || $to) {
        $errors = array_filter($errors, function ($error) use ($from, $to) {
            $created = substr($error['created_at'], 0, 10);
            if ($from && $created < $from) {
                return false;
            }
            if ($to && $created > $to) {
                return false;
            }
            return true;
        });
    }

    // Format response
    foreach ($errors as &$error) {
        if ($error['context']) {
            $error['context'] = json_decode($error['context'], true);
        }
        // Don't include stack trace in list view for performance
        unset($error['stack_trace']);
    }

    echo json_encode([
        'success' => true,
        'data' => array_values($errors),
        'count' => count($errors),
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
