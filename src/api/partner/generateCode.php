<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_SETTINGS:EDIT")) finish(false, ["code" => "PERMISSIONS"]);

$svc = new PartnerService($DBLIB);
$code = $svc->generatePartnerCode($AUTH->data['instance']['instances_id']);

finish(true, null, ['partner_code' => $code]);
