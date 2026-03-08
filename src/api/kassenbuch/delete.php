<?php
/**
 * Kassenbuch: Eintrag loeschen
 * POST: id
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_STATS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$entryId = (int)($_POST['id'] ?? 0);

if ($entryId <= 0) finish(false, ["message" => "Eintrags-ID erforderlich."]);

// Pruefen ob Eintrag zur Instanz gehoert
$DBLIB->where('id', $entryId);
$DBLIB->where('instances_id', $instanceId);
if (!$DBLIB->getOne('kassenbuch_entries')) {
    finish(false, ["message" => "Eintrag nicht gefunden."]);
}

require_once __DIR__ . '/../../services/KassenbuchService.php';
$service = new KassenbuchService($DBLIB);

$result = $service->deleteEntry($entryId);

if ($result['success']) {
    finish(true, null, $result);
} else {
    finish(false, ["message" => $result['message']]);
}
