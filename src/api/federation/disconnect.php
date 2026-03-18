<?php
/**
 * Federation Disconnect - Partnerschaft trennen
 *
 * Wird aufgerufen wenn der Partner-Server die Verbindung trennt.
 * Uses federationHead.php for consistent auth, JSON parsing and rate limiting.
 */
require_once __DIR__ . '/federationHead.php';

// Authenticate first (validates the API key and updates lastSeen)
$server = federationAuth();

// Process the disconnect using the authenticated API key
$apiKey = $_SERVER['HTTP_X_FEDERATION_KEY'] ?? '';
$result = $FEDERATION->handleDisconnect($apiKey);

finish($result, $result ? null : ['code' => 'DISCONNECT_FAILED', 'message' => 'Disconnect failed']);
