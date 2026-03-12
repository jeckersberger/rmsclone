<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/DamageReportService.php';

if (!$AUTH->instancePermissionCheck("ASSETS:VIEW")) finish(false, ["message" => "Permission denied"]);

$reportId = intval($_POST['report_id'] ?? 0);
$status = $_POST['status'] ?? '';
if (!$reportId || !$status) finish(false, ["message" => "report_id and status required"]);

// Verify report belongs to an asset in this instance
$DBLIB->where('damage_reports.id', $reportId);
$DBLIB->join('assets', 'damage_reports.assets_id = assets.assets_id', 'LEFT');
$DBLIB->where('assets.instances_id', $AUTH->data['instance']['instances_id']);
if (!$DBLIB->getOne('damage_reports', ['damage_reports.id'])) finish(false, ["code" => "NOT_FOUND"]);

$service = new DamageReportService($DBLIB);
$result = $service->updateStatus($reportId, $status, $_POST['resolution'] ?? null);

finish($result, $result ? null : ["message" => "Could not update status"]);
