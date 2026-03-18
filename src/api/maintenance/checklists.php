<?php
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->instancePermissionCheck("MAINTENANCE:VIEW")) {
    http_response_code(403);
    die(json_encode(['error' => 'Keine Berechtigung']));
}

$maintenanceService = new MaintenanceService($DBLIB);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // GET: Checklisten auflisten
    $checklists = $maintenanceService->getChecklists($AUTH->data['instance']['instances_id']);
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'checklists' => $checklists]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // POST: Neue Checkliste erstellen
    if (!$AUTH->instancePermissionCheck("MAINTENANCE:CREATE")) {
        http_response_code(403);
        die(json_encode(['error' => 'Keine Berechtigung']));
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    if (!isset($data['name'])) {
        http_response_code(400);
        die(json_encode(['error' => 'Name erforderlich']));
    }

    $data['instances_id'] = $AUTH->data['instance']['instances_id'];

    try {
        $checklistId = $maintenanceService->createChecklist($data);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'checklist_id' => $checklistId]);
    } catch (Exception $e) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
    }

} else {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Methode nicht erlaubt']);
}
?>
