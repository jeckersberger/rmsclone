<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/RecurringProjectService.php';

if (!$AUTH->instancePermissionCheck("PROJECTS:CREATE:EDIT_PROJECTS")) finish(false, ["message" => "Permission denied"]);

$templateId = intval($_POST['template_id'] ?? 0);
if (!$templateId) finish(false, ["message" => "template_id required"]);

$service = new RecurringProjectService($DBLIB);
$result = $service->deleteTemplate($templateId, $AUTH->data['instance']['instances_id']);

finish($result, $result ? null : ["message" => "Template not found"]);
