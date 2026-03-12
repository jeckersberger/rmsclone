<?php
require_once __DIR__ . '/../common/headSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:VIEW")) die($TWIG->render('404.twig', $PAGEDATA));

$PAGEDATA['pageConfig'] = ['TITLE' => 'SEPA-Lastschriften', 'BREADCRUMB' => true];

// Kunden fuer Dropdown laden
$DBLIB->where('instances_id', $AUTH->data['instance']['instances_id']);
$DBLIB->where('clients_deleted', 0);
$DBLIB->orderBy('clients_name', 'ASC');
$PAGEDATA['clients'] = $DBLIB->get('clients', null, ['clients_id', 'clients_name']) ?: [];

echo $TWIG->render('business/sepa.twig', $PAGEDATA);
