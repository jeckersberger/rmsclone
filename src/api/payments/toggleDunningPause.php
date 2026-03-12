<?php
/**
 * Mahnsperre manuell setzen/aufheben
 *
 * POST: document_exports_id, paused (0|1), reason (optional)
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("DUNNING:CREATE") && !$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:CREATE")) finish(false, ["code" => "PERMISSIONS"]);

$documentId = (int)($_POST['document_exports_id'] ?? 0);
$paused     = (int)($_POST['paused'] ?? 0);
$reason     = trim($_POST['reason'] ?? '');

if ($documentId <= 0) {
    finish(false, ["code" => null, "message" => "document_exports_id erforderlich."]);
}

$svc = new PaymentTrackingService($DBLIB);
$ok = $svc->toggleDunningPause($documentId, (bool)$paused, $reason);

if (!$ok) {
    finish(false, ["code" => null, "message" => "Keine Mahnhistorie fuer dieses Dokument gefunden."]);
}

finish(true, null, [
    "paused" => (bool)$paused,
    "reason" => $reason,
]);
