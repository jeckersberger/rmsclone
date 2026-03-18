<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/InsuranceService.php';

if (!$AUTH->instancePermissionCheck("INSURANCE:EDIT")) {
    finish(false, ["code" => "PERMISSIONS"]);
}

$svc = new InsuranceService($DBLIB);
$certId = intval($_POST['cert_id'] ?? 0);
$userId = $AUTH->data['user']['users_userid'];

if (!$certId) {
    finish(false, ["code" => "MISSING_CERT_ID"]);
}

$result = $svc->verifyCertificate($certId, $userId);
finish($result ? true : false, $result ? null : ["code" => "VERIFY_FAILED"]);
