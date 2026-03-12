<?php
/**
 * Rechnung/Angebot per E-Mail an Kunden versenden
 *
 * Nutzt InvoiceMailService fuer den Versand und protokolliert
 * den Vorgang in document_email_log. Aktualisiert den Status
 * in document_lifecycle auf 'sent' wenn der Versand erfolgreich war.
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("EMAIL_OUTBOX:SEND") && !$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$projectId = (int)($_POST['project_id'] ?? 0);
$s3fileId = (int)($_POST['s3file_id'] ?? 0);
$docNumber = trim($_POST['doc_number'] ?? '');
$docType = trim($_POST['doc_type'] ?? 'invoice');
$email = trim($_POST['email'] ?? '');

if ($projectId <= 0 || $s3fileId <= 0) finish(false, ["code" => "INVALID"]);

require_once __DIR__ . '/../../services/InvoiceEmailService.php';
require_once __DIR__ . '/../../services/DocumentLifecycleService.php';

$svc = new InvoiceMailService($DBLIB);

if (!empty($email)) {
    // Manuell angegebene E-Mail
    $name = trim($_POST['recipient_name'] ?? 'Kunde');
    $result = $svc->sendDocument($instanceId, $projectId, $s3fileId, $docNumber, $docType, $email, $name, $AUTH->data['users_userid']);
} else {
    // Automatisch: E-Mail aus Kundendaten
    $result = $svc->autoSendIfEmailAvailable($instanceId, $projectId, $s3fileId, $docNumber, $docType, $AUTH->data['users_userid']);
}

// Zusaetzlich in document_email_log protokollieren
if ($docNumber) {
    $DBLIB->where('instances_id', $instanceId);
    $DBLIB->where('doc_number', $docNumber);
    $doc = $DBLIB->getOne('document_lifecycle');
    if ($doc) {
        $recipientEmail = $email ?: ($result['email'] ?? '');
        $DBLIB->insert('document_email_log', [
            'instances_id' => $instanceId,
            'document_lifecycle_id' => (int)$doc['id'],
            'recipient_email' => $recipientEmail,
            'subject' => "{$docType} {$docNumber}",
            'sent_at' => date('Y-m-d H:i:s'),
            'status' => ($result['success'] ?? false) ? 'sent' : 'failed',
            'error_message' => ($result['success'] ?? false) ? null : ($result['message'] ?? null),
        ]);

        // Status auf 'sent' setzen falls noch draft (wird auch von InvoiceMailService gemacht,
        // aber hier nochmal als Sicherheit)
        if (($result['success'] ?? false) && $doc['status'] === 'draft') {
            $lifecycle = new DocumentLifecycleService($DBLIB);
            $lifecycle->changeStatus(
                (int)$doc['id'],
                'sent',
                $AUTH->data['users_userid'],
                'Per E-Mail versendet an ' . $recipientEmail,
                $instanceId
            );
        }
    }
}

if ($result['success']) {
    finish(true, null, $result);
} else {
    finish(false, ["message" => $result['message']]);
}
