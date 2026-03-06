<?php
/**
 * Feature 3: E-Mail-Entwuerfe automatisch generieren
 *
 * Erstellt professionelle E-Mail-Texte fuer Angebote, Rechnungen, Mahnungen etc.
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("AI:VIEW") && !$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

require_once __DIR__ . '/../../services/ClaudeService.php';
require_once __DIR__ . '/../../services/BusinessRepo.php';

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$claude = new ClaudeService($DBLIB, $instanceId);
if (!$claude->isFeatureEnabled('email_draft')) {
    finish(false, ["code" => "DISABLED", "message" => "E-Mail-Entwuerfe sind deaktiviert."]);
}

$emailType = trim($_POST['type'] ?? ''); // quote, invoice, reminder, confirmation, custom
$projectId = (int)($_POST['project_id'] ?? 0);
$clientName = trim($_POST['client_name'] ?? '');
$docNumber = trim($_POST['doc_number'] ?? '');
$amount = trim($_POST['amount'] ?? '');
$customNote = trim($_POST['custom_note'] ?? '');

$business = BusinessRepo::getSettings($DBLIB, $instanceId);
$companyName = $business['instances_name'] ?? 'Unser Unternehmen';

$typeLabels = [
    'quote'        => 'Angebot',
    'invoice'      => 'Rechnung',
    'reminder'     => 'Zahlungserinnerung',
    'dunning'      => 'Mahnung',
    'confirmation' => 'Auftragsbestaetigung',
    'thank_you'    => 'Danke fuer die Zahlung',
    'custom'       => 'Individuelle E-Mail',
];

$systemPrompt = <<<PROMPT
Du bist ein professioneller Geschaeftskorrespondenz-Assistent fuer die Firma "{$companyName}".
Schreibe eine hoefliche, professionelle E-Mail auf Deutsch.
Verwende "Sie" als Anrede. Halte den Text kurz und praegnant.
Gib die Antwort als JSON zurueck:
{
  "subject": "Betreff-Zeile",
  "body": "E-Mail-Text mit Zeilenumbruechen als \\n"
}
Antworte NUR mit JSON.
PROMPT;

$context = "E-Mail-Typ: " . ($typeLabels[$emailType] ?? $emailType) . "\n";
if ($clientName) $context .= "Kundenname: {$clientName}\n";
if ($docNumber) $context .= "Dokumentnummer: {$docNumber}\n";
if ($amount) $context .= "Betrag: {$amount} EUR\n";
if ($customNote) $context .= "Zusaetzliche Hinweise: {$customNote}\n";

$response = $claude->ask('email_draft', $systemPrompt, $context);
$text = ClaudeService::extractText($response);

$draft = json_decode($text, true);
if (!$draft && preg_match('/\{[\s\S]*\}/', $text, $m)) {
    $draft = json_decode($m[0], true);
}

finish(true, null, [
    'subject' => $draft['subject'] ?? '',
    'body' => $draft['body'] ?? $text,
    'type' => $emailType,
]);
