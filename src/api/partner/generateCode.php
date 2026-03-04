<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/RateLimitService.php';

if (!$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_SETTINGS:EDIT")) finish(false, ["code" => "PERMISSIONS"]);

// Rate-Limiting: max 5 Code-Generierungen pro Stunde pro Instance
$rateLimiter = new RateLimitService($DBLIB);
$identifier = 'instance_' . $AUTH->data['instance']['instances_id'];
if (!$rateLimiter->isAllowed('partner_code', $identifier)) {
    finish(false, ["code" => "RATE_LIMIT", "message" => "Too many code generations. Please try again later."]);
}
$rateLimiter->recordAttempt('partner_code', $identifier, true);

$svc = new PartnerService($DBLIB);
$code = $svc->generatePartnerCode($AUTH->data['instance']['instances_id']);

finish(true, null, ['partner_code' => $code]);
