<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/ProfitCalculationService.php';

if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_FINANCE:VIEW")) finish(false, ["message" => "Permission denied"]);

$dateFrom = $_POST['date_from'] ?? date('Y-01-01');
$dateTo = $_POST['date_to'] ?? date('Y-12-31');

$service = new ProfitCalculationService($DBLIB);
$result = $service->calculatePeriodProfit($AUTH->data['instance']['instances_id'], $dateFrom, $dateTo);

finish(true, null, $result);
