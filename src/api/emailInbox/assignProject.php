<?php
/**
 * E-Mail einem Projekt zuordnen
 *
 * POST-Parameter:
 *   id         - emailReceived_id
 *   project_id - projects_id (oder 0 zum Entfernen)
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$emailId = (int)($_POST['id'] ?? 0);
$projectId = (int)($_POST['project_id'] ?? 0);

if ($emailId <= 0) finish(false, ["code" => "INVALID"]);

$DBLIB->where('emailReceived_id', $emailId);
$DBLIB->where('instances_id', $instanceId);
$email = $DBLIB->getOne('emailReceived', ['emailReceived_id']);

if (!$email) finish(false, ["code" => "NOT_FOUND"]);

// Projekt-Zugehoerigkeit pruefen
if ($projectId > 0) {
    $DBLIB->where('projects_id', $projectId);
    $DBLIB->where('instances_id', $instanceId);
    $project = $DBLIB->getOne('projects', ['projects_id']);
    if (!$project) finish(false, ["code" => "PROJECT_NOT_FOUND"]);
}

$DBLIB->where('emailReceived_id', $emailId);
$updated = $DBLIB->update('emailReceived', [
    'projects_id' => $projectId > 0 ? $projectId : null,
]);

finish((bool)$updated);
