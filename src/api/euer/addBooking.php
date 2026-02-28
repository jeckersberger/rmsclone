<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_SETTINGS:EDIT")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];

$data = [
    'category_id'  => (int)($_POST['category_id'] ?? 0),
    'date'         => $_POST['date'] ?? '',
    'description'  => trim($_POST['description'] ?? ''),
    'amount'       => (float)str_replace(',', '.', $_POST['amount'] ?? '0'),
    'vat_amount'   => (float)str_replace(',', '.', $_POST['vat_amount'] ?? '0'),
    'projects_id'  => isset($_POST['projects_id']) ? (int)$_POST['projects_id'] : null,
];

if ($data['category_id'] <= 0 || !$data['date'] || !$data['description'] || $data['amount'] == 0)
    finish(false, ["code" => "INVALID", "message" => "Fehlende Pflichtfelder"]);

$svc = new EuerService($DBLIB);
$id = $svc->addBooking($instanceId, $data, $AUTH->data['users_userid']);

finish(true, null, ["id" => $id]);
