<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:CREATE")) finish(false, ["code" => "PERMISSIONS"]);

$sourceDocId = (int)($_POST['source_doc_id'] ?? 0);
$targetType = trim($_POST['target_type'] ?? '');

if ($sourceDocId <= 0 || !$targetType) finish(false, ["code" => "INVALID", "message" => "Missing parameters"]);

$convertOpts = [];
if (!empty($_POST['skonto_enabled'])) {
    $convertOpts['skonto_enabled'] = true;
    if (isset($_POST['skonto_rate'])) $convertOpts['skonto_rate'] = (float)$_POST['skonto_rate'];
    if (isset($_POST['skonto_days'])) $convertOpts['skonto_days'] = (int)$_POST['skonto_days'];
}

$svc = new DocumentLifecycleService($DBLIB);
$newDocId = $svc->convertDocument($sourceDocId, $targetType, $AUTH->data['users_userid'], $convertOpts);

if (!$newDocId) finish(false, ["code" => "ERROR", "message" => "Conversion not possible"]);

finish(true, null, ["new_doc_id" => $newDocId]);
