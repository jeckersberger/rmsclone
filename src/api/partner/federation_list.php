<?php
/**
 * Verbundene Partner-Server auflisten
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/FederationService.php';

if (!$AUTH->instancePermissionCheck("PARTNERS:VIEW") && !$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_SETTINGS:VIEW")) {
    finish(false, ["code" => "PERMISSIONS"]);
}

$instanceId = $AUTH->data['instance']['instances_id'];
$federation = new FederationService($DBLIB);

$servers = $federation->getConnectedServers($instanceId);

finish(true, null, ['servers' => $servers]);
