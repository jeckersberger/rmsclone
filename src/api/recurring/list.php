<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/RecurringProjectService.php';

if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["message" => "Permission denied"]);

$service = new RecurringProjectService($DBLIB);
$templates = $service->getTemplates($AUTH->data['instance']['instances_id']);

finish(true, null, ['templates' => $templates]);
