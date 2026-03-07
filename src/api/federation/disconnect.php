<?php
/**
 * Federation Disconnect - Partnerschaft trennen
 *
 * Wird aufgerufen wenn der Partner-Server die Verbindung trennt.
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

$apiKey = $_SERVER['HTTP_X_FEDERATION_KEY'] ?? '';
if (empty($apiKey)) {
    finish(false, ['code' => 'AUTH', 'message' => 'Missing API key']);
}

$result = $FEDERATION->handleDisconnect($apiKey);

finish($result);
