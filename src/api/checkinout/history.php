<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$assetId = (int)($_POST['asset_id'] ?? 0);
$projectId = (int)($_POST['project_id'] ?? 0);

if ($assetId > 0) {
    $svc = new CheckInOutService($DBLIB);
    $history = $svc->getAssetHistory($assetId);
    finish(true, null, ["history" => $history]);
} elseif ($projectId > 0) {
    $svc = new CheckInOutService($DBLIB);
    $history = $svc->getProjectHistory($projectId);
    $outstanding = $svc->getOutstandingCheckouts($projectId);
    finish(true, null, ["history" => $history, "outstanding" => $outstanding]);
} else {
    finish(false, ["code" => "INVALID"]);
}
