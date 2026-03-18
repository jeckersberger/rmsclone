<?php

/**
 * POST /api/errors/resolve.php
 * Mark error(s) as resolved
 *
 * POST body (JSON):
 *   - ids: Array of error log IDs or single ID (required)
 *   - note: Resolution note (optional)
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

// Permission check
if (!$AUTH || !$AUTH->instancePermissionCheck('SYSTEM:ADMIN')) {
    http_response_code(403);
    echo json_encode(['error' => 'Permission denied']);
    exit;
}

try {
    $instanceId = (int)($AUTH->data['instance']['instances_id'] ?? 0);
    $userId = (int)($AUTH->data['users_userid'] ?? 0);

    if (!$instanceId || !$userId) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid user/instance']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: [];

    // Get IDs - can be single ID or array
    $ids = $input['ids'] ?? [];
    if (!is_array($ids)) {
        $ids = [(int)$ids];
    }
    $ids = array_filter(array_map('intval', $ids));

    if (empty($ids)) {
        http_response_code(400);
        echo json_encode(['error' => 'No error IDs provided']);
        exit;
    }

    $note = $input['note'] ?? null;

    $service = new ErrorTerminalService($DBLIB);

    // Verify all IDs belong to this instance
    foreach ($ids as $id) {
        $error = $service->getError($id);
        if (!$error || $error['instances_id'] != $instanceId) {
            http_response_code(404);
            echo json_encode(['error' => "Error $id not found or access denied"]);
            exit;
        }
    }

    // Resolve
    if (count($ids) === 1) {
        $service->resolveError($ids[0], $userId, $note);
    } else {
        $service->resolveMultiple($ids, $userId, $note);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Errors marked as resolved',
        'count' => count($ids),
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
