<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/WarehouseService.php';

if (!$AUTH->instancePermissionCheck("ASSETS:VIEW")) finish(false, ["message" => "Permission denied"]);

try {
    $service = new WarehouseService($DBLIB);
    $warehouses = $service->getWarehouses($AUTH->data['instance']['instances_id']);

    finish(true, null, ['warehouses' => $warehouses]);
} catch (Exception $e) {
    finish(false, ["message" => "Fehler beim Laden der Lager: " . $e->getMessage()]);
}
