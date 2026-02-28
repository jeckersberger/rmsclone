<?php
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_SETTINGS:VIEW"))
    finish(false, ["code" => "PERMISSIONS", "message" => "No permission"]);

$instanceId = $AUTH->data['instance']['instances_id'];

$DBLIB->where("instances_id", $instanceId);
$DBLIB->orderBy("type", "ASC");
$DBLIB->orderBy("name", "ASC");
$templates = $DBLIB->get("document_templates", null, [
    "id", "type", "key", "name", "language", "is_default", "updated_at"
]);

finish(true, null, ["templates" => $templates ?: []]);
