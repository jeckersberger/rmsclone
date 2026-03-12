<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/TransportService.php';

if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["message" => "Permission denied"]);

$planId = intval($_POST['plan_id'] ?? 0);
if (!$planId) finish(false, ["message" => "plan_id required"]);

try {
    $service = new TransportService($DBLIB);

    // Verify plan belongs to this instance
    $plan = $service->getPlan($planId, $AUTH->data['instance']['instances_id']);
    if (!$plan) finish(false, ["message" => "Transportplan nicht gefunden"]);

    $result = $service->cancelPlan($planId);

    finish($result, $result ? null : ["message" => "Transportplan konnte nicht storniert werden"]);
} catch (Exception $e) {
    finish(false, ["message" => "Fehler beim Stornieren: " . $e->getMessage()]);
}
