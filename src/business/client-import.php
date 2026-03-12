<?php
require_once __DIR__ . '/../common/headSecure.php';
if (!$AUTH->instancePermissionCheck("CLIENTS:CREATE")) die($TWIG->render('404.twig', $PAGEDATA));

$PAGEDATA['pageConfig'] = ['TITLE' => 'Kunden-Import', 'BREADCRUMB' => true];

// Import-Felder fuer das Mapping
require_once __DIR__ . '/../services/ClientImportService.php';
$PAGEDATA['targetFields'] = ClientImportService::FIELD_MAP;

// Import-Protokoll laden
$importService = new ClientImportService($DBLIB);
$PAGEDATA['importLog'] = $importService->getImportLog($AUTH->data['instance']['instances_id']);

echo $TWIG->render('business/client-import.twig', $PAGEDATA);
