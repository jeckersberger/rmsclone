<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/MaintenanceScheduleService.php';

if (!$AUTH->instancePermissionCheck("ASSETS:VIEW")) finish(false, ["message" => "Permission denied"]);

$assetId = intval($_POST['asset_id'] ?? 0);
if (!$assetId) finish(false, ["message" => "asset_id required"]);

$service = new MaintenanceScheduleService($DBLIB);
$result = $service->recordMaintenance($assetId, $AUTH->data['users_userid'], $_POST['notes'] ?? null);

finish($result, $result ? null : ["message" => "Could not record maintenance"]);
