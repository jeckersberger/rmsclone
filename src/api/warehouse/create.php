<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/WarehouseService.php';

if (!$AUTH->instancePermissionCheck("ASSETS:CREATE")) die("404");

if (empty($_POST['name'])) finish(false, ["message" => "Name ist erforderlich"]);

$service = new WarehouseService($DBLIB);
$id = $service->createWarehouse($AUTH->data['instance']['instances_id'], [
    'name' => $_POST['name'],
    'address' => $_POST['address'] ?? null,
    'contact_person' => $_POST['contact_person'] ?? null,
    'phone' => $_POST['phone'] ?? null,
    'is_default' => $_POST['is_default'] ?? 0,
]);

if ($id) {
    $bCMS->auditLog("CREATE", "warehouses", "Lager erstellt: " . $_POST['name'] . " (ID: {$id})", $AUTH->data['users_userid']);
    finish(true, ["id" => $id]);
} else {
    finish(false, ["message" => "Lager konnte nicht erstellt werden"]);
}
