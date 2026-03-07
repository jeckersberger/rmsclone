<?php
/**
 * TAN fuer eine laufende FinTS-Session einreichen.
 *
 * POST:
 *   session_id  - fints_tan_sessions.id
 *   tan         - Die eingegebene TAN
 *   pin         - Online-Banking PIN (noetig fuer Dialog-Wiederaufnahme)
 *
 * Response:
 *   session_id  - Session-ID
 *   needs_tan   - Ob eine weitere TAN noetig ist
 *   result      - Ergebnis (wenn needs_tan=false)
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:CREATE")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$userId = (int)$AUTH->data['users_userid'];

$sessionId = (int)($_POST['session_id'] ?? 0);
$tan = trim($_POST['tan'] ?? '');
$pin = $_POST['pin'] ?? '';

if ($sessionId <= 0) finish(false, ["message" => "session_id erforderlich."]);
if (!$tan) finish(false, ["message" => "TAN erforderlich."]);
if (!$pin) finish(false, ["message" => "PIN erforderlich."]);

require_once __DIR__ . '/../../services/FinTSService.php';
require_once __DIR__ . '/../../services/DocumentLifecycleService.php';
require_once __DIR__ . '/../../services/BankImportService.php';

$fints = new FinTSService($DBLIB);

try {
    $result = $fints->submitTan($instanceId, $sessionId, $tan, $pin, $userId);
    finish(true, null, $result);
} catch (\Exception $e) {
    finish(false, ["message" => "TAN-Fehler: " . $e->getMessage()]);
}
