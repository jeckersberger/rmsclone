<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/WarehouseService.php';

if (!$AUTH->instancePermissionCheck("ASSETS:VIEW")) finish(false, ["message" => "Permission denied"]);

$name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS);
if (!$name) finish(false, ["message" => "name required"]);

try {
    $data = [
        'name' => $name,
        'address' => $_POST['address'] ?? null,
        'contact_person' => $_POST['contact_person'] ?? null,
        'phone' => $_POST['phone'] ?? null,
        'is_default' => intval($_POST['is_default'] ?? 0),
    ];

    $service = new WarehouseService($DBLIB);
    $id = $service->createWarehouse($AUTH->data['instance']['instances_id'], $data);

    finish($id > 0, $id > 0 ? null : ["message" => "Lager konnte nicht erstellt werden"], ['id' => $id]);
} catch (Exception $e) {
    finish(false, ["message" => "Fehler beim Erstellen: " . $e->getMessage()]);
}
