<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$from = $_POST['from'] ?? date('Y-m-01');
$to = $_POST['to'] ?? date('Y-m-t');

$svc = new ReportsService($DBLIB);
$report = $svc->utilizationReport($instanceId, $from, $to);

finish(true, null, ["report" => $report]);
