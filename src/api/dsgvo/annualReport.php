<?php
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_SETTINGS:VIEW")) {
    finish(false, ["message" => "Keine Berechtigung"]);
}

$instanceId = $AUTH->data['instance']['instances_id'];
$userId = $AUTH->data['users_userid'];
$year = (int)($_POST['year'] ?? date('Y'));
$action = $_POST['action'] ?? 'generate';

require_once __DIR__ . '/../../services/DsgvoReportService.php';
$svc = new DsgvoReportService($DBLIB);

if ($action === 'generate') {
    $pdf = $svc->generateAnnualReport($instanceId, $year);

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="DSGVO_Jahresbericht_' . $year . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;

} elseif ($action === 'store') {
    $fileInfo = $svc->generateAndStore($instanceId, $year, $userId);
    finish(true, null, ['s3files_id' => $fileInfo['s3files_id'] ?? null]);

} else {
    finish(false, ["message" => "Unbekannte Aktion"]);
}
