<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_SETTINGS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$year = (int)($_POST['year'] ?? date('Y'));
$month = (int)($_POST['month'] ?? date('n'));

if ($month < 1 || $month > 12) finish(false, ["code" => "INVALID", "message" => "Ungueltiger Monat"]);
if ($year < 2000 || $year > 2100) finish(false, ["code" => "INVALID", "message" => "Ungueltiges Jahr"]);

$svc = new UstvaService($DBLIB);
$result = $svc->calculateUstva($instanceId, $year, $month);
$saved = $svc->getReport($instanceId, $year, $month);

finish(true, null, [
    "calculated" => $result,
    "saved" => $saved,
]);
