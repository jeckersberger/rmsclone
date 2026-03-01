<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$startDate = $_POST['start_date'] ?? null;
$endDate = $_POST['end_date'] ?? null;
$search = $_POST['search'] ?? null;

$svc = new PartnerService($DBLIB);
$equipment = $svc->getPartnerEquipment($AUTH->data['instance']['instances_id'], $startDate, $endDate, $search);

finish(true, null, ['equipment' => $equipment]);
