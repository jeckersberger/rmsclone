<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/DamageReportService.php';

if (!$AUTH->instancePermissionCheck("ASSETS:VIEW")) finish(false, ["message" => "Permission denied"]);

$service = new DamageReportService($DBLIB);

if (!empty($_POST['asset_id'])) {
    $reports = $service->getAssetReports(intval($_POST['asset_id']));
} elseif (!empty($_POST['project_id'])) {
    $reports = $service->getProjectReports(intval($_POST['project_id']));
} else {
    $reports = [];
}

$stats = $service->getStats($AUTH->data['instance']['instances_id']);

finish(true, null, ['reports' => $reports, 'stats' => $stats]);
