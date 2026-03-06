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

    $server = $FEDERATION->authenticateRequest($apiKey);
    if (!$server) {
        finish(false, ['code' => 'AUTH', 'message' => 'Invalid or revoked API key']);
    }

    return $server;
}
