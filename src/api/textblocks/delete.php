<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_SETTINGS:EDIT")) finish(false, ["code" => "PERMISSIONS"]);

$blockId = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
if (!$blockId || $blockId <= 0) finish(false, ["code" => "INVALID", "message" => "Valid id required"]);

$DBLIB->where('id', $blockId);
$DBLIB->where('instances_id', $AUTH->data['instance']['instances_id']);
$result = $DBLIB->update('text_blocks', ['deleted' => 1]);

if ($result) finish(true);
else finish(false, ["code" => "DELETE_FAILED"]);
