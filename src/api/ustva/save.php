<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_SETTINGS:EDIT")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$year = (int)($_POST['year'] ?? 0);
$month = (int)($_POST['month'] ?? 0);

if ($month < 1 || $month > 12) finish(false, ["code" => "INVALID", "message" => "Ungueltiger Monat"]);
if ($year < 2000 || $year > 2100) finish(false, ["code" => "INVALID", "message" => "Ungueltiges Jahr"]);

$data = [
    'kz81'     => (float)str_replace(',', '.', $_POST['kz81'] ?? '0'),
    'kz81_tax' => (float)str_replace(',', '.', $_POST['kz81_tax'] ?? '0'),
    'kz86'     => (float)str_replace(',', '.', $_POST['kz86'] ?? '0'),
    'kz86_tax' => (float)str_replace(',', '.', $_POST['kz86_tax'] ?? '0'),
    'kz21'     => (float)str_replace(',', '.', $_POST['kz21'] ?? '0'),
    'kz41'     => (float)str_replace(',', '.', $_POST['kz41'] ?? '0'),
    'kz66'     => (float)str_replace(',', '.', $_POST['kz66'] ?? '0'),
    'kz83'     => (float)str_replace(',', '.', $_POST['kz83'] ?? '0'),
];

$svc = new UstvaService($DBLIB);
$id = $svc->saveReport($instanceId, $year, $month, $data);

finish(true, null, ["id" => $id]);
