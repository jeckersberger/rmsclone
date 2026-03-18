<?php
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->instancePermissionCheck("MAINTENANCE:VIEW")) {
    http_response_code(403);
    die(json_encode(['error' => 'Keine Berechtigung']));
}

$maintenanceService = new MaintenanceService($DBLIB);
$overdue = $maintenanceService->getOverdueMaintenances($AUTH->data['instance']['instances_id']);

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'count' => count($overdue),
    'maintenances' => $overdue
]);
?>
