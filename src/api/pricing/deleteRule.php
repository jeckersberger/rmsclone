<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/CustomerPricingService.php';

if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_FINANCE:EDIT")) finish(false, ["message" => "Permission denied"]);

$ruleId = intval($_POST['rule_id'] ?? 0);
if (!$ruleId) finish(false, ["message" => "rule_id required"]);

$service = new CustomerPricingService($DBLIB);
$result = $service->deleteRule($ruleId);

finish($result, $result ? null : ["message" => "Could not delete rule"]);
