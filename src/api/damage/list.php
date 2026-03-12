<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/DamageReportService.php';

if (!$AUTH->instancePermissionCheck("ASSETS:VIEW")) finish(false, ["message" => "Permission denied"]);

$service = new DamageReportService($DBLIB);
$instanceId = $AUTH->data['instance']['instances_id'];

if (!empty($_POST['asset_id'])) {
    // Verify asset belongs to current instance
    $DBLIB->where('assets_id', intval($_POST['asset_id']));
    $DBLIB->where('instances_id', $instanceId);
    if (!$DBLIB->getOne('assets', ['assets_id'])) finish(false, ["code" => "NOT_FOUND"]);
    $reports = $service->getAssetReports(intval($_POST['asset_id']));
} elseif (!empty($_POST['project_id'])) {
    // Verify project belongs to current instance
    $DBLIB->where('projects_id', intval($_POST['project_id']));
    $DBLIB->where('instances_id', $instanceId);
    if (!$DBLIB->getOne('projects', ['projects_id'])) finish(false, ["code" => "NOT_FOUND"]);
    $reports = $service->getProjectReports(intval($_POST['project_id']));
} else {
    $reports = [];
}

$stats = $service->getStats($AUTH->data['instance']['instances_id']);

finish(true, null, ['reports' => $reports, 'stats' => $stats]);
