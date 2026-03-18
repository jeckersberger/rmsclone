<?php
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->instancePermissionCheck("MAINTENANCE:VIEW")) {
    http_response_code(403);
    die(json_encode(['error' => 'Keine Berechtigung']));
}

$assetId = isset($_GET['asset_id']) ? (int) $_GET['asset_id'] : null;

if (!$assetId) {
    http_response_code(400);
    die(json_encode(['error' => 'asset_id erforderlich']));
}

$maintenanceService = new MaintenanceService($DBLIB);
$jobs = $maintenanceService->getJobsForAsset($assetId, $AUTH->data['instance']['instances_id']);
$costs = $maintenanceService->getMaintenanceCostsByAsset($assetId, $AUTH->data['instance']['instances_id']);

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'jobs' => $jobs,
    'costs' => $costs
]);
?>
