<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:CREATE")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$mode = $_POST['mode'] ?? 'batch';

$svc = new SepaService($DBLIB);

if ($mode === 'single') {
    // Einzeleinzug
    $mandateId = (int)($_POST['mandate_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $purpose = trim($_POST['purpose'] ?? '');

    if ($mandateId <= 0 || $amount <= 0 || empty($purpose)) {
        finish(false, ["code" => "INVALID", "message" => "Mandat-ID, Betrag und Verwendungszweck sind Pflichtfelder"]);
    }

    $xml = $svc->exportSepaXml($mandateId, $amount, $purpose, $instanceId);
} else {
    // Sammeleinzug
    $invoiceIds = $_POST['invoice_ids'] ?? [];
    if (is_string($invoiceIds)) $invoiceIds = json_decode($invoiceIds, true) ?: [];
    $invoiceIds = array_map('intval', $invoiceIds);

    if (empty($invoiceIds)) {
        finish(false, ["code" => "INVALID", "message" => "Keine Rechnungen ausgewaehlt"]);
    }

    $xml = $svc->generateSepaXml($invoiceIds, $instanceId);
}

if (!$xml) finish(false, ["code" => "ERROR", "message" => "SEPA-XML konnte nicht erzeugt werden. Pruefen Sie ob aktive Mandate vorhanden sind."]);

$filename = 'SEPA-Lastschrift-' . date('Y-m-d_His') . '.xml';

finish(true, null, [
    "xml"      => base64_encode($xml),
    "filename" => $filename,
]);
