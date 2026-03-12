<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/DeliveryNoteService.php';

if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["message" => "Permission denied"]);

$projectId = intval($_POST['project_id'] ?? 0);
if (!$projectId) finish(false, ["message" => "project_id required"]);

$service = new DeliveryNoteService($DBLIB);
$note = $service->generateFromProject($AUTH->data['instance']['instances_id'], $projectId);

if (!empty($note)) {
    finish(true, null, $note);
} else {
    finish(false, ["message" => "Project not found or no assets assigned"]);
}
