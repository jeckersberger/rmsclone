<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_SETTINGS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$exportType = $_POST['type'] ?? 'buchungen';
$periodFrom = $_POST['period_from'] ?? date('Y-01-01');
$periodTo = $_POST['period_to'] ?? date('Y-m-d');

$svc = new DatevExportService($DBLIB);

if ($exportType === 'stammdaten') {
    $result = $svc->exportStammdaten($instanceId, $AUTH->data['users_userid']);
} else {
    $result = $svc->exportBuchungen($instanceId, $periodFrom, $periodTo, $AUTH->data['users_userid']);
}

// Convert to Windows-1252 for DATEV compatibility
$csvContent = mb_convert_encoding($result['csv'], 'Windows-1252', 'UTF-8');

header('Content-Type: text/csv; charset=Windows-1252');
header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
echo $csvContent;
exit;
