<?php
/**
 * Kunden-Import: Import-Protokoll abrufen
 *
 * Gibt zurueck: Liste der bisherigen Imports mit Statistiken
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/ClientImportService.php';

if (!$AUTH->instancePermissionCheck("CLIENTS:VIEW")) die("404");

$importService = new ClientImportService($DBLIB);
$instanceId = $AUTH->data['instance']['instances_id'];

$log = $importService->getImportLog($instanceId);

finish(true, null, ['log' => $log]);
