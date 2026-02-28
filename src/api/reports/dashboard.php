<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];

$svc = new ReportsService($DBLIB);
$kpis = $svc->dashboardKPIs($instanceId);

finish(true, null, ["kpis" => $kpis]);
