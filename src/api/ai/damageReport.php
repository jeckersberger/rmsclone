<?php
/**
 * Feature 9: Schadensbericht aus Foto
 *
 * Foto hochladen, KI erstellt automatisch Schadensbeschreibung und Schweregrad.
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

require_once __DIR__ . '/../../services/ClaudeService.php';
require_once __DIR__ . '/../../services/LocalFileStorage.php';

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$userId = (int)$AUTH->data['users_userid'];

$claude = new ClaudeService($DBLIB, $instanceId);
if (!$claude->isFeatureEnabled('damage_report')) {
    finish(false, ["code" => "DISABLED", "message" => "KI-Schadensbericht ist deaktiviert."]);
}

if (empty($_FILES['photo'])) finish(false, ["code" => "NO_FILE", "message" => "Kein Foto hochgeladen."]);

$file = $_FILES['photo'];
$assetId = (int)($_POST['asset_id'] ?? 0);
$assetName = trim($_POST['asset_name'] ?? 'Unbekanntes Equipment');

$stored = LocalFileStorage::store($instanceId, 'damage', $file);
$base64 = LocalFileStorage::readBase64($stored['path']);
$mediaType = $stored['mime'] ?: 'image/jpeg';

$systemPrompt = <<<PROMPT
Du bist ein Equipment-Schadensgutachter fuer Film-/Veranstaltungstechnik.
Analysiere das Foto und erstelle einen Schadensbericht.
Equipment: {$assetName}

Gib die Antwort als JSON zurueck:
{
  "description": "Detaillierte Schadensbeschreibung auf Deutsch",
  "severity": "minor|moderate|major|total_loss",
  "severity_label": "Geringfuegig|Mittel|Schwer|Totalschaden",
  "visible_damage": ["Kratzer am Gehaeuse", "Gebrochene Halterung"],
  "repair_possible": true,
  "repair_estimate_eur": 0,
  "repair_description": "Was muss repariert werden",
  "usage_impact": "Kann noch benutzt werden / Eingeschraenkt nutzbar / Nicht einsatzfaehig",
  "recommendation": "Empfehlung: Reparatur / Austausch / Weiter verwenden"
}
Antworte NUR mit JSON.
PROMPT;

$response = $claude->askWithImage('damage_report', $systemPrompt, 'Bitte analysiere den Schaden auf diesem Foto.', $base64, $mediaType);
$text = ClaudeService::extractText($response);

$report = json_decode($text, true);
if (!$report && preg_match('/\{[\s\S]*\}/', $text, $m)) {
    $report = json_decode($m[0], true);
}

finish(true, null, [
    'report' => $report,
    'photo_path' => $stored['path'],
    'asset_id' => $assetId,
    'asset_name' => $assetName,
]);
