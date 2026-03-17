<?php
/**
 * E-Mail einem Projekt zuordnen
 *
 * POST-Parameter:
 *   id         - emailReceived_id
 *   project_id - projects_id (oder 0 zum Entfernen)
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("EMAIL_INBOX:EDIT") && !$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$userId = $AUTH->data['users_userid'] ?? 0;
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

$updateData = [
    'projects_id' => $projectId > 0 ? $projectId : null,
];

// Zuordnungs-Metadaten speichern wenn zugeordnet wird
if ($projectId > 0) {
    $updateData['assigned_by'] = $userId;
    $updateData['assigned_at'] = date('Y-m-d H:i:s');
} else {
    // Entfernen der Zuordnung löscht auch die Metadaten
    $updateData['assigned_by'] = null;
    $updateData['assigned_at'] = null;
}

$DBLIB->where('emailReceived_id', $emailId);
$updated = $DBLIB->update('emailReceived', $updateData);

finish((bool)$updated);
