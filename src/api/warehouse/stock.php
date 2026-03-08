<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/WarehouseService.php';

if (!$AUTH->instancePermissionCheck("ASSETS:VIEW")) die("404");

if (empty($_POST['warehouse_id'])) finish(false, ["message" => "Lager-ID erforderlich"]);

$service = new WarehouseService($DBLIB);

// Sicherstellen, dass das Lager zur Instanz gehoert
$warehouse = $service->getWarehouse((int) $_POST['warehouse_id'], $AUTH->data['instance']['instances_id']);
if (!$warehouse) finish(false, ["message" => "Lager nicht gefunden"]);

if (!empty($_POST['assetTypes_id'])) {
    // Wo ist ein bestimmter Equipment-Typ gelagert?
    $locations = $service->getAssetLocations((int) $_POST['assetTypes_id']);
    finish(true, ["locations" => $locations]);
} else {
    // Alle Bestaende eines Lagers
    $stock = $service->getStockByWarehouse((int) $_POST['warehouse_id']);
    finish(true, ["stock" => $stock, "warehouse" => $warehouse]);
}
