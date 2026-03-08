<?php
/**
 * E-Mail Auto-Reply: Generiert Antwortvorschlaege fuer eingehende E-Mails
 * basierend auf Kontext, Kategorie und bisheriger Kommunikation.
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("EMAIL:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

require_once __DIR__ . '/../../services/ClaudeService.php';
require_once __DIR__ . '/../../services/BusinessRepo.php';

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$claude = new ClaudeService($DBLIB, $instanceId);
if (!$claude->isFeatureEnabled('email_reply')) {
    finish(false, ["code" => "DISABLED", "message" => "E-Mail Auto-Reply ist deaktiviert."]);
}

$emailId = (int)($_POST['email_id'] ?? 0);
$tone = trim($_POST['tone'] ?? 'freundlich'); // freundlich, formell, kurz
if (!$emailId) finish(false, ["code" => "INVALID", "message" => "E-Mail-ID fehlt."]);

// Load email
$DBLIB->where('emailReceived_id', $emailId);
$DBLIB->where('instances_id', $instanceId);
$email = $DBLIB->getOne('emailReceived');
if (!$email) finish(false, ["code" => "NOT_FOUND"]);

$business = BusinessRepo::getSettings($DBLIB, $instanceId);
$companyName = $business['instances_name'] ?? 'Unser Unternehmen';

// Get linked project/client info if available
$projectInfo = '';
if ($email['projects_id']) {
    $DBLIB->where('projects_id', $email['projects_id']);
    $proj = $DBLIB->getOne('projects', ['projects_name', 'projects_dateStart', 'projects_dateEnd']);
    if ($proj) $projectInfo = "Zugeordnetes Projekt: {$proj['projects_name']} ({$proj['projects_dateStart']} - {$proj['projects_dateEnd']})\n";
}

$clientInfo = '';
if ($email['clients_id']) {
    $DBLIB->where('clients_id', $email['clients_id']);
    $cl = $DBLIB->getOne('clients', ['clients_name']);
    if ($cl) $clientInfo = "Kunde: {$cl['clients_name']}\n";
}

$context = "Von: {$email['emailReceived_fromName']} <{$email['emailReceived_fromEmail']}>\n";
$context .= "Betreff: {$email['emailReceived_subject']}\n";
$context .= "Datum: {$email['emailReceived_date']}\n";
if ($email['ai_category']) $context .= "KI-Kategorie: {$email['ai_category']}\n";
if ($email['ai_priority']) $context .= "KI-Prioritaet: {$email['ai_priority']}\n";
$context .= $clientInfo . $projectInfo;
$context .= "\nE-Mail-Text:\n" . mb_substr($email['emailReceived_bodyText'] ?: strip_tags($email['emailReceived_bodyHtml'] ?: ''), 0, 3000);

$toneLabel = ['freundlich' => 'freundlich und hilfsbereit', 'formell' => 'formell und professionell', 'kurz' => 'kurz und praegnant'];

$systemPrompt = <<<PROMPT
Du bist der E-Mail-Assistent der Firma "{$companyName}".
Erstelle einen Antwortvorschlag auf die eingehende E-Mail.
Ton: {$toneLabel[$tone]}. Verwende "Sie" als Anrede.
Beruecksichtige den Kontext (Kategorie, Projekt, Kunde) falls vorhanden.

Gib die Antwort als JSON zurueck:
{
  "subject": "Re: Betreff",
  "body": "Antworttext mit Zeilenumbruechen als \\n",
  "quick_actions": ["Moegliche Folgeaktion 1", "Folgeaktion 2"]
}
Antworte NUR mit JSON.
PROMPT;

$response = $claude->ask('email_reply', $systemPrompt, $context);
$text = ClaudeService::extractText($response);

$draft = json_decode($text, true);
if (!$draft && preg_match('/\{[\s\S]*\}/', $text, $m)) {
    $draft = json_decode($m[0], true);
}

finish(true, null, [
    'original_subject' => $email['emailReceived_subject'],
    'from' => $email['emailReceived_fromEmail'],
    'reply' => $draft ?: ['body' => $text],
]);
