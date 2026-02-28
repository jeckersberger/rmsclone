<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:CREATE")) finish(false, ["code" => "PERMISSIONS"]);

$sourceDocId = (int)($_POST['source_doc_id'] ?? 0);
$targetType = trim($_POST['target_type'] ?? '');

if ($sourceDocId <= 0 || !$targetType) finish(false, ["code" => "INVALID", "message" => "Missing parameters"]);

$svc = new DocumentLifecycleService($DBLIB);
$newDocId = $svc->convertDocument($sourceDocId, $targetType, $AUTH->data['users_userid']);

if (!$newDocId) finish(false, ["code" => "ERROR", "message" => "Conversion not possible"]);

finish(true, null, ["new_doc_id" => $newDocId]);
