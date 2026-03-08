<?php
/**
 * Transaktion ignorieren (Gebuehren, irrelevante Buchungen etc.)
 *
 * POST:
 *   transaction_id - bank_transactions.id
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:CREATE")) finish(false, ["code" => "PERMISSIONS"]);

$transactionId = (int)($_POST['transaction_id'] ?? 0);

if ($transactionId <= 0) {
    finish(false, ["message" => "transaction_id erforderlich."]);
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

if ($service->ignoreTransaction($transactionId)) {
    finish(true, null, ["message" => "Transaktion ignoriert."]);
} else {
    finish(false, ["message" => "Transaktion nicht gefunden."]);
}
