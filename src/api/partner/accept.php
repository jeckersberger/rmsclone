<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_SETTINGS:EDIT")) finish(false, ["code" => "PERMISSIONS"]);

$linkId = filter_var($_POST['link_id'] ?? 0, FILTER_VALIDATE_INT);
if (!$linkId || $linkId <= 0) finish(false, ["code" => "INVALID", "message" => "Valid link_id required"]);

$svc = new PartnerService($DBLIB);
$result = $svc->acceptInvitation($linkId, $AUTH->data['instance']['instances_id']);

if ($result) {
    finish(true, null, ["message" => "Partnership accepted"]);
} else {
    finish(false, ["code" => "FAILED", "message" => "Could not accept invitation"]);
}
