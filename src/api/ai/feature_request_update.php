<?php
/**
 * Feature Request Update API
 *
 * POST /api/ai/feature_request_update - Update status or add formulation
 *
 * Request body:
 * {
 *   "fr_number": "FR-001",
 *   "action": "formulate" | "update_status",
 *   // For formulate action:
 *   "ai_formulation": "Detailed formulation text...",
 *   "priority": "high|medium|low" (optional),
 *   "estimated_size": "s|m|l|xl" (optional),
 *   // For update_status action:
 *   "status": "idea|formulated|approved|in_progress|done",
 *   "commit_hash": "abc123..." (optional, for done status)
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

if (empty($input['fr_number']) || empty($input['action'])) {
    http_response_code(400);
    die(json_encode(['error' => 'Missing required fields: fr_number, action']));
}

try {
    $service = new FeatureRequestService($db);
    $frNumber = $input['fr_number'];
    $action = $input['action'];

    if ($action === 'formulate') {
        if (empty($input['ai_formulation'])) {
            http_response_code(400);
            die(json_encode(['error' => 'Missing required field: ai_formulation']));
        }

        $success = $service->formulateRequest(
            $frNumber,
            $input['ai_formulation'],
            $input['priority'] ?? null,
            $input['estimated_size'] ?? null,
            $instanceId,
        );

        if ($success) {
            echo json_encode([
                'success' => true,
                'message' => "Feature request {$frNumber} formulated",
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => "Feature request {$frNumber} not found"]);
        }

    } elseif ($action === 'update_status') {
        if (empty($input['status'])) {
            http_response_code(400);
            die(json_encode(['error' => 'Missing required field: status']));
        }

        $success = $service->updateStatus(
            $frNumber,
            $input['status'],
            $input['commit_hash'] ?? null,
            $instanceId,
        );

        if ($success) {
            echo json_encode([
                'success' => true,
                'message' => "Feature request {$frNumber} status updated to {$input['status']}",
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => "Feature request {$frNumber} not found"]);
        }

    } else {
        http_response_code(400);
        die(json_encode(['error' => 'Invalid action. Must be: formulate or update_status']));
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'API error: ' . $e->getMessage()]);
}
