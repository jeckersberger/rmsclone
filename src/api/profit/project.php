<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/ProfitCalculationService.php';

if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_FINANCE:VIEW")) finish(false, ["message" => "Permission denied"]);

$projectId = intval($_POST['project_id'] ?? 0);
if (!$projectId) finish(false, ["message" => "project_id required"]);

$service = new ProfitCalculationService($DBLIB);
$result = $service->calculateProjectProfit($projectId);

finish(true, null, $result);
