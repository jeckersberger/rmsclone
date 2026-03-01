<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/MaintenanceScheduleService.php';

if (!$AUTH->instancePermissionCheck("ASSETS:VIEW")) finish(false, ["message" => "Permission denied"]);

$assetId = intval($_POST['asset_id'] ?? 0);
if (!$assetId) finish(false, ["message" => "asset_id required"]);

$service = new MaintenanceScheduleService($DBLIB);
$history = $service->getHistory($assetId);

finish(true, null, ['history' => $history]);
