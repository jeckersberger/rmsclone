<?php
/**
 * Federation Disconnect - Partnerschaft trennen
 *
 * Wird aufgerufen wenn der Partner-Server die Verbindung trennt.
 */
require_once __DIR__ . '/../apiHead.php';
require_once __DIR__ . '/../../services/FederationService.php';

$FEDERATION = new FederationService($DBLIB);

$apiKey = $_SERVER['HTTP_X_FEDERATION_KEY'] ?? '';
if (empty($apiKey)) {
    finish(false, ['code' => 'AUTH', 'message' => 'Missing API key']);
}

$result = $FEDERATION->handleDisconnect($apiKey);

finish($result);
