<?php
/**
 * Equipment von einem verbundenen Partner-Server abrufen
 *
 * POST-Parameter:
 *   server_id  - partner_servers_id
 *   start_date - optional (Y-m-d)
 *   end_date   - optional (Y-m-d)
 *   search     - optional
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/FederationService.php';

if (!$AUTH->instancePermissionCheck("PARTNERS:VIEW") && !$AUTH->instancePermissionCheck("PROJECTS:VIEW")) {
    finish(false, ["code" => "PERMISSIONS"]);
}

$instanceId = $AUTH->data['instance']['instances_id'];
$serverId = (int)($_POST['server_id'] ?? 0);
$startDate = $_POST['start_date'] ?? null;
$endDate = $_POST['end_date'] ?? null;
$search = $_POST['search'] ?? null;

if ($serverId <= 0) finish(false, ['code' => 'INVALID']);

$federation = new FederationService($DBLIB);
$equipment = $federation->fetchPartnerEquipment($serverId, $startDate, $endDate, $search);

finish(true, null, ['equipment' => $equipment]);
