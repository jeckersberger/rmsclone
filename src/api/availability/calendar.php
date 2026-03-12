<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$assetId = (int)($_POST['asset_id'] ?? 0);
$startDate = $_POST['start'] ?? date('Y-m-01');
$endDate = $_POST['end'] ?? date('Y-m-t');

if ($assetId <= 0) finish(false, ["code" => "INVALID"]);

// Verify asset belongs to current instance
$DBLIB->where('assets_id', $assetId);
$DBLIB->where('instances_id', $AUTH->data['instance']['instances_id']);
if (!$DBLIB->getOne('assets', ['assets_id'])) finish(false, ["code" => "NOT_FOUND"]);

$svc = new AvailabilityService($DBLIB);
$events = $svc->getCalendarEvents($assetId, $startDate, $endDate);

finish(true, null, ["events" => $events]);
