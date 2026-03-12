<?php
/**
 * Smarte Asset-Zuteilung: Schlaegt passende Assets fuer ein Projekt vor
 * basierend auf Anforderungen, Verfuegbarkeit und historischen Daten.
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("ASSETS:VIEW") && !$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

require_once __DIR__ . '/../../services/ClaudeService.php';

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$claude = new ClaudeService($DBLIB, $instanceId);
if (!$claude->isFeatureEnabled('asset_allocation')) {
    finish(false, ["code" => "DISABLED", "message" => "Asset-Zuteilung ist deaktiviert."]);
}

$projectId = (int)($_POST['project_id'] ?? 0);
$requirements = trim($_POST['requirements'] ?? '');
if (!$projectId) finish(false, ["code" => "INVALID", "message" => "Projekt-ID fehlt."]);

// Load project info
$DBLIB->where('projects_id', $projectId);
$DBLIB->where('instances_id', $instanceId);
$DBLIB->join('clients', 'projects.clients_id=clients.clients_id', 'LEFT');
$project = $DBLIB->getOne('projects', ['projects.*', 'clients.clients_name']);
if (!$project) finish(false, ["code" => "NOT_FOUND"]);

// Get already assigned assets
$DBLIB->where('projects_id', $projectId);
$DBLIB->where('assetsAssignments_deleted', 0);
$DBLIB->join('assets', 'assetsAssignments.assets_id=assets.assets_id', 'LEFT');
$DBLIB->join('assetTypes', 'assets.assetTypes_id=assetTypes.assetTypes_id', 'LEFT');
$assigned = $DBLIB->get('assetsAssignments', null, ['assetTypes.assetTypes_name', 'assets.assets_tag']) ?: [];

// Get available asset types with counts
$sql = "SELECT at.assetTypes_id, at.assetTypes_name, at.assetTypes_dayRate, at.assetTypes_weekRate,
               ac.assetCategories_name, COUNT(a.assets_id) as total_count,
               SUM(CASE WHEN a.assets_endDate IS NULL AND a.assets_deleted = 0 THEN 1 ELSE 0 END) as available_count
        FROM assetTypes at
        LEFT JOIN assets a ON at.assetTypes_id = a.assetTypes_id
        LEFT JOIN assetCategories ac ON at.assetCategories_id = ac.assetCategories_id
        WHERE at.instances_id = ?
        GROUP BY at.assetTypes_id
        ORDER BY ac.assetCategories_name, at.assetTypes_name";
$types = $DBLIB->rawQuery($sql, [$instanceId]) ?: [];

$context = "Projekt: {$project['projects_name']}\n";
$context .= "Kunde: {$project['clients_name']}\n";
$context .= "Zeitraum: {$project['projects_dateStart']} bis {$project['projects_dateEnd']}\n";
if ($requirements) $context .= "Anforderungen: {$requirements}\n";

$context .= "\nBereits zugewiesen:\n";
foreach ($assigned as $a) {
    $context .= "- {$a['assetTypes_name']} ({$a['assets_tag']})\n";
}

$context .= "\nVerfuegbare Equipment-Typen:\n";
foreach ($types as $t) {
    $context .= "- {$t['assetTypes_name']} (Kategorie: {$t['assetCategories_name']}, verfuegbar: {$t['available_count']}/{$t['total_count']}, Tagespreis: {$t['assetTypes_dayRate']} EUR)\n";
}

$systemPrompt = <<<'PROMPT'
Du bist ein Equipment-Disponent fuer ein Verleihunternehmen.
Analysiere das Projekt und schlage passende Assets vor, die noch nicht zugewiesen sind.
Beruecksichtige: Projektzeitraum, Art der Veranstaltung, bereits zugewiesenes Equipment, Verfuegbarkeit.

Gib die Antwort als JSON zurueck:
{
  "suggestions": [
    {
      "asset_type": "Name des Asset-Typs",
      "quantity": 1,
      "reason": "Kurze Begruendung",
      "priority": "hoch|mittel|niedrig"
    }
  ],
  "notes": "Allgemeine Hinweise zum Setup",
  "estimated_total_day": 0.00
}
Antworte NUR mit JSON.
PROMPT;

$response = $claude->ask('asset_allocation', $systemPrompt, $context);
$text = ClaudeService::extractText($response);

$result = json_decode($text, true);
if (!$result && preg_match('/\{[\s\S]*\}/', $text, $m)) {
    $result = json_decode($m[0], true);
}

finish(true, null, [
    'project' => $project['projects_name'],
    'allocation' => $result ?: ['suggestions' => [], 'notes' => $text],
]);
