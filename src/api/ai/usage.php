<?php
/** KI-Nutzungsstatistiken abrufen */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_SETTINGS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

require_once __DIR__ . '/../../services/ClaudeService.php';

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$claude = new ClaudeService($DBLIB, $instanceId);

$year = (int)($_POST['year'] ?? date('Y'));
$month = (int)($_POST['month'] ?? date('n'));

$stats = $claude->getUsageStats($year, $month);
finish(true, null, $stats);
