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
$jobId = isset($data['id']) ? (int) $data['id'] : null;

if (!$jobId) {
    http_response_code(400);
    die(json_encode(['error' => 'ID erforderlich']));
}

$maintenanceService = new MaintenanceService($DBLIB);

try {
    // Status aktualisieren
    if (isset($data['status'])) {
        $maintenanceService->updateJobStatus($jobId, $data['status'], $AUTH->data['user']['users_userid']);
    }

    // Weitere Felder aktualisieren
    $updateData = [];
    if (isset($data['title'])) $updateData['title'] = $data['title'];
    if (isset($data['description'])) $updateData['description'] = $data['description'];
    if (isset($data['assigned_to'])) $updateData['assigned_to'] = $data['assigned_to'];
    if (isset($data['priority'])) $updateData['priority'] = $data['priority'];
    if (isset($data['estimated_cost'])) $updateData['estimated_cost'] = floatval($data['estimated_cost']);
    if (isset($data['actual_cost'])) $updateData['actual_cost'] = floatval($data['actual_cost']);
    if (isset($data['notes'])) $updateData['notes'] = $data['notes'];

    if (!empty($updateData)) {
        $DBLIB->where('id', $jobId);
        $DBLIB->update('maintenance_jobs', $updateData);
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
?>
