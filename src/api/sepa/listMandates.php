<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$clientId = isset($_POST['clients_id']) ? (int)$_POST['clients_id'] : null;
$status = $_POST['status'] ?? null;

$svc = new SepaService($DBLIB);

if ($clientId) {
    $mandates = $svc->getMandatesByClient($clientId, $instanceId);
} else {
    $mandates = $svc->getMandatesByInstance($instanceId, $status);
}

finish(true, null, ["mandates" => $mandates]);
