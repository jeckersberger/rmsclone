<?php
/**
 * Federation Handshake - Partnerschaft aufbauen
 *
 * Wird von einem Remote-Server aufgerufen wenn sich eine Firma
 * mit unserem Server verbinden moechte.
 *
 * POST (JSON):
 *   partner_code              - Partner-Code unserer Instanz
 *   requesting_server_url     - URL des anfragenden Servers
 *   requesting_server_name    - Name der anfragenden Firma
 *   requesting_server_instance_id - instances_id auf dem Remote-Server
 *   requesting_api_key        - API-Key den der Remote-Server uns gibt
 *   api_version               - Protokoll-Version
 */
require_once __DIR__ . '/../apiHead.php';
require_once __DIR__ . '/../../services/FederationService.php';

$FEDERATION = new FederationService($DBLIB);

$partnerCode = trim($_POST['partner_code'] ?? '');
$requestingUrl = trim($_POST['requesting_server_url'] ?? '');
$requestingName = trim($_POST['requesting_server_name'] ?? '');
$requestingInstanceId = (int)($_POST['requesting_server_instance_id'] ?? 0);
$requestingApiKey = trim($_POST['requesting_api_key'] ?? '');

if (empty($partnerCode) || empty($requestingUrl) || empty($requestingApiKey)) {
    finish(false, ['code' => 'INVALID', 'message' => 'Missing required fields']);
}

// URL validieren
if (!filter_var($requestingUrl, FILTER_VALIDATE_URL)) {
    finish(false, ['code' => 'INVALID', 'message' => 'Invalid server URL']);
}

$result = $FEDERATION->handleIncomingHandshake(
    $partnerCode,
    $requestingUrl,
    $requestingName,
    $requestingInstanceId,
    $requestingApiKey
);

if ($result['success']) {
    finish(true, null, [
        'api_key' => $result['api_key'],
        'server_name' => $result['server_name'],
        'instance_id' => $result['instance_id'],
    ]);
} else {
    // Generische Fehlermeldung um Enumeration zu verhindern
    finish(false, ['code' => 'HANDSHAKE_FAILED', 'message' => 'Partnership could not be established']);
}
