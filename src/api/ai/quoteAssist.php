<?php
/**
 * Feature 4: Angebots-Assistent
 *
 * Nutzer beschreibt Bedarf in natuerlicher Sprache, z.B.
 * "3-Tage-Dreh, Kamera-Setup mit Licht und Ton"
 * → KI schlaegt passende Assets + Preise vor.
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

require_once __DIR__ . '/../../services/ClaudeService.php';

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$claude = new ClaudeService($DBLIB, $instanceId);
if (!$claude->isFeatureEnabled('quote_assist')) {
    finish(false, ["code" => "DISABLED", "message" => "Angebots-Assistent ist deaktiviert."]);
}

$request = trim($_POST['request'] ?? '');
if (!$request) finish(false, ["code" => "EMPTY", "message" => "Bitte Anforderung beschreiben."]);

// Get available asset types with prices
$DBLIB->where('instances_id', $instanceId);
$DBLIB->orderBy('assetTypes_name', 'ASC');
$assetTypes = $DBLIB->get('assetTypes', null, [
    'assetTypes_id', 'assetTypes_name', 'assetTypes_dayRate', 'assetTypes_weekRate',
    'assetTypes_value', 'assetCategories_id'
]) ?: [];

// Get categories
$DBLIB->where('instances_id', $instanceId);
$categories = $DBLIB->get('assetCategories', null, ['assetCategories_id', 'assetCategories_name']) ?: [];
$catMap = [];
foreach ($categories as $c) $catMap[$c['assetCategories_id']] = $c['assetCategories_name'];

$assetList = "";
foreach ($assetTypes as $at) {
    $cat = $catMap[$at['assetCategories_id'] ?? 0] ?? 'Sonstige';
    $assetList .= "- {$at['assetTypes_name']} (Kategorie: {$cat}, Tagespreis: {$at['assetTypes_dayRate']} EUR, ID: {$at['assetTypes_id']})\n";
}

$systemPrompt = <<<PROMPT
Du bist ein Angebots-Assistent fuer einen Verleih von Film-/Veranstaltungstechnik.
Der Nutzer beschreibt seinen Bedarf. Schlage passende Equipment-Zusammenstellungen vor.

Verfuegbares Equipment:
{$assetList}

Gib die Antwort als JSON zurueck:
{
  "suggestion_text": "Kurze Beschreibung des Vorschlags auf Deutsch",
  "items": [
    {"asset_type_id": 123, "name": "Assetname", "qty": 1, "days": 3, "day_rate": 50.00, "total": 150.00}
  ],
  "total_net": 0.00,
  "notes": "Optionale Hinweise oder Alternativen"
}
Antworte NUR mit JSON. Waehle nur Assets aus der obigen Liste.
PROMPT;

$response = $claude->ask('quote_assist', $systemPrompt, "Kundenanfrage: {$request}");
$text = ClaudeService::extractText($response);

$suggestion = json_decode($text, true);
if (!$suggestion && preg_match('/\{[\s\S]*\}/', $text, $m)) {
    $suggestion = json_decode($m[0], true);
}

finish(true, null, $suggestion ?: ['suggestion_text' => $text, 'items' => [], 'total_net' => 0]);
