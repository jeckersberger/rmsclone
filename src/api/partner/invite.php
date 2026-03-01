<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_SETTINGS:EDIT")) finish(false, ["code" => "PERMISSIONS"]);

$partnerCode = trim($_POST['partner_code'] ?? '');
if (!$partnerCode) finish(false, ["code" => "MISSING", "message" => "Partner code required"]);

$svc = new PartnerService($DBLIB);
$result = $svc->sendInvitation($AUTH->data['instance']['instances_id'], $partnerCode, $AUTH->data['users_userid']);

if ($result['success']) {
    finish(true, null, ["partner_name" => $result['partner_name']]);
} else {
    finish(false, ["code" => $result['error']]);
}
