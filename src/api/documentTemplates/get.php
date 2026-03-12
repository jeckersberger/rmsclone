<?php
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_SETTINGS:VIEW"))
    finish(false, ["code" => "PERMISSIONS", "message" => "No permission"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) finish(false, ["code" => "INVALID", "message" => "Invalid template ID"]);

$DBLIB->where("id", $id);
$DBLIB->where("instances_id", $instanceId);
$template = $DBLIB->getOne("document_templates");

if (!$template) finish(false, ["code" => "NOT-FOUND", "message" => "Template not found"]);

finish(true, null, ["template" => $template]);
