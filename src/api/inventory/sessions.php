<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/InventoryService.php';

if (!$AUTH->instancePermissionCheck("ASSETS:VIEW")) finish(false, ["message" => "Permission denied"]);

$service = new InventoryService($DBLIB);
$sessions = $service->getSessions($AUTH->data['instance']['instances_id']);

finish(true, null, ['sessions' => $sessions]);
