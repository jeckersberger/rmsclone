<?php
/**
 * Erstellt einen oeffentlichen Freigabe-Link fuer ein Angebot.
 *
 * POST-Parameter:
 *   id            - Projekt-ID
 *   doc_id        - Document-Lifecycle-ID des Angebots
 *   s3files_id    - PDF-Datei-ID (optional)
 *   validity_days - Gueltigkeitsdauer in Tagen (default: 30)
 */
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW") || !isset($_POST['id'])) {
    finish(false, ["code" => null, "message" => "Keine Berechtigung."]);
}

$projectId = (int)$bCMS->sanitizeString($_POST['id']);
$docId = (int)$bCMS->sanitizeString($_POST['doc_id'] ?? 0);
$s3filesId = !empty($_POST['s3files_id']) ? (int)$_POST['s3files_id'] : null;
$validityDays = (int)($_POST['validity_days'] ?? 30);
$instanceId = (int)$AUTH->data['instance']['instances_id'];
$userId = (int)$AUTH->data['users_userid'];

// Projekt laden
$DBLIB->where('projects_id', $projectId);
$DBLIB->where('instances_id', $instanceId);
$DBLIB->join('clients', 'projects.clients_id=clients.clients_id', 'LEFT');
$project = $DBLIB->getOne('projects', ['projects.*', 'clients.clients_name', 'clients.clients_email']);

if (!$project) {
    finish(false, ["code" => null, "message" => "Projekt nicht gefunden."]);
}

// Dokumentnummer laden
$docNumber = '';
if ($docId > 0) {
    $DBLIB->where('id', $docId);
    $doc = $DBLIB->getOne('document_lifecycle');
    if ($doc) {
        $docNumber = $doc['doc_number'];
        if (!$s3filesId && $doc['s3files_id']) {
            $s3filesId = (int)$doc['s3files_id'];
        }
    }
}

// Sicheren Token generieren (URL-safe, 32 Zeichen)
$token = bin2hex(random_bytes(24));

$expiresAt = (new DateTime())->modify("+{$validityDays} days")->format('Y-m-d H:i:s');

$insertData = [
    'instances_id' => $instanceId,
    'projects_id' => $projectId,
    'document_lifecycle_id' => $docId ?: 0,
    's3files_id' => $s3filesId,
    'token' => $token,
    'client_name' => $project['clients_name'] ?? 'Kunde',
    'client_email' => $project['clients_email'] ?? null,
    'doc_number' => $docNumber,
    'status' => 'pending',
    'expires_at' => $expiresAt,
    'created_by' => $userId,
];

$id = $DBLIB->insert('quote_approval_tokens', $insertData);

if (!$id) {
    finish(false, ["code" => null, "message" => "Fehler beim Erstellen des Freigabelinks."]);
}

$approvalUrl = $CONFIG['ROOTURL'] . '/public/quote.php?token=' . $token;

// Optional: E-Mail an Kunden senden
if (!empty($_POST['send_email']) && !empty($project['clients_email'])) {
    require_once __DIR__ . '/../notifications/email/email.php';

    $instanceName = $AUTH->data['instance']['instances_name'] ?? $CONFIG['PROJECT_NAME'];
    $emailHtml = "<p>Sehr geehrte/r {$project['clients_name']},</p>"
        . "<p>wir haben ein Angebot <strong>{$docNumber}</strong> fuer Sie erstellt.</p>"
        . "<p>Sie koennen das Angebot unter folgendem Link einsehen und direkt annehmen oder ablehnen:</p>"
        . "<p><a href=\"{$approvalUrl}\" style=\"display:inline-block;padding:12px 30px;background:#27ae60;color:white;text-decoration:none;border-radius:4px;font-size:16px;\">Angebot ansehen</a></p>"
        . "<p>Der Link ist gueltig bis zum " . date('d.m.Y', strtotime($expiresAt)) . ".</p>"
        . "<p>Mit freundlichen Gruessen<br>{$instanceName}</p>";

    @sendEmail(
        ['userData' => ['users_email' => $project['clients_email'], 'users_name1' => $project['clients_name'], 'users_name2' => '']],
        $instanceId,
        "Angebot {$docNumber} - {$instanceName}",
        $emailHtml
    );
}

finish(true, null, [
    'id' => $id,
    'token' => $token,
    'url' => $approvalUrl,
    'expires_at' => $expiresAt,
]);
