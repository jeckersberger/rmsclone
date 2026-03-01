<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/InventoryService.php';

if (!$AUTH->instancePermissionCheck("ASSETS:VIEW")) finish(false, ["message" => "Permission denied"]);

$sessionId = intval($_POST['session_id'] ?? 0);
if (!$sessionId) finish(false, ["message" => "session_id required"]);

$service = new InventoryService($DBLIB);
$progress = $service->getSessionProgress($sessionId, $AUTH->data['instance']['instances_id']);

finish(true, null, $progress);
