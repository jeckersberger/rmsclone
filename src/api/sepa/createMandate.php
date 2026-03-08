<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:CREATE")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$clientId = (int)($_POST['clients_id'] ?? 0);

if ($clientId <= 0) finish(false, ["code" => "INVALID", "message" => "Keine Kunden-ID angegeben"]);

$data = [
    'iban'           => $_POST['iban'] ?? '',
    'bic'            => $_POST['bic'] ?? '',
    'account_holder' => $_POST['account_holder'] ?? '',
    'mandate_type'   => $_POST['mandate_type'] ?? 'CORE',
    'signed_at'      => $_POST['signed_at'] ?? null,
];

if (empty($data['iban']) || empty($data['account_holder'])) {
    finish(false, ["code" => "INVALID", "message" => "IBAN und Kontoinhaber sind Pflichtfelder"]);
}

$svc = new SepaService($DBLIB);
$result = $svc->createMandate($clientId, $instanceId, $data);

if (!$result) finish(false, ["code" => "ERROR", "message" => "Mandat konnte nicht angelegt werden. Bitte IBAN pruefen."]);

finish(true, null, $result);
