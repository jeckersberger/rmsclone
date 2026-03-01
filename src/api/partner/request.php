<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:EDIT:CLIENT")) finish(false, ["code" => "PERMISSIONS"]);

$toInstanceId = (int)($_POST['to_instance_id'] ?? 0);
$projectId = (int)($_POST['project_id'] ?? 0);
$startDate = $_POST['start_date'] ?? '';
$endDate = $_POST['end_date'] ?? '';
$equipment = json_decode($_POST['equipment'] ?? '[]', true);
$notes = $_POST['notes'] ?? null;

if ($toInstanceId <= 0 || !$startDate || !$endDate || empty($equipment)) {
    finish(false, ["code" => "INVALID"]);
}

$svc = new PartnerService($DBLIB);
$requestId = $svc->createRequest(
    $AUTH->data['instance']['instances_id'],
    $toInstanceId,
    $projectId,
    ['equipment' => $equipment, 'notes' => $notes],
    $startDate, $endDate,
    $AUTH->data['users_userid']
);

finish(true, null, ['request_id' => $requestId]);
