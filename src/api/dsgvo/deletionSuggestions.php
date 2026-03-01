<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/DsgvoService.php';

if (!$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_SETTINGS:VIEW")) finish(false, ["message" => "Permission denied"]);

$service = new DsgvoService($DBLIB);
$clients = $service->getClientsReadyForDeletion($AUTH->data['instance']['instances_id']);

finish(true, null, ['clients' => $clients]);
