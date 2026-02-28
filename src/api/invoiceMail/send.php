<?php
/**
 * Rechnung/Angebot per E-Mail an Kunden versenden
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$projectId = (int)($_POST['project_id'] ?? 0);
$s3fileId = (int)($_POST['s3file_id'] ?? 0);
$docNumber = trim($_POST['doc_number'] ?? '');
$docType = trim($_POST['doc_type'] ?? 'invoice');
$email = trim($_POST['email'] ?? '');

if ($projectId <= 0 || $s3fileId <= 0) finish(false, ["code" => "INVALID"]);

$svc = new InvoiceMailService($DBLIB);

if (!empty($email)) {
    // Manuell angegebene E-Mail
    $name = trim($_POST['recipient_name'] ?? 'Kunde');
    $result = $svc->sendDocument($instanceId, $projectId, $s3fileId, $docNumber, $docType, $email, $name, $AUTH->data['users_userid']);
} else {
    // Automatisch: E-Mail aus Kundendaten
    $result = $svc->autoSendIfEmailAvailable($instanceId, $projectId, $s3fileId, $docNumber, $docType, $AUTH->data['users_userid']);
}

if ($result['success']) {
    finish(true, null, $result);
} else {
    finish(false, ["message" => $result['message']]);
}
