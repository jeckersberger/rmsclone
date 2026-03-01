<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/DepositService.php';

if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["message" => "Permission denied"]);

$projectId = intval($_POST['project_id'] ?? 0);
if (!$projectId) finish(false, ["message" => "project_id required"]);

$percent = floatval($_POST['percent'] ?? 20);

$service = new DepositService($DBLIB);
$result = $service->calculateDeposit($projectId, $percent);

finish(true, null, $result);
