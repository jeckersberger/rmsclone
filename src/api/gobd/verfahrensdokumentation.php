<?php
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_SETTINGS:VIEW")) {
    finish(false, ["message" => "Keine Berechtigung"]);
}

$instanceId = $AUTH->data['instance']['instances_id'];
$userId = $AUTH->data['users_userid'];

require_once __DIR__ . '/../../services/GobdDocumentationService.php';
$svc = new GobdDocumentationService($DBLIB);

$action = $_POST['action'] ?? 'generate';

if ($action === 'generate') {
    $pdf = $svc->generatePdf($instanceId);

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="Verfahrensdokumentation_' . date('Y-m-d') . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;

} elseif ($action === 'store') {
    $fileInfo = $svc->generateAndStore($instanceId, $userId);
    finish(true, null, ['s3files_id' => $fileInfo['s3files_id'] ?? null]);

} else {
    finish(false, ["message" => "Unbekannte Aktion"]);
}
