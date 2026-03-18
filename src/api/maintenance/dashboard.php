<?php
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->instancePermissionCheck("MAINTENANCE:VIEW")) {
    http_response_code(403);
    die(json_encode(['error' => 'Keine Berechtigung']));
}

$maintenanceService = new MaintenanceService($DBLIB);
$stats = $maintenanceService->getDashboardStats($AUTH->data['instance']['instances_id']);

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'stats' => $stats
]);
?>
