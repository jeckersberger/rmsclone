<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/DsgvoService.php';

if (!$AUTH->instancePermissionCheck("CLIENTS:EDIT")) finish(false, ["message" => "Permission denied"]);

$clientId = intval($_POST['client_id'] ?? 0);
if (!$clientId) finish(false, ["message" => "client_id required"]);

$service = new DsgvoService($DBLIB);
$result = $service->anonymizeClient($clientId, $AUTH->data['instance']['instances_id'], $AUTH->data['users_userid']);

finish($result['success'], $result['success'] ? null : ["message" => $result['error'] ?? 'Unknown error'], $result);
