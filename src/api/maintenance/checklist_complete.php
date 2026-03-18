<?php
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->instancePermissionCheck("MAINTENANCE:EDIT")) {
    http_response_code(403);
    die(json_encode(['error' => 'Keine Berechtigung']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Nur POST erlaubt']));
}

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

if (!isset($data['job_id']) || !isset($data['checklist_id'])) {
    http_response_code(400);
    die(json_encode(['error' => 'job_id und checklist_id erforderlich']));
}

$maintenanceService = new MaintenanceService($DBLIB);

try {
    $results = isset($data['results']) ? $data['results'] : [];
    $resultId = $maintenanceService->completeChecklist(
        $data['job_id'],
        $data['checklist_id'],
        $results,
        $AUTH->data['user']['users_userid']
    );

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'result_id' => $resultId]);
} catch (Exception $e) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
?>
