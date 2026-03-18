<?php
/**
 * Feature Requests API
 *
 * GET /api/ai/feature_requests - List all feature requests
 * GET /api/ai/feature_requests?status=pending - List pending (idea/formulated) requests
 * POST /api/ai/feature_requests - Create new feature request
 *
 * Request body for POST:
 * {
 *   "title": "Feature short title",
 *   "user_idea": "User's description of the feature idea"
 * }
 */

require_once __DIR__ . '/../apiHeadSecure.php';

if (!$user || !$perms->hasPerm('AI:VIEW')) {
    http_response_code(403);
    die(json_encode(['error' => 'Permission denied']));
}

$instanceId = (int)$_SESSION['instance_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    $service = new FeatureRequestService($db);

    if ($method === 'GET') {
        // List requests
        $status = $_GET['status'] ?? null;

        if ($status === 'pending') {
            $requests = $service->getPendingRequests($instanceId);
        } else {
            $requests = $service->getAllRequests($instanceId);
        }

        echo json_encode([
            'success' => true,
            'count' => count($requests),
            'requests' => $requests,
        ]);

    } elseif ($method === 'POST') {
        // Create new request
        if (!$perms->hasPerm('AI:CONFIGURE')) {
            http_response_code(403);
            die(json_encode(['error' => 'Permission denied. Requires AI:CONFIGURE']));
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['title']) || empty($input['user_idea'])) {
            http_response_code(400);
            die(json_encode(['error' => 'Missing required fields: title, user_idea']));
        }

        $frNumber = $service->addRequest(
            $input['title'],
            $input['user_idea'],
            $instanceId,
        );

        echo json_encode([
            'success' => true,
            'fr_number' => $frNumber,
            'message' => 'Feature request created',
        ]);

    } else {
        http_response_code(405);
        die(json_encode(['error' => 'Method not allowed']));
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'API error: ' . $e->getMessage()]);
}
