<?php
/**
 * FinTS Sync-History fuer ein Bankkonto abrufen.
 *
 * GET:
 *   account_id  - bank_accounts.id
 *   limit       - (optional) Anzahl Eintraege (default 20)
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:CREATE")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = (int)$AUTH->data['instance']['instances_id'];

$accountId = (int)($_GET['account_id'] ?? 0);
$limit = min((int)($_GET['limit'] ?? 20), 100);

if ($accountId <= 0) finish(false, ["message" => "account_id erforderlich."]);

require_once __DIR__ . '/../../services/FinTSService.php';
$fints = new FinTSService($DBLIB);

$log = $fints->getSyncLog($instanceId, $accountId, $limit);
finish(true, null, ["log" => $log]);
