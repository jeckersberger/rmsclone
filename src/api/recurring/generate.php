<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/RecurringProjectService.php';

if (!$AUTH->instancePermissionCheck("PROJECTS:CREATE:EDIT_PROJECTS")) finish(false, ["message" => "Permission denied"]);

$service = new RecurringProjectService($DBLIB);
$created = $service->generateUpcoming($AUTH->data['instance']['instances_id']);

finish(true, null, ['created' => $created, 'count' => count($created)]);
