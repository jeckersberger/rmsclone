<?php
/**
 * Mit einem Remote-Server verbinden
 *
 * POST-Parameter:
 *   server_url   - URL des Partner-Servers (z.B. https://firma-b.de)
 *   partner_code - Partner-Code der Gegenstelle
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/FederationService.php';

if (!$AUTH->instancePermissionCheck("PARTNERS:CREATE") && !$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_SETTINGS:EDIT")) {
    finish(false, ["code" => "PERMISSIONS"]);
}

$instanceId = $AUTH->data['instance']['instances_id'];
$serverUrl = trim($_POST['server_url'] ?? '');
$partnerCode = strtoupper(trim($_POST['partner_code'] ?? ''));

if (empty($serverUrl) || empty($partnerCode)) {
    finish(false, ['code' => 'INVALID', 'message' => 'Server-URL und Partner-Code sind erforderlich.']);
}

// URL validieren
if (!filter_var($serverUrl, FILTER_VALIDATE_URL)) {
    finish(false, ['code' => 'INVALID', 'message' => 'Ungueltige Server-URL.']);
}

// Nur HTTPS erlauben (ausser in Dev-Modus)
if (!$CONFIG['DEV'] && strpos($serverUrl, 'https://') !== 0) {
    finish(false, ['code' => 'INVALID', 'message' => 'Nur HTTPS-Verbindungen sind erlaubt.']);
}

// Partner-Code Format pruefen
if (!preg_match('/^[A-F0-9]{6,16}$/i', $partnerCode)) {
    finish(false, ['code' => 'INVALID', 'message' => 'Ungueltiges Partner-Code Format.']);
}

$federation = new FederationService($DBLIB);
$result = $federation->initiateHandshake($instanceId, $serverUrl, $partnerCode);

if ($result['success']) {
    finish(true, null, [
        'partner_name' => $result['partner_name'],
        'server_id' => $result['server_id'],
    ]);
} else {
    $messages = [
        'already_connected' => 'Mit diesem Server besteht bereits eine Verbindung.',
        'remote_server_error' => 'Der Partner-Server ist nicht erreichbar oder hat den Handshake abgelehnt.',
    ];
    $msg = $messages[$result['error']] ?? 'Verbindung konnte nicht hergestellt werden.';
    finish(false, ['code' => 'HANDSHAKE_FAILED', 'message' => $msg]);
}
