<?php
/**
 * Federation Ping - Verbindungstest
 *
 * Authentifizierter Endpoint zum Testen der Verbindung.
 * Returns actual instance name, company code, and API version.
 */
require_once __DIR__ . '/federationHead.php';

$server = federationAuth();

// Get actual instance name and company code
$instanceId = (int)$server['instances_id'];
$DBLIB->where('instances_id', $instanceId);
$instance = $DBLIB->getOne('instances', ['instances_name', 'instances_companyCode']);

finish(true, null, [
    'server_name' => $instance ? $instance['instances_name'] : 'rmsclone',
    'company_code' => $instance['instances_companyCode'] ?? null,
    'api_version' => '1',
    'timestamp' => date('Y-m-d H:i:s'),
    'capabilities' => [
        'binary_epc' => true,
        'company_code_exchange' => true,
        'tag_format_version' => 2,
    ],
]);
