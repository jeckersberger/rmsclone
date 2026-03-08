<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/TransportService.php';

if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["message" => "Permission denied"]);

$itemId = intval($_POST['item_id'] ?? 0);
if (!$itemId) finish(false, ["message" => "item_id required"]);

try {
    $service = new TransportService($DBLIB);
    $result = $service->markDelivered($itemId);

    finish($result, $result ? null : ["message" => "Position konnte nicht als geliefert markiert werden"]);
} catch (Exception $e) {
    finish(false, ["message" => "Fehler: " . $e->getMessage()]);
}
