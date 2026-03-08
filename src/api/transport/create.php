<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/TransportService.php';

if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["message" => "Permission denied"]);

$plannedDate = filter_input(INPUT_POST, 'planned_date', FILTER_SANITIZE_SPECIAL_CHARS);
if (!$plannedDate) finish(false, ["message" => "planned_date required"]);

try {
    $data = [
        'projects_id' => intval($_POST['projects_id'] ?? 0) ?: null,
        'from_warehouse_id' => intval($_POST['from_warehouse_id'] ?? 0) ?: null,
        'to_warehouse_id' => intval($_POST['to_warehouse_id'] ?? 0) ?: null,
        'to_address' => $_POST['to_address'] ?? null,
        'driver_name' => $_POST['driver_name'] ?? null,
        'vehicle' => $_POST['vehicle'] ?? null,
        'planned_date' => $plannedDate,
        'planned_time' => $_POST['planned_time'] ?? null,
        'notes' => $_POST['notes'] ?? null,
    ];

    $service = new TransportService($DBLIB);
    $id = $service->createPlan($AUTH->data['instance']['instances_id'], $data);

    finish($id > 0, $id > 0 ? null : ["message" => "Transportplan konnte nicht erstellt werden"], ['id' => $id]);
} catch (Exception $e) {
    finish(false, ["message" => "Fehler beim Erstellen: " . $e->getMessage()]);
}
