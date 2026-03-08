<?php
/**
 * Zahlungen fuer eine Rechnung auflisten
 *
 * POST: document_exports_id
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("DOCUMENTS:VIEW") && !$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$documentId = (int)($_POST['document_exports_id'] ?? 0);
if ($documentId <= 0) {
    finish(false, ["code" => null, "message" => "document_exports_id erforderlich."]);
}

$svc = new PaymentTrackingService($DBLIB);

$payments  = $svc->getPayments($documentId);
$remaining = $svc->getRemainingAmount($documentId);

// Zahlungsmethoden-Labels (Deutsch)
$methodLabels = [
    'bank_transfer' => 'Ueberweisung',
    'cash'          => 'Barzahlung',
    'sepa'          => 'SEPA-Lastschrift',
    'paypal'        => 'PayPal',
    'other'         => 'Sonstige',
];

foreach ($payments as &$p) {
    $p['payment_method_label'] = $methodLabels[$p['payment_method']] ?? $p['payment_method'];
}
unset($p);

finish(true, null, [
    "payments"  => $payments,
    "remaining" => $remaining,
]);
