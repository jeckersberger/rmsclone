<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$assetId = (int)($_POST['asset_id'] ?? 0);
$startDate = $_POST['start'] ?? date('Y-m-01');
$endDate = $_POST['end'] ?? date('Y-m-t');

if ($assetId <= 0) finish(false, ["code" => "INVALID"]);

$svc = new AvailabilityService($DBLIB);
$events = $svc->getCalendarEvents($assetId, $startDate, $endDate);

finish(true, null, ["events" => $events]);
