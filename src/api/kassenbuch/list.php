<?php
/**
 * Kassenbuch: Eintraege mit Datumsfilter und laufendem Saldo abrufen
 * GET: date_from, date_to (optional, default: aktueller Monat)
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_STATS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = (int)$AUTH->data['instance']['instances_id'];

$dateFrom = $_REQUEST['date_from'] ?? date('Y-m-01');
$dateTo = $_REQUEST['date_to'] ?? date('Y-m-t');

require_once __DIR__ . '/../../services/KassenbuchService.php';
$service = new KassenbuchService($DBLIB);

$entries = $service->getEntries($instanceId, $dateFrom, $dateTo);
$summary = $service->getMonthlySummary(
    $instanceId,
    (int)date('Y', strtotime($dateFrom)),
    (int)date('m', strtotime($dateFrom))
);

finish(true, null, [
    'entries'          => $entries['entries'],
    'previous_balance' => $entries['previous_balance'],
    'final_balance'    => $entries['final_balance'],
    'summary'          => $summary,
    'categories'       => $service->getCategories(),
]);
