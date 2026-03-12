<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_SETTINGS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$year = (int)($_POST['year'] ?? date('Y'));

$svc = new EuerService($DBLIB);
$report = $svc->getReport($instanceId, $year);
$monthly = $svc->getMonthlySummary($instanceId, $year);

finish(true, null, [
    "report" => $report,
    "monthly" => $monthly,
]);
