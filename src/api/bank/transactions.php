<?php
/**
 * Transaktionen auflisten mit Filtern.
 *
 * GET-Parameter:
 *   status   - (optional) Filter: unmatched, auto_matched, manual_matched, ignored
 *   batch_id - (optional) Filter nach Import-Batch
 *   limit    - (optional) Max. Ergebnisse (Standard: 100)
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:CREATE")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$status = trim($_REQUEST['status'] ?? '') ?: null;
$batchId = trim($_REQUEST['batch_id'] ?? '') ?: null;
$limit = min(500, max(1, (int)($_REQUEST['limit'] ?? 100)));

// Status validieren
if ($status && !in_array($status, ['unmatched', 'auto_matched', 'manual_matched', 'ignored'])) {
    finish(false, ["message" => "Ungueltiger Status-Filter."]);
}

require_once __DIR__ . '/../../services/BankImportService.php';
$service = new BankImportService($DBLIB);

$transactions = $service->getTransactions($instanceId, $status, $batchId, $limit);
$importLog = $service->getImportLog($instanceId);

finish(true, null, [
    "transactions" => $transactions,
    "import_log"   => $importLog,
]);
