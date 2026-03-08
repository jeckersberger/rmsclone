<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/WarehouseService.php';

if (!$AUTH->instancePermissionCheck("ASSETS:VIEW")) finish(false, ["message" => "Permission denied"]);

$assetTypesId = intval($_POST['assetTypes_id'] ?? 0);
if (!$assetTypesId) finish(false, ["message" => "assetTypes_id required"]);

try {
    $service = new WarehouseService($DBLIB);
    $locations = $service->getAssetLocations($assetTypesId);

    finish(true, null, ['locations' => $locations]);
} catch (Exception $e) {
    finish(false, ["message" => "Fehler beim Laden der Standorte: " . $e->getMessage()]);
}
