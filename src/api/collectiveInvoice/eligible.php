<?php
/**
 * Projekte fuer Sammelrechnung vorschlagen (noch nicht abgerechnete Projekte eines Kunden)
 * GET/POST: client_id
 */
require_once __DIR__ . '/../apiHeadSecure.php';
$AUTH->requirePermission('PROJECTS:PROJECT_PAYMENTS:CREATE');

$clientId = intval($_POST['client_id'] ?? $_GET['client_id'] ?? 0);
if (!$clientId) {
    finish(false, ["message" => "Kunden-ID fehlt."]);
}

$instanceId = $AUTH->data['instance']['instances_id'];

require_once __DIR__ . '/../../services/CollectiveInvoiceService.php';

$service = new CollectiveInvoiceService($DBLIB);
$projects = $service->getEligibleProjects($instanceId, $clientId);

finish(true, false, ['projects' => $projects]);
