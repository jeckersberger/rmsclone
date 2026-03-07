<?php
/**
 * Import-Sessions auflisten.
 * GET: limit (optional, default 50)
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:CREATE")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));

require_once __DIR__ . '/../../services/BankImportService.php';
$service = new BankImportService($DBLIB);

$sessions = $service->getSessions($instanceId, $limit);
finish(true, null, ["sessions" => $sessions]);
