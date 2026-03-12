<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$assetId = (int)($_POST['asset_id'] ?? 0);
$projectId = (int)($_POST['project_id'] ?? 0);
$condition = trim($_POST['condition'] ?? 'good');
$notes = trim($_POST['notes'] ?? '');

if ($assetId <= 0 || $projectId <= 0) finish(false, ["code" => "INVALID"]);

$svc = new CheckInOutService($DBLIB);
$id = $svc->checkOut($instanceId, $assetId, $projectId, $AUTH->data['users_userid'], [
    'condition' => $condition,
    'notes' => $notes ?: null,
]);

finish(true, null, ["id" => $id]);
