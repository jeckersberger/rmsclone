<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/TransportService.php';

if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) die("404");

if (empty($_POST['planned_date'])) finish(false, ["message" => "Datum ist erforderlich"]);

$service = new TransportService($DBLIB);
$instanceId = $AUTH->data['instance']['instances_id'];

$id = $service->createPlan($instanceId, [
    'projects_id' => $_POST['projects_id'] ?? null,
    'from_warehouse_id' => $_POST['from_warehouse_id'] ?? null,
    'to_warehouse_id' => $_POST['to_warehouse_id'] ?? null,
    'to_address' => $_POST['to_address'] ?? null,
    'driver_name' => $_POST['driver_name'] ?? null,
    'vehicle' => $_POST['vehicle'] ?? null,
    'planned_date' => $_POST['planned_date'],
    'planned_time' => $_POST['planned_time'] ?? null,
    'notes' => $_POST['notes'] ?? null,
]);

if (!$id) finish(false, ["message" => "Transportplan konnte nicht erstellt werden"]);

// Positionen hinzufuegen, falls uebergeben
if (!empty($_POST['items']) && is_array($_POST['items'])) {
    foreach ($_POST['items'] as $item) {
        if (!empty($item['assetTypes_id']) && !empty($item['quantity'])) {
            $service->addItem($id, (int) $item['assetTypes_id'], (int) $item['quantity']);
        }
    }
}

$bCMS->auditLog("CREATE", "transport_plans", "Transportplan erstellt (ID: {$id})", $AUTH->data['users_userid']);
finish(true, ["id" => $id]);
