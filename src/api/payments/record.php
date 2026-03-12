<?php
/**
 * Zahlung erfassen
 *
 * POST: document_exports_id, amount, payment_date, payment_method, reference, notes
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("DOCUMENTS:EDIT") && !$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:CREATE")) finish(false, ["code" => "PERMISSIONS"]);

$documentId  = (int)($_POST['document_exports_id'] ?? 0);
$amount      = (float)str_replace(',', '.', $_POST['amount'] ?? '0');
$paymentDate = trim($_POST['payment_date'] ?? date('Y-m-d'));
$method      = trim($_POST['payment_method'] ?? 'bank_transfer');
$reference   = trim($_POST['reference'] ?? '');
$notes       = trim($_POST['notes'] ?? '');

if ($documentId <= 0 || $amount <= 0) {
    finish(false, ["code" => null, "message" => "Ungueltige Parameter (document_exports_id und amount erforderlich)."]);
}

// Datumsformat pruefen
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)) {
    finish(false, ["code" => null, "message" => "Ungueltiges Datumsformat (YYYY-MM-DD erwartet)."]);
}

$svc = new PaymentTrackingService($DBLIB);

try {
    $result = $svc->recordPayment($documentId, $amount, $paymentDate, $method, $reference, $notes);
    finish(true, null, $result);
} catch (\RuntimeException $e) {
    finish(false, ["code" => null, "message" => $e->getMessage()]);
}
