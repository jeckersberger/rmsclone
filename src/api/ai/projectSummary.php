<?php
/**
 * Feature 5: Projekt-Zusammenfassung per KI
 *
 * Erstellt eine uebersichtliche Zusammenfassung eines Projekts:
 * Status, Risiken, offene Posten, naechste Schritte.
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

require_once __DIR__ . '/../../services/ClaudeService.php';

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$projectId = (int)($_POST['project_id'] ?? 0);
if (!$projectId) finish(false, ["code" => "INVALID"]);

$claude = new ClaudeService($DBLIB, $instanceId);
if (!$claude->isFeatureEnabled('project_summary')) {
    finish(false, ["code" => "DISABLED", "message" => "Projekt-Zusammenfassungen sind deaktiviert."]);
}

// Gather project data
$DBLIB->where('projects_id', $projectId);
$DBLIB->where('instances_id', $instanceId);
$DBLIB->join('clients', 'projects.clients_id=clients.clients_id', 'LEFT');
$project = $DBLIB->getOne('projects', ['projects.*', 'clients.clients_name']);
if (!$project) finish(false, ["code" => "NOT_FOUND"]);

// Get documents
$DBLIB->where('instances_id', $instanceId);
$DBLIB->where('projects_id', $projectId);
$DBLIB->orderBy('created_at', 'ASC');
$docs = $DBLIB->get('document_lifecycle') ?: [];

// Get asset assignments count
$DBLIB->where('projects_id', $projectId);
$DBLIB->where('assetsAssignments_deleted', 0);
$assetCount = $DBLIB->getValue('assetsAssignments', 'COUNT(*)');

$context = "Projekt: {$project['projects_name']}\n";
$context .= "Kunde: {$project['clients_name']}\n";
$context .= "Zeitraum: {$project['projects_dateStart']} bis {$project['projects_dateEnd']}\n";
$context .= "Assets zugewiesen: {$assetCount}\n";
$context .= "Dokumente:\n";
foreach ($docs as $d) {
    $context .= "  - {$d['doc_type']} #{$d['doc_number']}: Status={$d['status']}, Brutto={$d['gross_amount']} EUR\n";
}

$systemPrompt = <<<'PROMPT'
Du bist ein Projekt-Assistent. Erstelle eine kurze, uebersichtliche Zusammenfassung des Projekts auf Deutsch.
Gib die Antwort als JSON zurueck:
{
  "summary": "2-3 Saetze Zusammenfassung",
  "status_icon": "success|warning|danger",
  "risks": ["Risiko 1", "Risiko 2"],
  "open_items": ["Offener Punkt 1"],
  "next_steps": ["Naechster Schritt 1"],
  "financial_status": "Kurze Finanz-Zusammenfassung"
}
Antworte NUR mit JSON.
PROMPT;

$response = $claude->ask('project_summary', $systemPrompt, $context);
$text = ClaudeService::extractText($response);

$summary = json_decode($text, true);
if (!$summary && preg_match('/\{[\s\S]*\}/', $text, $m)) {
    $summary = json_decode($m[0], true);
}

finish(true, null, $summary ?: ['summary' => $text]);
