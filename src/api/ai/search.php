<?php
/**
 * Feature 2: Intelligente Suche in natuerlicher Sprache
 *
 * Nutzer fragt z.B. "Zeig mir alle Projekte mit Kamera-Equipment im Januar"
 * Claude wandelt das in SQL-Filter um und gibt passende Ergebnisse zurueck.
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("AI:VIEW") && !$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

require_once __DIR__ . '/../../services/ClaudeService.php';

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$query = trim($_POST['query'] ?? '');
if (!$query) finish(false, ["code" => "EMPTY", "message" => "Bitte Suchanfrage eingeben."]);

$claude = new ClaudeService($DBLIB, $instanceId);
if (!$claude->isFeatureEnabled('search')) {
    finish(false, ["code" => "DISABLED", "message" => "Intelligente Suche ist deaktiviert."]);
}

// Get context: recent projects, asset types, clients for Claude
$DBLIB->where('instances_id', $instanceId);
$DBLIB->orderBy('projects_id', 'DESC');
$recentProjects = $DBLIB->get('projects', 20, ['projects_id', 'projects_name', 'projects_dateStart', 'projects_dateEnd', 'clients_id']) ?: [];

$DBLIB->where('instances_id', $instanceId);
$clients = $DBLIB->get('clients', null, ['clients_id', 'clients_name']) ?: [];

$DBLIB->where('instances_id', $instanceId);
$assetTypes = $DBLIB->get('assetTypes', null, ['assetTypes_id', 'assetTypes_name']) ?: [];

$context = "Verfuegbare Daten:\n";
$context .= "Kunden: " . implode(', ', array_map(fn($c) => $c['clients_name'] . " (ID:{$c['clients_id']})", $clients)) . "\n";
$context .= "Equipment-Typen: " . implode(', ', array_map(fn($a) => $a['assetTypes_name'], $assetTypes)) . "\n";
$context .= "Letzte Projekte: " . implode(', ', array_map(fn($p) => "{$p['projects_name']} ({$p['projects_dateStart']} bis {$p['projects_dateEnd']})", $recentProjects)) . "\n";

$systemPrompt = <<<'PROMPT'
Du bist ein Such-Assistent fuer ein Verleih-Management-System. Der Nutzer stellt Fragen in natuerlicher Sprache.
Analysiere die Frage und gib eine strukturierte Antwort als JSON zurueck:
{
  "type": "projects|assets|clients|invoices|mixed",
  "filters": {
    "date_from": "YYYY-MM-DD oder null",
    "date_to": "YYYY-MM-DD oder null",
    "client_ids": [1,2] oder null,
    "asset_type_keywords": ["Kamera", "Licht"] oder null,
    "status": "string oder null",
    "keyword": "Freitext-Suchwort oder null"
  },
  "summary": "Kurze deutsche Zusammenfassung was gesucht wird",
  "sql_hint": "Vorschlag fuer WHERE-Bedingungen (nur als Hilfe, nicht direkt ausfuehren)"
}
Antworte NUR mit JSON.
PROMPT;

$response = $claude->ask('search', $systemPrompt, "Kontext:\n{$context}\n\nSuchanfrage: {$query}");
$text = ClaudeService::extractText($response);

$parsed = json_decode($text, true);
if (!$parsed && preg_match('/\{[\s\S]*\}/', $text, $m)) {
    $parsed = json_decode($m[0], true);
}

// Execute search based on parsed filters
$results = [];
if ($parsed) {
    $filters = $parsed['filters'] ?? [];

    // Search projects
    $DBLIB->where('p.instances_id', $instanceId);
    if (!empty($filters['date_from'])) $DBLIB->where('p.projects_dateStart', $filters['date_from'], '>=');
    if (!empty($filters['date_to'])) $DBLIB->where('p.projects_dateEnd', $filters['date_to'], '<=');
    if (!empty($filters['client_ids'])) $DBLIB->where('p.clients_id', $filters['client_ids'], 'IN');
    if (!empty($filters['keyword'])) $DBLIB->where("(p.projects_name LIKE ? OR p.projects_description LIKE ?)", ['%'.$filters['keyword'].'%', '%'.$filters['keyword'].'%']);
    $DBLIB->join('clients c', 'p.clients_id=c.clients_id', 'LEFT');
    $DBLIB->orderBy('p.projects_dateStart', 'DESC');
    $results = $DBLIB->get('projects p', 50, ['p.projects_id', 'p.projects_name', 'p.projects_dateStart', 'p.projects_dateEnd', 'c.clients_name']) ?: [];
}

finish(true, null, [
    'query' => $query,
    'interpretation' => $parsed,
    'results' => $results,
    'count' => count($results),
]);
