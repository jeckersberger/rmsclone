<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/TransportService.php';

if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["message" => "Permission denied"]);

$planId = intval($_POST['plan_id'] ?? 0);
if (!$planId) finish(false, ["message" => "plan_id required"]);

try {
    $data = [];
    $fields = ['projects_id', 'from_warehouse_id', 'to_warehouse_id', 'to_address',
               'driver_name', 'vehicle', 'planned_date', 'planned_time', 'status', 'notes'];

    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            if (in_array($field, ['projects_id', 'from_warehouse_id', 'to_warehouse_id'])) {
                $data[$field] = intval($_POST[$field]) ?: null;
            } else {
                $data[$field] = $_POST[$field];
            }
        }
    }

    $service = new TransportService($DBLIB);

    // Verify plan belongs to this instance
    $plan = $service->getPlan($planId, $AUTH->data['instance']['instances_id']);
    if (!$plan) finish(false, ["message" => "Transportplan nicht gefunden"]);

    $result = $service->updatePlan($planId, $data);

    finish($result, $result ? null : ["message" => "Keine Aenderungen vorgenommen"]);
} catch (Exception $e) {
    finish(false, ["message" => "Fehler beim Aktualisieren: " . $e->getMessage()]);
}
