<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$assetId = (int)($_POST['asset_id'] ?? 0);
$startDate = $_POST['start_date'] ?? '';
$endDate = $_POST['end_date'] ?? '';
$excludeProjectId = isset($_POST['exclude_project_id']) ? (int)$_POST['exclude_project_id'] : null;

if ($assetId <= 0 || !$startDate || !$endDate) finish(false, ["code" => "INVALID"]);

// Verify asset belongs to current instance
$DBLIB->where('assets_id', $assetId);
$DBLIB->where('instances_id', $AUTH->data['instance']['instances_id']);
if (!$DBLIB->getOne('assets', ['assets_id'])) finish(false, ["code" => "NOT_FOUND"]);

$svc = new AvailabilityService($DBLIB);
$conflicts = $svc->getAssetConflicts($assetId, $startDate, $endDate, $excludeProjectId);

finish(true, null, [
    "available" => count($conflicts) === 0,
    "conflicts" => $conflicts,
]);
