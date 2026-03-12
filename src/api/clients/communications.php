<?php
/**
 * Kommunikationsprotokoll CRUD
 *
 * Aktionen via POST-Parameter 'action':
 *   - list:   Eintraege eines Kunden abrufen
 *   - add:    Neuen Eintrag anlegen
 *   - delete: Eintrag loeschen
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/ClientCommunicationService.php';

if (!$AUTH->instancePermissionCheck("CLIENTS:VIEW")) die("404");

$commService = new ClientCommunicationService($DBLIB);
$instanceId = $AUTH->data['instance']['instances_id'];
$userId = $AUTH->data['users_userid'];

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

switch ($action) {
    // ═══════════════════════════════════════════════
    //  EINTRAEGE ABRUFEN
    // ═══════════════════════════════════════════════
    case 'list':
        $clientId = (int)($_POST['clients_id'] ?? $_GET['clients_id'] ?? 0);
        if ($clientId <= 0) {
            finish(false, ["code" => "PARAM-ERROR", "message" => "Kunden-ID fehlt"]);
        }

        // Pruefen ob Kunde zur Instanz gehoert
        $DBLIB->where('clients_id', $clientId);
        $DBLIB->where('instances_id', $instanceId);
        $client = $DBLIB->getOne('clients');
        if (!$client) {
            finish(false, ["code" => "NOT-FOUND", "message" => "Kunde nicht gefunden"]);
        }

        $entries = $commService->getEntries($clientId);

        finish(true, null, ['entries' => $entries]);
        break;

    // ═══════════════════════════════════════════════
    //  NEUEN EINTRAG ANLEGEN
    // ═══════════════════════════════════════════════
    case 'add':
        if (!$AUTH->instancePermissionCheck("CLIENTS:EDIT")) die("404");

        $clientId = (int)($_POST['clients_id'] ?? 0);
        if ($clientId <= 0) {
            finish(false, ["code" => "PARAM-ERROR", "message" => "Kunden-ID fehlt"]);
        }

        // Pruefen ob Kunde zur Instanz gehoert
        $DBLIB->where('clients_id', $clientId);
        $DBLIB->where('instances_id', $instanceId);
        $client = $DBLIB->getOne('clients');
        if (!$client) {
            finish(false, ["code" => "NOT-FOUND", "message" => "Kunde nicht gefunden"]);
        }

        $type = $_POST['type'] ?? '';
        $subject = $_POST['subject'] ?? '';
        $content = $_POST['content'] ?? '';
        $direction = $_POST['direction'] ?? 'outbound';
        $contactPerson = $_POST['contact_person'] ?? null;
        $communicationDate = $_POST['communication_date'] ?? null;

        if (empty($type) || empty($subject)) {
            finish(false, ["code" => "PARAM-ERROR", "message" => "Typ und Betreff sind erforderlich"]);
        }

        $entryId = $commService->addEntry(
            $clientId,
            $type,
            $subject,
            $content,
            $direction,
            $contactPerson,
            $userId,
            $communicationDate
        );

        if (!$entryId) {
            finish(false, ["code" => "CREATE-FAIL", "message" => "Eintrag konnte nicht angelegt werden"]);
        }

        $bCMS->auditLog("INSERT", "client_communications", json_encode([
            'clients_id' => $clientId,
            'type'       => $type,
            'subject'    => $subject,
        ]), $userId);

        finish(true, null, ['entry_id' => $entryId]);
        break;

    // ═══════════════════════════════════════════════
    //  EINTRAG LOESCHEN
    // ═══════════════════════════════════════════════
    case 'delete':
        if (!$AUTH->instancePermissionCheck("CLIENTS:EDIT")) die("404");

        $entryId = (int)($_POST['entry_id'] ?? 0);
        if ($entryId <= 0) {
            finish(false, ["code" => "PARAM-ERROR", "message" => "Eintrags-ID fehlt"]);
        }

        // Pruefen ob Eintrag zur Instanz gehoert
        $entry = $commService->getEntry($entryId);
        if (!$entry || (int)$entry['instances_id'] !== $instanceId) {
            finish(false, ["code" => "NOT-FOUND", "message" => "Eintrag nicht gefunden"]);
        }

        $result = $commService->deleteEntry($entryId);
        if (!$result) {
            finish(false, ["code" => "DELETE-FAIL", "message" => "Eintrag konnte nicht geloescht werden"]);
        }

        $bCMS->auditLog("DELETE", "client_communications", json_encode([
            'entry_id' => $entryId,
        ]), $userId);

        finish(true);
        break;

    default:
        finish(false, ["code" => "UNKNOWN-ACTION", "message" => "Unbekannte Aktion: {$action}"]);
}
