<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:CREATE")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$mandateId = (int)($_POST['mandate_id'] ?? 0);

if ($mandateId <= 0) finish(false, ["code" => "INVALID", "message" => "Keine Mandat-ID angegeben"]);

$svc = new SepaService($DBLIB);
$result = $svc->revokeMandate($mandateId, $instanceId);

if (!$result) finish(false, ["code" => "ERROR", "message" => "Mandat konnte nicht widerrufen werden"]);

finish(true, null, ["message" => "Mandat erfolgreich widerrufen"]);
