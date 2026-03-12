<?php
/**
 * IMAP-Verbindung testen
 *
 * POST-Parameter:
 *   server     - IMAP Server
 *   port       - Port
 *   encryption - None/SSL/TLS
 *   username   - Benutzername
 *   password   - Passwort
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/ImapMailService.php';

if (!$AUTH->instancePermissionCheck("EMAIL_INBOX:SETTINGS") && !$AUTH->instancePermissionCheck("INSTANCES:EDIT")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];

$server = trim($_POST['server'] ?? '');
$port = (int)($_POST['port'] ?? 993);
$encryption = trim($_POST['encryption'] ?? 'SSL');
$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if (empty($server) || empty($username) || empty($password)) {
    finish(false, ["message" => "Server, Benutzername und Passwort sind Pflichtfelder."]);
}

$imapService = new ImapMailService($DBLIB, $instanceId);
$result = $imapService->testConnection($server, $port, $encryption, $username, $password);

if ($result['success']) {
    finish(true, null, $result);
} else {
    finish(false, ["message" => $result['message']]);
}
