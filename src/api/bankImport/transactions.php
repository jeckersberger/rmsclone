<?php
/**
 * Transaktionen einer Import-Session abrufen.
 * GET: session_id (required)
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:CREATE")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$sessionId = (int)($_GET['session_id'] ?? 0);

if ($sessionId <= 0) finish(false, ["message" => "session_id erforderlich."]);

// Session validieren
$DBLIB->where('id', $sessionId);
$DBLIB->where('instances_id', $instanceId);
if (!$DBLIB->getOne('bank_import_sessions')) {
    finish(false, ["message" => "Import-Session nicht gefunden."]);
}

require_once __DIR__ . '/../../services/BankImportService.php';
$service = new BankImportService($DBLIB);

$transactions = $service->getSessionTransactions($instanceId, $sessionId);
$stats = $service->getSessionStats($instanceId, $sessionId);

finish(true, null, [
    "transactions" => $transactions,
    "stats"        => $stats,
]);
