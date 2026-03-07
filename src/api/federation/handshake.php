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

// Parse JSON body
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $jsonBody = json_decode(file_get_contents('php://input'), true);
    if (is_array($jsonBody)) {
        $_POST = array_merge($_POST, $jsonBody);
    }
}

// Rate limiting: max 5 handshake attempts per IP per 10 minutes
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateCacheKey = 'federation_handshake_' . hash('sha256', $clientIp);
$rateCacheFile = sys_get_temp_dir() . '/' . $rateCacheKey;
$now = time();
$attempts = [];
if (file_exists($rateCacheFile)) {
    $attempts = json_decode(file_get_contents($rateCacheFile), true) ?: [];
    $attempts = array_filter($attempts, function ($t) use ($now) { return $t > $now - 600; });
}
if (count($attempts) >= 5) {
    finish(false, ['code' => 'RATE_LIMIT', 'message' => 'Too many handshake attempts. Try again later.']);
}
$attempts[] = $now;
file_put_contents($rateCacheFile, json_encode(array_values($attempts)), LOCK_EX);

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
