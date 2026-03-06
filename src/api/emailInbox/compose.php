<?php
/**
 * E-Mail schreiben und senden
 *
 * POST-Parameter:
 *   to       - Empfaenger E-Mail
 *   to_name  - Empfaenger Name (optional)
 *   subject  - Betreff
 *   body     - HTML-Body
 *   reply_to - emailReceived_id (optional, bei Antwort)
 */
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->instancePermissionCheck("EMAIL_INBOX:EDIT")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$userId = $AUTH->data['users_userid'];

$to = trim($_POST['to'] ?? '');
$toName = trim($_POST['to_name'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$body = $_POST['body'] ?? '';
$replyToId = (int)($_POST['reply_to'] ?? 0);

if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    finish(false, ["code" => "INVALID_EMAIL", "message" => "Keine gueltige E-Mail-Adresse"]);
}
if (empty($subject)) {
    finish(false, ["code" => "MISSING_SUBJECT", "message" => "Betreff fehlt"]);
}

// Sende E-Mail
require_once __DIR__ . '/../notifications/email/email.php';

$sent = @sendEmail(
    ['userData' => [
        'users_email' => $to,
        'users_name1' => $toName,
        'users_name2' => '',
        'users_userid' => 0,
    ]],
    $instanceId,
    $subject,
    $body
);

if (!$sent) {
    finish(false, ["code" => "SEND_FAILED", "message" => "E-Mail konnte nicht gesendet werden"]);
}

// Absender-Daten aus Instanz
$fromEmail = $AUTH->data['instance']['instances_email'] ?? '';
$fromName = $AUTH->data['instance']['instances_name'] ?? '';

// In Sent-Tabelle speichern
$DBLIB->insert('emailSent', [
    'users_userid' => $userId,
    'emailSent_toEmail' => $to,
    'emailSent_toName' => $toName,
    'emailSent_subject' => $subject,
    'emailSent_html' => $body,
    'emailSent_fromEmail' => $fromEmail,
    'emailSent_fromName' => $fromName,
]);

// Falls Antwort: Original als beantwortet markieren
if ($replyToId > 0) {
    $DBLIB->where('emailReceived_id', $replyToId);
    $DBLIB->update('emailReceived', ['emailReceived_replied' => 1]);
}

finish(true, null, ['message' => 'E-Mail gesendet']);
