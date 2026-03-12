<?php
/**
 * Status einer FinTS TAN-Session abfragen.
 *
 * GET:
 *   session_id  - fints_tan_sessions.id
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:CREATE")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$userId = (int)$AUTH->data['users_userid'];

$sessionId = (int)($_GET['session_id'] ?? 0);
if ($sessionId <= 0) finish(false, ["message" => "session_id erforderlich."]);

require_once __DIR__ . '/../../services/FinTSService.php';

$fints = new FinTSService($DBLIB);
$status = $fints->getSessionStatus($instanceId, $sessionId, $userId);

if (!$status) {
    finish(false, ["message" => "Session nicht gefunden."]);
}

finish(true, null, $status);
