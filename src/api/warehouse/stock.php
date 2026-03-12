<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/WarehouseService.php';

if (!$AUTH->instancePermissionCheck("ASSETS:VIEW")) finish(false, ["message" => "Permission denied"]);

$warehouseId = intval($_POST['warehouse_id'] ?? 0);
if (!$warehouseId) finish(false, ["message" => "warehouse_id required"]);

try {
    $service = new WarehouseService($DBLIB);

    // Verify warehouse belongs to this instance
    $warehouse = $service->getWarehouse($warehouseId, $AUTH->data['instance']['instances_id']);
    if (!$warehouse) finish(false, ["message" => "Lager nicht gefunden"]);

    $stock = $service->getStockByWarehouse($warehouseId);

    finish(true, null, ['stock' => $stock, 'warehouse' => $warehouse]);
} catch (Exception $e) {
    finish(false, ["message" => "Fehler beim Laden des Bestands: " . $e->getMessage()]);
}
