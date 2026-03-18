<?php
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->instancePermissionCheck("MAINTENANCE:VIEW")) {
    http_response_code(403);
    die(json_encode(['error' => 'Keine Berechtigung']));
}

$maintenanceService = new MaintenanceService($DBLIB);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // GET: Wartungspläne auflisten
    $assetId = isset($_GET['asset_id']) ? (int) $_GET['asset_id'] : null;
    $assetTypeId = isset($_GET['asset_type_id']) ? (int) $_GET['asset_type_id'] : null;

    $schedules = [];

    if ($assetId) {
        $schedules = $maintenanceService->getSchedulesForAsset($assetId, $AUTH->data['instance']['instances_id']);
    } elseif ($assetTypeId) {
        $schedules = $maintenanceService->getSchedulesForAssetType($assetTypeId, $AUTH->data['instance']['instances_id']);
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'schedules' => $schedules]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // POST: Neuen Wartungsplan erstellen
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
        $scheduleId = $maintenanceService->createSchedule($data);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'schedule_id' => $scheduleId]);
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
