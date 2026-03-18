<?php
/**
 * Implementation Update API
 *
 * POST /api/ai/implementation_update - Update baustein status
 *
 * Request body:
 * {
 *   "module_code": "L1",
 *   "baustein_nr": 5,
 *   "status": "done|review|open|needs_tests"
 * }
 */

require_once __DIR__ . '/../apiHeadSecure.php';

if (!$user || !$perms->hasPerm('AI:CONFIGURE')) {
    http_response_code(403);
    die(json_encode(['error' => 'Permission denied. Requires AI:CONFIGURE']));
}

$instanceId = (int)$_SESSION['instance_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Method not allowed']));
}

$input = json_decode(file_get_contents('php://input'), true);

$required = ['module_code', 'baustein_nr', 'status'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        die(json_encode(['error' => "Missing required field: {$field}"]));
    }
}

try {
    $service = new ImplementationTrackerService($db);

    $success = $service->updateStatus(
        $input['module_code'],
        (int)$input['baustein_nr'],
        $input['status'],
        $instanceId,
    );

    if ($success) {
        // Log the update
        $service->logTask(
            'update_baustein',
            $input['module_code'],
            "Updated {$input['module_code']} baustein {$input['baustein_nr']} to {$input['status']}",
            null,
            $user['users_id'],
            $instanceId,
        );

        echo json_encode([
            'success' => true,
            'message' => "Baustein status updated to {$input['status']}",
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Baustein not found']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'API error: ' . $e->getMessage()]);
}
