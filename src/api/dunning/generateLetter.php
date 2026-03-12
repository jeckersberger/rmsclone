<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("DUNNING:CREATE") && !$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:CREATE")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$dunningId = (int)($_POST['dunning_id'] ?? 0);

if ($dunningId <= 0) finish(false, ["code" => "INVALID", "message" => "Keine gueltige Mahnungs-ID angegeben"]);

require_once __DIR__ . '/../../services/DunningLetterService.php';

$svc = new DunningLetterService($DBLIB);
$result = $svc->generateLetter($instanceId, $dunningId, $AUTH->data['users_userid']);

if (!$result) finish(false, ["code" => "ERROR", "message" => "Mahnbrief konnte nicht erzeugt werden. Pruefe ob die Mahnung existiert."]);

// Build download URL
$downloadUrl = null;
if (!empty($result['s3files_id'])) {
    global $bCMS;
    if (isset($bCMS)) {
        $downloadUrl = $bCMS->s3URL($result['s3files_id']);
    }
}

finish(true, null, [
    'dunning_id' => $result['dunning_id'],
    's3files_id' => $result['s3files_id'],
    'filename'   => $result['filename'],
    'download_url' => $downloadUrl,
]);
