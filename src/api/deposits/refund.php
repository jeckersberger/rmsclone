<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/DepositService.php';

if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_FINANCE:EDIT")) finish(false, ["message" => "Permission denied"]);

$depositId = intval($_POST['deposit_id'] ?? 0);
$refundAmount = floatval($_POST['refund_amount'] ?? 0);
if (!$depositId || $refundAmount <= 0) finish(false, ["message" => "deposit_id and refund_amount required"]);

$service = new DepositService($DBLIB);
$result = $service->refundDeposit($depositId, $refundAmount, $AUTH->data['users_userid'], $_POST['deduction_reason'] ?? null);

finish($result, $result ? null : ["message" => "Could not refund deposit"]);
