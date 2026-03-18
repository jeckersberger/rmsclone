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
$scheduleId = isset($data['id']) ? (int) $data['id'] : null;

if (!$scheduleId) {
    http_response_code(400);
    die(json_encode(['error' => 'ID erforderlich']));
}

$maintenanceService = new MaintenanceService($DBLIB);

try {
    $success = $maintenanceService->deleteSchedule($scheduleId);
    header('Content-Type: application/json');
    echo json_encode(['success' => $success]);
} catch (Exception $e) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
?>
