<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/DepositService.php';

if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["message" => "Permission denied"]);

$projectId = intval($_POST['project_id'] ?? 0);
if (!$projectId) finish(false, ["message" => "project_id required"]);

$service = new DepositService($DBLIB);
$summary = $service->getProjectDepositSummary($projectId);

finish(true, null, $summary);
