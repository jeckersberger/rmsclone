<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/TransportService.php';

if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) die("404");

if (empty($_POST['id'])) finish(false, ["message" => "Plan-ID erforderlich"]);

$service = new TransportService($DBLIB);
$instanceId = $AUTH->data['instance']['instances_id'];

// Pruefen, dass Plan zur Instanz gehoert
$plan = $service->getPlan((int) $_POST['id'], $instanceId);
if (!$plan) finish(false, ["message" => "Transportplan nicht gefunden"]);

if ($plan['status'] === 'cancelled') finish(false, ["message" => "Plan ist bereits storniert"]);

$result = $service->cancelPlan((int) $_POST['id']);
if ($result) {
    $bCMS->auditLog("CANCEL", "transport_plans", "Transportplan {$_POST['id']} storniert", $AUTH->data['users_userid']);
    finish(true);
}
finish(false, ["message" => "Stornierung fehlgeschlagen"]);
