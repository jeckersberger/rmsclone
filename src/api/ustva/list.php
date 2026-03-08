<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_SETTINGS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$year = (int)($_POST['year'] ?? date('Y'));

if ($year < 2000 || $year > 2100) finish(false, ["code" => "INVALID", "message" => "Ungueltiges Jahr"]);

$svc = new UstvaService($DBLIB);
$overview = $svc->getAnnualOverview($instanceId, $year);

finish(true, null, [
    "year" => $year,
    "overview" => $overview,
]);
