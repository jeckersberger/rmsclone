<?php
/**
 * FinTS-Aktion starten (Login + ggf. TAN-Challenge).
 *
 * POST:
 *   account_id  - bank_accounts.id
 *   pin         - Online-Banking PIN (wird NICHT gespeichert)
 *   action      - 'fetch_transactions', 'get_balance', 'get_accounts'
 *   date_from   - (optional) Startdatum YYYY-MM-DD
 *   date_to     - (optional) Enddatum YYYY-MM-DD
 *
 * Response:
 *   session_id  - TAN-Session-ID fuer submitTan
 *   needs_tan   - Ob eine TAN eingegeben werden muss
 *   challenge   - TAN-Challenge Text (wenn needs_tan=true)
 *   result      - Ergebnis (wenn needs_tan=false)
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:CREATE")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$userId = (int)$AUTH->data['users_userid'];

$accountId = (int)($_POST['account_id'] ?? 0);
$pin = $_POST['pin'] ?? '';
$action = trim($_POST['action'] ?? 'fetch_transactions');

if ($accountId <= 0) finish(false, ["message" => "account_id erforderlich."]);
if (!$pin) finish(false, ["message" => "PIN erforderlich."]);
if (!in_array($action, ['fetch_transactions', 'get_balance', 'get_accounts'])) {
    finish(false, ["message" => "Ungueltige Aktion. Erlaubt: fetch_transactions, get_balance, get_accounts"]);
}

$params = [];
if (!empty($_POST['date_from'])) $params['date_from'] = $_POST['date_from'];
if (!empty($_POST['date_to'])) $params['date_to'] = $_POST['date_to'];

require_once __DIR__ . '/../../services/FinTSService.php';
require_once __DIR__ . '/../../services/DocumentLifecycleService.php';
require_once __DIR__ . '/../../services/BankImportService.php';

$fints = new FinTSService($DBLIB);

try {
    $result = $fints->initAction($instanceId, $accountId, $pin, $action, $userId, $params);
    finish(true, null, $result);
} catch (\Exception $e) {
    finish(false, ["message" => "FinTS-Fehler: " . $e->getMessage()]);
}
