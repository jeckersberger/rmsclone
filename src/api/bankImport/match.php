<?php
/**
 * Transaktion manuell einer Rechnung zuordnen.
 *
 * POST: transaction_id, document_lifecycle_id
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:CREATE")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$userId = (int)$AUTH->data['users_userid'];
$transactionId = (int)($_POST['transaction_id'] ?? 0);
$docId = (int)($_POST['document_lifecycle_id'] ?? 0);

if ($transactionId <= 0 || $docId <= 0) {
    finish(false, ["message" => "transaction_id und document_lifecycle_id erforderlich."]);
}

require_once __DIR__ . '/../../services/BankImportService.php';
$service = new BankImportService($DBLIB);

if ($service->manualMatch($instanceId, $transactionId, $docId, $userId)) {
    finish(true, null, ["message" => "Zuordnung gespeichert."]);
} else {
    finish(false, ["message" => "Zuordnung fehlgeschlagen."]);
}
