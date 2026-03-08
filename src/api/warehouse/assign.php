<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/WarehouseService.php';

if (!$AUTH->instancePermissionCheck("ASSETS:VIEW")) finish(false, ["message" => "Permission denied"]);

$assetTypesId = intval($_POST['assetTypes_id'] ?? 0);
$warehouseId = intval($_POST['warehouse_id'] ?? 0);
$quantity = intval($_POST['quantity'] ?? 0);

if (!$assetTypesId) finish(false, ["message" => "assetTypes_id required"]);
if (!$warehouseId) finish(false, ["message" => "warehouse_id required"]);
if ($quantity < 0) finish(false, ["message" => "quantity must be >= 0"]);

try {
    $service = new WarehouseService($DBLIB);

    // Verify warehouse belongs to this instance
    $warehouse = $service->getWarehouse($warehouseId, $AUTH->data['instance']['instances_id']);
    if (!$warehouse) finish(false, ["message" => "Lager nicht gefunden"]);

    $result = $service->assignAsset($assetTypesId, $warehouseId, $quantity);

    finish($result, $result ? null : ["message" => "Zuweisung fehlgeschlagen"]);
} catch (Exception $e) {
    finish(false, ["message" => "Fehler bei der Zuweisung: " . $e->getMessage()]);
}
