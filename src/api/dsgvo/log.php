<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/DsgvoService.php';

if (!$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_SETTINGS:VIEW")) finish(false, ["message" => "Permission denied"]);

$service = new DsgvoService($DBLIB);

$clientId = !empty($_POST['client_id']) ? intval($_POST['client_id']) : null;
$log = $service->getDsgvoLog($AUTH->data['instance']['instances_id'], $clientId);

finish(true, null, ['log' => $log]);
