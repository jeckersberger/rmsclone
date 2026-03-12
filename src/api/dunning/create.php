<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("DUNNING:CREATE") && !$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:CREATE")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$docLifecycleId = (int)($_POST['document_lifecycle_id'] ?? 0);

if ($docLifecycleId <= 0) finish(false, ["code" => "INVALID"]);

$svc = new DunningService($DBLIB);
$result = $svc->createDunning($instanceId, $docLifecycleId, $AUTH->data['users_userid']);

if (!$result) finish(false, ["code" => "ERROR", "message" => "Keine Mahnung moeglich (Frist noch nicht erreicht oder max. Stufe erreicht)"]);

finish(true, null, $result);
