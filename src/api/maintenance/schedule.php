<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/MaintenanceScheduleService.php';

if (!$AUTH->instancePermissionCheck("ASSETS:VIEW")) finish(false, ["message" => "Permission denied"]);

$service = new MaintenanceScheduleService($DBLIB);

$due = $service->getDueMaintenance($AUTH->data['instance']['instances_id']);
$upcoming = $service->getUpcomingMaintenance($AUTH->data['instance']['instances_id']);
$stats = $service->getStats($AUTH->data['instance']['instances_id']);

finish(true, null, [
    'due' => $due,
    'upcoming' => $upcoming,
    'stats' => $stats,
]);
