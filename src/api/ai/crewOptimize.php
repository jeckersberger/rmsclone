<?php
/**
 * Crew-Optimierung: Schlaegt optimale Teamzusammensetzung vor,
 * erkennt Konflikte und optimiert Skill-Matching.
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

require_once __DIR__ . '/../../services/ClaudeService.php';

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$claude = new ClaudeService($DBLIB, $instanceId);
if (!$claude->isFeatureEnabled('crew_optimize')) {
    finish(false, ["code" => "DISABLED", "message" => "Crew-Optimierung ist deaktiviert."]);
}

$projectId = (int)($_POST['project_id'] ?? 0);
if (!$projectId) finish(false, ["code" => "INVALID", "message" => "Projekt-ID fehlt."]);

// Load project
$DBLIB->where('projects_id', $projectId);
$DBLIB->where('instances_id', $instanceId);
$DBLIB->join('clients', 'projects.clients_id=clients.clients_id', 'LEFT');
$project = $DBLIB->getOne('projects', ['projects.*', 'clients.clients_name']);
if (!$project) finish(false, ["code" => "NOT_FOUND"]);

// Get current crew assignments for this project
$DBLIB->where('crewAssignments.projects_id', $projectId);
$DBLIB->where('crewAssignments.crewAssignments_deleted', 0);
$DBLIB->join('users', 'crewAssignments.users_userid=users.users_userid', 'LEFT');
$crew = $DBLIB->get('crewAssignments', null, [
    'crewAssignments.*', 'users.users_name1', 'users.users_name2'
]) ?: [];

// Get all available team members
$DBLIB->where('instancesUsers.instances_id', $instanceId);
$DBLIB->join('users', 'instancesUsers.users_userid=users.users_userid', 'INNER');
$allUsers = $DBLIB->get('instancesUsers', null, [
    'users.users_userid', 'users.users_name1', 'users.users_name2'
]) ?: [];

// Get overlapping projects (conflict detection)
$sql = "SELECT p.projects_id, p.projects_name, p.projects_dateStart, p.projects_dateEnd,
               GROUP_CONCAT(DISTINCT CONCAT(u.users_name1, ' ', u.users_name2) SEPARATOR ', ') as crew_names
        FROM projects p
        JOIN crewAssignments ca ON p.projects_id = ca.projects_id AND ca.crewAssignments_deleted = 0
        LEFT JOIN users u ON ca.users_userid = u.users_userid
        WHERE p.instances_id = ? AND p.projects_deleted = 0
        AND p.projects_id != ?
        AND p.projects_dateStart <= ? AND p.projects_dateEnd >= ?
        GROUP BY p.projects_id";
$overlapping = $DBLIB->rawQuery($sql, [
    $instanceId, $projectId,
    $project['projects_dateEnd'], $project['projects_dateStart']
]) ?: [];

// Get crew history (who worked on similar projects)
$sql = "SELECT u.users_userid, CONCAT(u.users_name1, ' ', u.users_name2) as name,
               COUNT(DISTINCT ca.projects_id) as project_count,
               GROUP_CONCAT(DISTINCT ca.crewAssignments_role SEPARATOR ', ') as roles
        FROM crewAssignments ca
        JOIN users u ON ca.users_userid = u.users_userid
        JOIN projects p ON ca.projects_id = p.projects_id
        WHERE p.instances_id = ? AND ca.crewAssignments_deleted = 0
        GROUP BY u.users_userid
        ORDER BY project_count DESC LIMIT 30";
$crewHistory = $DBLIB->rawQuery($sql, [$instanceId]) ?: [];

$context = "Projekt: {$project['projects_name']}\n";
$context .= "Kunde: {$project['clients_name']}\n";
$context .= "Zeitraum: {$project['projects_dateStart']} bis {$project['projects_dateEnd']}\n\n";

$context .= "Aktuelle Crew-Zuweisung:\n";
if (empty($crew)) {
    $context .= "- Noch niemand zugewiesen\n";
} else {
    foreach ($crew as $c) {
        $name = $c['users_name1'] ? "{$c['users_name1']} {$c['users_name2']}" : $c['crewAssignments_personName'];
        $context .= "- {$name}: {$c['crewAssignments_role']}\n";
    }
}

$context .= "\nVerfuegbare Teammitglieder:\n";
foreach ($allUsers as $u) {
    $context .= "- {$u['users_name1']} {$u['users_name2']}\n";
}

$context .= "\nTeam-Erfahrung (Projekthistorie):\n";
foreach ($crewHistory as $h) {
    $context .= "- {$h['name']}: {$h['project_count']} Projekte, Rollen: {$h['roles']}\n";
}

if (!empty($overlapping)) {
    $context .= "\nParallel laufende Projekte (Konfliktpotenzial):\n";
    foreach ($overlapping as $o) {
        $context .= "- {$o['projects_name']} ({$o['projects_dateStart']} - {$o['projects_dateEnd']}): Crew: {$o['crew_names']}\n";
    }
}

$systemPrompt = <<<'PROMPT'
Du bist ein Personalplaner fuer ein Equipment-Verleihunternehmen (Veranstaltungstechnik).
Analysiere das Projekt und optimiere die Teamzusammensetzung.

Gib die Antwort als JSON zurueck:
{
  "conflicts": [
    {"person": "Name", "conflict_project": "Projektname", "dates": "Zeitraum", "severity": "hoch|mittel"}
  ],
  "suggestions": [
    {"person": "Name", "role": "Vorgeschlagene Rolle", "reason": "Begruendung", "fit_score": 0.9}
  ],
  "missing_roles": ["Fehlende Rolle 1"],
  "team_size_recommendation": {
    "minimum": 1,
    "optimal": 2,
    "reason": "Begruendung"
  },
  "summary": "Zusammenfassung in 2-3 Saetzen"
}
Antworte NUR mit JSON.
PROMPT;

$response = $claude->ask('crew_optimize', $systemPrompt, $context);
$text = ClaudeService::extractText($response);

$result = json_decode($text, true);
if (!$result && preg_match('/\{[\s\S]*\}/', $text, $m)) {
    $result = json_decode($m[0], true);
}

finish(true, null, [
    'project' => $project['projects_name'],
    'optimization' => $result ?: ['summary' => $text],
]);
