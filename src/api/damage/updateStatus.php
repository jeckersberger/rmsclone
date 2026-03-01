<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/DamageReportService.php';

if (!$AUTH->instancePermissionCheck("ASSETS:VIEW")) finish(false, ["message" => "Permission denied"]);

$reportId = intval($_POST['report_id'] ?? 0);
$status = $_POST['status'] ?? '';
if (!$reportId || !$status) finish(false, ["message" => "report_id and status required"]);

$service = new DamageReportService($DBLIB);
$result = $service->updateStatus($reportId, $status, $_POST['resolution'] ?? null);

finish($result, $result ? null : ["message" => "Could not update status"]);
