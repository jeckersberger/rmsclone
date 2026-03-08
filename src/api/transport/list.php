<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/TransportService.php';

if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["message" => "Permission denied"]);

try {
    $dateFrom = isset($_POST['date_from']) ? filter_input(INPUT_POST, 'date_from', FILTER_SANITIZE_SPECIAL_CHARS) : null;
    $dateTo = isset($_POST['date_to']) ? filter_input(INPUT_POST, 'date_to', FILTER_SANITIZE_SPECIAL_CHARS) : null;

    $service = new TransportService($DBLIB);
    $plans = $service->getPlans($AUTH->data['instance']['instances_id'], $dateFrom, $dateTo);

    finish(true, null, ['plans' => $plans]);
} catch (Exception $e) {
    finish(false, ["message" => "Fehler beim Laden der Transportplaene: " . $e->getMessage()]);
}
