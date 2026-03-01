<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_SETTINGS:EDIT")) finish(false, ["code" => "PERMISSIONS"]);

$linkId = (int)($_POST['link_id'] ?? 0);
if ($linkId <= 0) finish(false, ["code" => "INVALID"]);

$svc = new PartnerService($DBLIB);
$result = $svc->acceptInvitation($linkId, $AUTH->data['instance']['instances_id']);

finish($result);
