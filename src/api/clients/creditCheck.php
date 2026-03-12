<?php
/**
 * Kreditlimit-Pruefung
 *
 * Parameter: clients_id, amount (optional - zu pruefender Rechnungsbetrag)
 * Gibt Kreditinfo zurueck inkl. ob Betrag erlaubt waere.
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/ClientCreditService.php';

if (!$AUTH->instancePermissionCheck("CLIENTS:EDIT")) die("404");

$service  = new ClientCreditService($DBLIB);
$clientId = (int)($_POST['clients_id'] ?? $_GET['clients_id'] ?? 0);
$amount   = (float)($_POST['amount'] ?? $_GET['amount'] ?? 0);

if (!$clientId) {
    finish(false, ["code" => "PARAM-ERROR", "message" => "clients_id fehlt"]);
}

// Pruefen ob Kunde zur Instanz gehoert
$DBLIB->where('clients_id', $clientId);
$DBLIB->where('instances_id', $AUTH->data['instance']['instances_id']);
if (!$DBLIB->getOne('clients')) {
    finish(false, ["code" => "FORBIDDEN", "message" => "Kunde nicht gefunden"]);
}

// Saldo aktualisieren
$service->updateCurrentBalance($clientId);

// Kreditpruefung durchfuehren
$result = $service->checkCreditLimit($clientId, $amount);

finish(true, null, $result);
