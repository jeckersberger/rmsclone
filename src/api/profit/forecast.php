<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/ProfitCalculationService.php';

if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_FINANCE:VIEW")) finish(false, ["message" => "Permission denied"]);

$months = intval($_POST['months'] ?? 3);

$service = new ProfitCalculationService($DBLIB);
$result = $service->getRevenueForecast($AUTH->data['instance']['instances_id'], $months);

finish(true, null, ['forecast' => $result]);
