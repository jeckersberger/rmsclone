<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$from = $_POST['from'] ?? date('Y-01-01');
$to = $_POST['to'] ?? date('Y-m-d');
$groupBy = $_POST['group_by'] ?? 'month';

$svc = new ReportsService($DBLIB);
$report = $svc->revenueReport($instanceId, $from, $to, $groupBy);

finish(true, null, ["report" => $report]);
