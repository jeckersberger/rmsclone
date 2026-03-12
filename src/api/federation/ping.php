<?php
/**
 * Federation Ping - Verbindungstest
 *
 * Authentifizierter Endpoint zum Testen der Verbindung.
 */
require_once __DIR__ . '/federationHead.php';

$server = federationAuth();

finish(true, null, [
    'server_name' => 'rmsclone',
    'api_version' => '1',
    'timestamp' => date('Y-m-d H:i:s'),
]);
