<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];

$svc = new ReportsService($DBLIB);
$report = $svc->outstandingReport($instanceId);

finish(true, null, ["report" => $report]);
