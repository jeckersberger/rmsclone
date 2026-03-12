<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/DepositService.php';

if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_FINANCE:EDIT")) finish(false, ["message" => "Permission denied"]);

$projectId = intval($_POST['project_id'] ?? 0);
$amount = floatval($_POST['amount'] ?? 0);
$method = $_POST['method'] ?? 'bank_transfer';
if (!$projectId || $amount <= 0) finish(false, ["message" => "project_id and amount required"]);

$service = new DepositService($DBLIB);
$id = $service->recordDeposit(
    $AUTH->data['instance']['instances_id'],
    $projectId, $amount, $method,
    $AUTH->data['users_userid'],
    $_POST['reference'] ?? null
);

finish($id > 0, null, ['id' => $id]);
