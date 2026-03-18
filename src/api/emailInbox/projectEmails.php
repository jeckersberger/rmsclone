<?php
/**
 * E-Mail-zu-Projekt-Verwaltung
 *
 * Unterstützte Aktionen:
 *   - list: Alle E-Mails eines Projekts laden
 *   - auto_assign: E-Mails automatisch basierend auf Client-E-Mail zuordnen
 *   - assign: E-Mail einem Projekt zuordnen (wird durch assignProject.php gehandhabt)
 *   - unassign: E-Mail von Projekt entfernen
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/ImapMailService.php';

// Berechtigungen prüfen
if (!$AUTH->instancePermissionCheck("EMAIL_INBOX:VIEW") && !$AUTH->instancePermissionCheck("PROJECTS:VIEW")) {
    finish(false, ["code" => "PERMISSIONS"]);
}

$action = $_GET['action'] ?? 'list';
$instanceId = $AUTH->data['instance']['instances_id'];
$userId = $AUTH->data['users_userid'] ?? 0;

$mailService = new ImapMailService($DBLIB, $instanceId);

switch ($action) {
    case 'list':
        // Alle E-Mails eines Projekts laden
        $projectId = (int)($_GET['project_id'] ?? 0);
        $limit = (int)($_GET['limit'] ?? 50);
        $offset = (int)($_GET['offset'] ?? 0);

        if ($projectId <= 0) {
            finish(false, ["code" => "INVALID_PROJECT"]);
        }

        // Projekt-Berechtigung prüfen
        $DBLIB->where('projects_id', $projectId);
        $DBLIB->where('instances_id', $instanceId);
        $project = $DBLIB->getOne('projects', ['projects_id']);

        if (!$project) {
            finish(false, ["code" => "PROJECT_NOT_FOUND"]);
        }

        $result = $mailService->getProjectEmails($projectId, $limit, $offset);
        finish(true, $result);
        break;

    case 'auto_assign':
        // E-Mails automatisch anhand Client-E-Mail zuordnen
        if (!$AUTH->instancePermissionCheck("EMAIL_INBOX:EDIT")) {
            finish(false, ["code" => "PERMISSIONS"]);
        }

        $projectId = (int)($_POST['project_id'] ?? 0);

        if ($projectId <= 0) {
            finish(false, ["code" => "INVALID_PROJECT"]);
        }

        // Projekt-Berechtigung prüfen
        $DBLIB->where('projects_id', $projectId);
        $DBLIB->where('instances_id', $instanceId);
        $project = $DBLIB->getOne('projects', ['projects_id']);

        if (!$project) {
            finish(false, ["code" => "PROJECT_NOT_FOUND"]);
        }

        $assigned = $mailService->autoAssignByClient($projectId);
        finish(true, ["assigned_count" => $assigned]);
        break;

    case 'unassign':
        // E-Mail von Projekt entfernen
        if (!$AUTH->instancePermissionCheck("EMAIL_INBOX:EDIT")) {
            finish(false, ["code" => "PERMISSIONS"]);
        }

        $emailId = (int)($_POST['email_id'] ?? 0);

        if ($emailId <= 0) {
            finish(false, ["code" => "INVALID_EMAIL"]);
        }

        $success = $mailService->unassignFromProject($emailId);
        finish($success);
        break;

    default:
        finish(false, ["code" => "INVALID_ACTION"]);
}
