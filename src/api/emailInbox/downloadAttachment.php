<?php
/**
 * E-Mail-Anhang herunterladen
 *
 * GET-Parameter:
 *   id - emailAttachment_id
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("EMAIL_INBOX:VIEW:ATTACHMENTS") && !$AUTH->instancePermissionCheck("USERS:VIEW:MAILINGS")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$attachmentId = (int)($_GET['id'] ?? 0);

if ($attachmentId <= 0) finish(false, ["code" => "INVALID"]);

$DBLIB->where('emailAttachment_id', $attachmentId);
$DBLIB->where('instances_id', $instanceId);
$attachment = $DBLIB->getOne('emailAttachments');

if (!$attachment) finish(false, ["code" => "NOT_FOUND"]);

$filePath = $attachment['emailAttachment_storagePath'];
if (!file_exists($filePath)) {
    finish(false, ["message" => "Datei nicht gefunden."]);
}

// Direkter Download
header('Content-Type: ' . $attachment['emailAttachment_mimeType']);
header('Content-Disposition: attachment; filename="' . basename($attachment['emailAttachment_filename']) . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
