<?php
/**
 * Einzelne eingehende E-Mail anzeigen (inkl. Body und Anhaenge)
 *
 * GET-Parameter:
 *   id - emailReceived_id
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("EMAIL_INBOX:VIEW") && !$AUTH->instancePermissionCheck("USERS:VIEW:MAILINGS")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$emailId = (int)($_GET['id'] ?? 0);

if ($emailId <= 0) finish(false, ["code" => "INVALID"]);

$DBLIB->where('emailReceived_id', $emailId);
$DBLIB->where('instances_id', $instanceId);
$email = $DBLIB->getOne('emailReceived');

if (!$email) finish(false, ["code" => "NOT_FOUND"]);

// Als gelesen markieren
if (!(int)$email['emailReceived_isRead']) {
    $DBLIB->where('emailReceived_id', $emailId);
    $DBLIB->update('emailReceived', ['emailReceived_isRead' => 1]);
    $email['emailReceived_isRead'] = 1;
}

// Anhaenge laden
$DBLIB->where('emailReceived_id', $emailId);
$DBLIB->where('instances_id', $instanceId);
$attachments = $DBLIB->get('emailAttachments', null, [
    'emailAttachment_id',
    'emailAttachment_filename',
    'emailAttachment_mimeType',
    'emailAttachment_size',
    'emailAttachment_savedAt',
    's3files_id',
]) ?: [];

finish(true, null, [
    'email' => $email,
    'attachments' => $attachments,
]);
