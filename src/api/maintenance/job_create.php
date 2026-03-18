<?php
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->instancePermissionCheck("MAINTENANCE:CREATE")) {
    http_response_code(403);
    die(json_encode(['error' => 'Keine Berechtigung']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Nur POST erlaubt']));
}

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

if (!isset($data['asset_id']) || !isset($data['title'])) {
    http_response_code(400);
    die(json_encode(['error' => 'asset_id und title erforderlich']));
}

$maintenanceService = new MaintenanceService($DBLIB);
$data['instances_id'] = $AUTH->data['instance']['instances_id'];

try {
    $jobId = $maintenanceService->createJob($data);
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'job_id' => $jobId]);
} catch (Exception $e) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
?>
