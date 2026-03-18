<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/InsuranceService.php';

if (!$AUTH->instancePermissionCheck("INSURANCE:VIEW")) {
    finish(false, ["code" => "PERMISSIONS"]);
}

$svc = new InsuranceService($DBLIB);
$instanceId = $AUTH->data['instance']['instances_id'];
$daysAhead = intval($_GET['days_ahead'] ?? 30);

$expiringPolicies = $svc->getExpiringPolicies($instanceId, $daysAhead);

finish(true, null, ['expiring_policies' => $expiringPolicies]);
