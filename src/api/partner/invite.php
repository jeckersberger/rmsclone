<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PARTNERS:CREATE") && !$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_SETTINGS:EDIT")) finish(false, ["code" => "PERMISSIONS"]);

$partnerCode = trim($_POST['partner_code'] ?? '');
if (!$partnerCode || !preg_match('/^[A-Fa-f0-9]{6,16}$/', $partnerCode)) {
    finish(false, ["code" => "INVALID", "message" => "Valid partner code required (6-16 hex characters)"]);
}

$svc = new PartnerService($DBLIB);
$result = $svc->sendInvitation($AUTH->data['instance']['instances_id'], strtoupper($partnerCode), $AUTH->data['users_userid']);

if ($result['success']) {
    finish(true, null, ["partner_name" => $result['partner_name']]);
} else {
    // Einheitliche Fehlermeldung um Account-Enumeration zu verhindern
    finish(false, ["code" => "LINK_FAILED", "message" => "Could not create partnership link"]);
}
