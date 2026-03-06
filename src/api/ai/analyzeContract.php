<?php
/**
 * Feature 7: Vertragsanalyse
 *
 * PDF-Vertrag hochladen, KI extrahiert Kernpunkte.
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

require_once __DIR__ . '/../../services/ClaudeService.php';
require_once __DIR__ . '/../../services/LocalFileStorage.php';

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$userId = (int)$AUTH->data['users_userid'];

$claude = new ClaudeService($DBLIB, $instanceId);
if (!$claude->isFeatureEnabled('contract_analysis')) {
    finish(false, ["code" => "DISABLED", "message" => "Vertragsanalyse ist deaktiviert."]);
}

if (empty($_FILES['file'])) finish(false, ["code" => "NO_FILE"]);

$file = $_FILES['file'];
$title = trim($_POST['title'] ?? $file['name']);
$projectId = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
$clientId = !empty($_POST['client_id']) ? (int)$_POST['client_id'] : null;

$stored = LocalFileStorage::store($instanceId, 'contracts', $file);
$base64 = LocalFileStorage::readBase64($stored['path']);

$systemPrompt = <<<'PROMPT'
Du bist ein Vertragsanalyse-Assistent. Analysiere den hochgeladenen Vertrag und extrahiere die wichtigsten Informationen.
Gib die Antwort als JSON zurueck:
{
  "summary": "2-3 Saetze Zusammenfassung des Vertrags",
  "parties": ["Partei 1", "Partei 2"],
  "contract_type": "Mietvertrag|Dienstleistungsvertrag|Kaufvertrag|Rahmenvertrag|Sonstiges",
  "start_date": "YYYY-MM-DD oder null",
  "end_date": "YYYY-MM-DD oder null",
  "value": "Vertragswert als Zahl oder null",
  "currency": "EUR",
  "key_points": [
    "Wichtiger Punkt 1",
    "Wichtiger Punkt 2"
  ],
  "obligations": [
    "Pflicht/Leistung 1",
    "Pflicht/Leistung 2"
  ],
  "risks": [
    "Risiko oder kritische Klausel 1"
  ],
  "termination": "Kuendigungsfrist/Regelung",
  "special_clauses": ["Besondere Klauseln"]
}
Antworte NUR mit JSON.
PROMPT;

$response = $claude->askWithPdf('contract_analysis', $systemPrompt, 'Bitte analysiere diesen Vertrag.', $base64, 4096);
$text = ClaudeService::extractText($response);

$analysis = json_decode($text, true);
if (!$analysis && preg_match('/\{[\s\S]*\}/', $text, $m)) {
    $analysis = json_decode($m[0], true);
}

// Save to contracts table
$contractId = $DBLIB->insert('contracts', [
    'instances_id' => $instanceId,
    'projects_id' => $projectId,
    'clients_id' => $clientId,
    'title' => $title,
    'file_path' => $stored['path'],
    'original_name' => $stored['original_name'],
    'ai_summary' => $analysis['summary'] ?? null,
    'ai_key_points_json' => json_encode($analysis),
    'uploaded_by' => $userId,
]);

finish(true, null, [
    'contract_id' => $contractId,
    'analysis' => $analysis,
    'file_path' => $stored['path'],
]);
