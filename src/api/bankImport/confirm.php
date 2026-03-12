<?php
/**
 * Zuordnung(en) bestaetigen und Zahlung(en) verbuchen.
 *
 * POST:
 *   transaction_id - Einzelne Transaktion bestaetigen
 *   ODER
 *   session_id     - Alle vorgeschlagenen Matches einer Session bestaetigen
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:CREATE")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$userId = (int)$AUTH->data['users_userid'];
$transactionId = (int)($_POST['transaction_id'] ?? 0);
$sessionId = (int)($_POST['session_id'] ?? 0);

require_once __DIR__ . '/../../services/DocumentLifecycleService.php';
require_once __DIR__ . '/../../services/BankImportService.php';
$service = new BankImportService($DBLIB);

if ($transactionId > 0) {
    // Einzelne Transaktion bestaetigen
    if ($service->confirmMatch($instanceId, $transactionId, $userId)) {
        finish(true, null, ["message" => "Zahlung verbucht."]);
    } else {
        finish(false, ["message" => "Bestaaetigung fehlgeschlagen. Transaktion nicht gefunden oder keine Zuordnung."]);
    }
} elseif ($sessionId > 0) {
    // Alle Suggested einer Session bestaetigen
    $DBLIB->where('id', $sessionId);
    $DBLIB->where('instances_id', $instanceId);
    if (!$DBLIB->getOne('bank_import_sessions')) {
        finish(false, ["message" => "Session nicht gefunden."]);
    }

    $result = $service->confirmAllSuggested($instanceId, $sessionId, $userId);
    finish(true, null, $result);
} else {
    finish(false, ["message" => "transaction_id oder session_id erforderlich."]);
}
