<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/DamageReportService.php';

if (!$AUTH->instancePermissionCheck("ASSETS:VIEW")) finish(false, ["message" => "Permission denied"]);

$assetId = intval($_POST['asset_id'] ?? 0);
$projectId = intval($_POST['project_id'] ?? 0);
if (!$assetId) finish(false, ["message" => "asset_id required"]);

$data = [
    'severity' => $_POST['severity'] ?? 'minor',
    'description' => $_POST['description'] ?? '',
    'repair_estimate' => isset($_POST['repair_estimate']) ? floatval($_POST['repair_estimate']) : null,
    'charge_to_client' => intval($_POST['charge_to_client'] ?? 0),
];

$service = new DamageReportService($DBLIB);
$id = $service->createReport($AUTH->data['instance']['instances_id'], $assetId, $projectId, $data, $AUTH->data['users_userid']);

finish($id > 0, $id > 0 ? null : ["message" => "Could not create report"], ['id' => $id]);
