<?php
/**
 * Bankkonten verwalten (CRUD).
 *
 * GET:    Liste aller Bankkonten
 * POST:   Neues Bankkonto anlegen
 * PUT:    Bankkonto aktualisieren (account_id erforderlich)
 * DELETE: Bankkonto loeschen (account_id erforderlich)
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:CREATE")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = (int)$AUTH->data['instance']['instances_id'];

require_once __DIR__ . '/../../services/BankImportService.php';
$service = new BankImportService($DBLIB);

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $accounts = $service->getAccounts($instanceId);
        finish(true, null, ["accounts" => $accounts]);
        break;

    case 'POST':
        $name = trim($_POST['account_name'] ?? '');
        if (!$name) finish(false, ["message" => "account_name erforderlich."]);

        $accountData = [
            'account_name' => $name,
            'iban'         => trim($_POST['iban'] ?? ''),
            'bic'          => trim($_POST['bic'] ?? ''),
            'bank_name'    => trim($_POST['bank_name'] ?? ''),
            'currency'     => trim($_POST['currency'] ?? 'EUR'),
            'is_default'   => (int)($_POST['is_default'] ?? 0),
        ];

        $id = $service->createAccount($instanceId, $accountData);

        // FinTS-Konfiguration direkt mitgeben wenn vorhanden
        if (!empty($_POST['fints_url'])) {
            require_once __DIR__ . '/../../services/FinTSService.php';
            $fints = new FinTSService($DBLIB);
            $fintsData = [];
            foreach (['fints_url', 'fints_blz', 'fints_username', 'fints_account_number', 'fints_port', 'fints_version', 'fints_enabled'] as $f) {
                if (isset($_POST[$f])) $fintsData[$f] = $_POST[$f];
            }
            $fints->configureAccount($instanceId, $id, $fintsData);
        }
        finish(true, null, ["id" => $id]);
        break;

    case 'PUT':
        // PUT-Daten lesen
        parse_str(file_get_contents('php://input'), $putData);
        $accountId = (int)($putData['account_id'] ?? 0);
        if ($accountId <= 0) finish(false, ["message" => "account_id erforderlich."]);

        $service->updateAccount($instanceId, $accountId, $putData);
        finish(true, null, ["message" => "Bankkonto aktualisiert."]);
        break;

    case 'DELETE':
        parse_str(file_get_contents('php://input'), $delData);
        $accountId = (int)($delData['account_id'] ?? $_GET['account_id'] ?? 0);
        if ($accountId <= 0) finish(false, ["message" => "account_id erforderlich."]);

        $service->deleteAccount($instanceId, $accountId);
        finish(true, null, ["message" => "Bankkonto geloescht."]);
        break;

    default:
        finish(false, ["message" => "Methode nicht unterstuetzt."]);
}
