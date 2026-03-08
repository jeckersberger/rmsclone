<?php
/**
 * Transaktion manuell einer Rechnung zuordnen.
 *
 * POST:
 *   transaction_id - bank_transactions.id
 *   document_id    - document_exports.document_exports_id
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:CREATE")) finish(false, ["code" => "PERMISSIONS"]);

$transactionId = (int)($_POST['transaction_id'] ?? 0);
$documentId = (int)($_POST['document_id'] ?? 0);

if ($transactionId <= 0 || $documentId <= 0) {
    finish(false, ["message" => "transaction_id und document_id erforderlich."]);
}

// Sicherstellen, dass die Transaktion zur aktuellen Instanz gehoert
$instanceId = (int)$AUTH->data['instance']['instances_id'];
$DBLIB->where('id', $transactionId);
$DBLIB->where('instances_id', $instanceId);
if (!$DBLIB->getOne('bank_transactions')) {
    finish(false, ["message" => "Transaktion nicht gefunden."]);
}

require_once __DIR__ . '/../../services/BankImportService.php';
$service = new BankImportService($DBLIB);

if ($service->manualMatch($transactionId, $documentId)) {
    finish(true, null, ["message" => "Zuordnung gespeichert."]);
} else {
    finish(false, ["message" => "Zuordnung fehlgeschlagen."]);
}
