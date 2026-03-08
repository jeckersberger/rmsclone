<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/WarehouseService.php';

if (!$AUTH->instancePermissionCheck("ASSETS:CREATE")) die("404");

$required = ['assetTypes_id', 'from_warehouse_id', 'to_warehouse_id', 'quantity'];
foreach ($required as $field) {
    if (empty($_POST[$field])) finish(false, ["message" => "Feld '{$field}' ist erforderlich"]);
}

$service = new WarehouseService($DBLIB);
$instanceId = $AUTH->data['instance']['instances_id'];

// Pruefen, dass beide Lager zur Instanz gehoeren
$from = $service->getWarehouse((int) $_POST['from_warehouse_id'], $instanceId);
$to = $service->getWarehouse((int) $_POST['to_warehouse_id'], $instanceId);
if (!$from || !$to) finish(false, ["message" => "Lager nicht gefunden"]);

if ($_POST['from_warehouse_id'] == $_POST['to_warehouse_id']) {
    finish(false, ["message" => "Quell- und Ziellager muessen unterschiedlich sein"]);
}

$result = $service->transferAsset(
    (int) $_POST['assetTypes_id'],
    (int) $_POST['from_warehouse_id'],
    (int) $_POST['to_warehouse_id'],
    (int) $_POST['quantity']
);

if ($result) {
    $bCMS->auditLog("TRANSFER", "asset_warehouse_assignments",
        "Umlagerung: {$_POST['quantity']}x AssetType {$_POST['assetTypes_id']} von Lager {$from['name']} nach {$to['name']}",
        $AUTH->data['users_userid']);
    finish(true);
} else {
    finish(false, ["message" => "Umlagerung fehlgeschlagen - nicht genug Bestand im Quelllager"]);
}
