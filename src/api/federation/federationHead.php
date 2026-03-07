<?php
/**
 * Federation API Header
 *
 * Authentifizierung per X-Federation-Key Header statt User-Session.
 * Wird von allen Federation-Endpoints verwendet (ausser handshake).
 */
require_once __DIR__ . '/../apiHead.php';

require_once __DIR__ . '/../../services/FederationService.php';

$FEDERATION = new FederationService($DBLIB);

// Parse JSON body into $_POST for federation endpoints (they send JSON, not form data)
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $jsonBody = json_decode(file_get_contents('php://input'), true);
    if (is_array($jsonBody)) {
        $_POST = array_merge($_POST, $jsonBody);
    }
}

/**
 * Federation-Authentifizierung pruefen
 * @return array Partner-Server Daten
 */
function federationAuth(): array
{
    global $FEDERATION;

    $apiKey = $_SERVER['HTTP_X_FEDERATION_KEY'] ?? '';
    if (empty($apiKey)) {
        finish(false, ['code' => 'AUTH', 'message' => 'Missing X-Federation-Key header']);
    }

    // Rate limiting: max 60 requests per minute per API key
    $cacheKey = 'federation_rate_' . hash('sha256', $apiKey);
    $cacheFile = sys_get_temp_dir() . '/' . $cacheKey;
    $now = time();
    $requests = [];
    if (file_exists($cacheFile)) {
        $requests = json_decode(file_get_contents($cacheFile), true) ?: [];
        $requests = array_filter($requests, function ($t) use ($now) { return $t > $now - 60; });
    }
    if (count($requests) >= 60) {
        finish(false, ['code' => 'RATE_LIMIT', 'message' => 'Too many requests. Max 60 per minute.']);
    }
    $requests[] = $now;
    file_put_contents($cacheFile, json_encode(array_values($requests)), LOCK_EX);

    $server = $FEDERATION->authenticateRequest($apiKey);
    if (!$server) {
        finish(false, ['code' => 'AUTH', 'message' => 'Invalid or revoked API key']);
    }

    return $server;
}
