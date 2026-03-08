<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/TransportService.php';

if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) die("404");

$service = new TransportService($DBLIB);
$instanceId = $AUTH->data['instance']['instances_id'];

if (!empty($_POST['project_id'])) {
    // Plaene fuer ein bestimmtes Projekt
    $plans = $service->getPlansForProject((int) $_POST['project_id']);
} elseif (!empty($_POST['date'])) {
    // Plaene fuer ein bestimmtes Datum
    $plans = $service->getPlansForDate($instanceId, $_POST['date']);
} else {
    // Alle Plaene (optional mit Datumsbereich)
    $plans = $service->getPlans(
        $instanceId,
        $_POST['date_from'] ?? null,
        $_POST['date_to'] ?? null
    );
}

finish(true, ["plans" => $plans]);
