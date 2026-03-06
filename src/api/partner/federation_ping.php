<?php
/**
 * Verbindung zu einem Partner-Server testen
 *
 * POST-Parameter:
 *   server_id - partner_servers_id
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/FederationService.php';

if (!$AUTH->instancePermissionCheck("PARTNERS:VIEW") && !$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_SETTINGS:VIEW")) {
    finish(false, ["code" => "PERMISSIONS"]);
}

$instanceId = $AUTH->data['instance']['instances_id'];
$serverId = (int)($_POST['server_id'] ?? 0);

if ($serverId <= 0) finish(false, ['code' => 'INVALID']);

$federation = new FederationService($DBLIB);
$result = $federation->pingServer($serverId);

if ($result['success']) {
    finish(true, null, $result);
} else {
    finish(false, ['message' => 'Server nicht erreichbar.', 'latency_ms' => $result['latency_ms']]);
}
