<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$svc = new DunningService($DBLIB);

$overdue = $svc->getOverdueInvoices($instanceId);
$stats = $svc->getDashboardStats($instanceId);
$levels = $svc->getDunningLevels($instanceId);

finish(true, null, [
    "overdue_invoices" => $overdue,
    "stats" => $stats,
    "levels" => $levels,
]);
