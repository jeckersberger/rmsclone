<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/TransportService.php';

if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["message" => "Permission denied"]);

$planId = intval($_POST['plan_id'] ?? 0);
$assetTypesId = intval($_POST['assetTypes_id'] ?? 0);
$quantity = intval($_POST['quantity'] ?? 0);

if (!$planId) finish(false, ["message" => "plan_id required"]);
if (!$assetTypesId) finish(false, ["message" => "assetTypes_id required"]);
if ($quantity < 1) finish(false, ["message" => "quantity must be at least 1"]);

try {
    $service = new TransportService($DBLIB);

    // Verify plan belongs to this instance
    $plan = $service->getPlan($planId, $AUTH->data['instance']['instances_id']);
    if (!$plan) finish(false, ["message" => "Transportplan nicht gefunden"]);

    $id = $service->addItem($planId, $assetTypesId, $quantity);

    finish($id > 0, $id > 0 ? null : ["message" => "Position konnte nicht hinzugefuegt werden"], ['id' => $id]);
} catch (Exception $e) {
    finish(false, ["message" => "Fehler beim Hinzufuegen: " . $e->getMessage()]);
}
