<?php
/**
 * Zahlung loeschen
 *
 * POST: payment_id
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("DOCUMENTS:EDIT") && !$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:CREATE")) finish(false, ["code" => "PERMISSIONS"]);

$paymentId = (int)($_POST['payment_id'] ?? 0);
if ($paymentId <= 0) {
    finish(false, ["code" => null, "message" => "payment_id erforderlich."]);
}

$svc = new PaymentTrackingService($DBLIB);
$ok = $svc->deletePayment($paymentId);

if (!$ok) {
    finish(false, ["code" => null, "message" => "Zahlung nicht gefunden."]);
}

finish(true, null, ["deleted" => true]);
