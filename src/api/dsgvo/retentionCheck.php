<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/DsgvoService.php';

if (!$AUTH->instancePermissionCheck("CLIENTS:VIEW")) finish(false, ["message" => "Permission denied"]);

$clientId = intval($_POST['client_id'] ?? 0);
if (!$clientId) finish(false, ["message" => "client_id required"]);

$service = new DsgvoService($DBLIB);
$result = $service->checkRetentionPeriods($clientId, $AUTH->data['instance']['instances_id']);

finish(true, null, $result);
