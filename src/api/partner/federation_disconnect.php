<?php
/**
 * Verbindung zu einem Partner-Server trennen
 *
 * POST-Parameter:
 *   server_id - partner_servers_id
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/FederationService.php';

if (!$AUTH->instancePermissionCheck("PARTNERS:DELETE") && !$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_SETTINGS:EDIT")) {
    finish(false, ["code" => "PERMISSIONS"]);
}

$instanceId = $AUTH->data['instance']['instances_id'];
$serverId = (int)($_POST['server_id'] ?? 0);

if ($serverId <= 0) finish(false, ['code' => 'INVALID']);

$federation = new FederationService($DBLIB);
$result = $federation->disconnect($serverId, $instanceId);

finish($result);
