<?php
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->instancePermissionCheck("CLIENTS:VIEW")) finish(false, ["message" => "Permission denied"]);

$DBLIB->where('instances_id', $AUTH->data['instance']['instances_id']);
$DBLIB->where('clients_deleted', 0);
$DBLIB->where('clients_archived', 0);
$DBLIB->orderBy('clients_name', 'ASC');
$clients = $DBLIB->get('clients', null, ['clients_id', 'clients_name', 'clients_email', 'clients_phone']) ?: [];

finish(true, null, ['clients' => $clients]);
