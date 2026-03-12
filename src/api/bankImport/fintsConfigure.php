<?php
/**
 * FinTS-Zugangsdaten fuer ein Bankkonto konfigurieren.
 *
 * POST:
 *   account_id          - bank_accounts.id
 *   fints_url           - FinTS-Server URL
 *   fints_blz           - Bankleitzahl
 *   fints_username      - Online-Banking Benutzerkennung
 *   fints_account_number - Kontonummer (optional wenn IBAN gesetzt)
 *   fints_port           - Port (default 443)
 *   fints_version        - FinTS-Version (default '300')
 *   fints_enabled        - Aktivieren (1/0)
 *
 * GET (ohne account_id):
 *   Gibt Bankenliste zurueck (optional: query fuer Suche)
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:CREATE")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = (int)$AUTH->data['instance']['instances_id'];

require_once __DIR__ . '/../../services/FinTSService.php';
$fints = new FinTSService($DBLIB);

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Bankenliste
    $query = trim($_GET['query'] ?? '');
    $banks = $fints->getBankDirectory($query);
    finish(true, null, ["banks" => $banks]);
}

if ($method === 'POST') {
    $accountId = (int)($_POST['account_id'] ?? 0);
    if ($accountId <= 0) finish(false, ["message" => "account_id erforderlich."]);

    // Pruefen ob Bankkonto existiert und zur Instanz gehoert
    $DBLIB->where('id', $accountId);
    $DBLIB->where('instances_id', $instanceId);
    $DBLIB->where('deleted', 0);
    if (!$DBLIB->getOne('bank_accounts')) {
        finish(false, ["message" => "Bankkonto nicht gefunden."]);
    }

    $data = [];
    foreach (['fints_url', 'fints_blz', 'fints_username', 'fints_account_number', 'fints_port', 'fints_version', 'fints_enabled'] as $field) {
        if (isset($_POST[$field])) {
            $data[$field] = $_POST[$field];
        }
    }

    if (empty($data)) {
        finish(false, ["message" => "Keine Felder zum Aktualisieren."]);
    }

    $fints->configureAccount($instanceId, $accountId, $data);
    finish(true, null, ["message" => "FinTS-Konfiguration gespeichert."]);
}

finish(false, ["message" => "Methode nicht unterstuetzt."]);
