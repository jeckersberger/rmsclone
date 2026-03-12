<?php
/**
 * Transaktion ignorieren (Gebuehren, irrelevante Buchungen etc.)
 *
 * POST: transaction_id, note (optional)
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:CREATE")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$userId = (int)$AUTH->data['users_userid'];
$transactionId = (int)($_POST['transaction_id'] ?? 0);
$note = trim($_POST['note'] ?? '');

if ($transactionId <= 0) {
    finish(false, ["message" => "transaction_id erforderlich."]);
}

require_once __DIR__ . '/../../services/BankImportService.php';
$service = new BankImportService($DBLIB);

if ($service->ignoreTransaction($instanceId, $transactionId, $userId, $note ?: null)) {
    finish(true, null, ["message" => "Transaktion ignoriert."]);
} else {
    finish(false, ["message" => "Transaktion nicht gefunden."]);
}
