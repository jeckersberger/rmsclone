<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/RecurringProjectService.php';

if (!$AUTH->instancePermissionCheck("PROJECTS:CREATE:EDIT_PROJECTS")) finish(false, ["message" => "Permission denied"]);

$sourceProjectId = intval($_POST['source_project_id'] ?? 0);
if (!$sourceProjectId) finish(false, ["message" => "source_project_id required"]);

$schedule = [
    'name' => $_POST['name'] ?? null,
    'type' => $_POST['recurrence_type'] ?? 'weekly',
    'day' => isset($_POST['recurrence_day']) ? intval($_POST['recurrence_day']) : null,
    'time_start' => $_POST['time_start'] ?? null,
    'time_end' => $_POST['time_end'] ?? null,
    'duration_hours' => intval($_POST['duration_hours'] ?? 24),
    'delivery_buffer_hours' => intval($_POST['delivery_buffer_hours'] ?? 2),
    'clone_assets' => intval($_POST['clone_assets'] ?? 1),
    'clone_crew' => intval($_POST['clone_crew'] ?? 0),
    'auto_create_days_ahead' => intval($_POST['auto_create_days_ahead'] ?? 7),
];

$service = new RecurringProjectService($DBLIB);
$id = $service->createTemplate($AUTH->data['instance']['instances_id'], $sourceProjectId, $schedule, $AUTH->data['users_userid']);

if ($id) {
    finish(true, null, ['id' => $id]);
} else {
    finish(false, ["message" => "Could not create template"]);
}
