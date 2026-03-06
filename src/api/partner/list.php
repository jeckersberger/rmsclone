<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PARTNERS:VIEW") && !$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_SETTINGS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$svc = new PartnerService($DBLIB);
$partnerships = $svc->getPartnerships($AUTH->data['instance']['instances_id']);
$pending = $svc->getPendingInvitations($AUTH->data['instance']['instances_id']);

finish(true, null, [
    'partnerships' => $partnerships,
    'pending_invitations' => $pending,
    'my_partner_code' => $AUTH->data['instance']['instances_partnerCode'] ?? null,
]);
