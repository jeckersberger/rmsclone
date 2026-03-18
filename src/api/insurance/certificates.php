<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/InsuranceService.php';

if (!$AUTH->instancePermissionCheck("INSURANCE:VIEW")) {
    finish(false, ["code" => "PERMISSIONS"]);
}

$svc = new InsuranceService($DBLIB);
$instanceId = $AUTH->data['instance']['instances_id'];
$clientId = intval($_GET['client_id'] ?? $_POST['client_id'] ?? 0);

if (!$clientId) {
    finish(false, ["code" => "MISSING_CLIENT_ID"]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$AUTH->instancePermissionCheck("INSURANCE:EDIT")) {
        finish(false, ["code" => "PERMISSIONS"]);
    }

    $certId = $svc->uploadClientCertificate($clientId, [
        'instances_id' => $instanceId,
        'certificate_type' => $_POST['certificate_type'] ?? 'liability',
        'file_path' => $_POST['file_path'] ?? '',
        'valid_until' => $_POST['valid_until'] ?? date('Y-m-d'),
    ]);

    if ($certId) {
        finish(true, ["id" => $certId]);
    } else {
        finish(false, ["code" => "UPLOAD_FAILED"]);
    }
} else {
    $certificates = $svc->getClientCertificates($clientId, $instanceId);
    finish(true, null, ['certificates' => $certificates]);
}
