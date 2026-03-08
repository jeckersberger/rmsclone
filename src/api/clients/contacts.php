<?php
/**
 * Ansprechpartner-API (CRUD)
 *
 * Parameter: action = list|get|create|update|delete
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/ClientContactService.php';

if (!$AUTH->instancePermissionCheck("CLIENTS:EDIT")) die("404");

$service = new ClientContactService($DBLIB);
$action  = $_POST['action'] ?? $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        $clientId = (int)($_POST['clients_id'] ?? $_GET['clients_id'] ?? 0);
        if (!$clientId) finish(false, ["code" => "PARAM-ERROR", "message" => "clients_id fehlt"]);

        $contacts = $service->getContacts($clientId);
        finish(true, null, $contacts);
        break;

    case 'get':
        $contactId = (int)($_POST['contact_id'] ?? 0);
        if (!$contactId) finish(false, ["code" => "PARAM-ERROR", "message" => "contact_id fehlt"]);

        $contact = $service->getContact($contactId);
        if (!$contact) finish(false, ["code" => "NOT-FOUND", "message" => "Ansprechpartner nicht gefunden"]);
        finish(true, null, $contact);
        break;

    case 'create':
        $clientId = (int)($_POST['clients_id'] ?? 0);
        if (!$clientId || empty($_POST['name'])) {
            finish(false, ["code" => "PARAM-ERROR", "message" => "clients_id und name sind erforderlich"]);
        }

        // Pruefen ob Kunde zur Instanz gehoert
        $DBLIB->where('clients_id', $clientId);
        $DBLIB->where('instances_id', $AUTH->data['instance']['instances_id']);
        if (!$DBLIB->getOne('clients')) finish(false, ["code" => "FORBIDDEN", "message" => "Kunde nicht gefunden"]);

        $id = $service->createContact($clientId, $_POST);
        if (!$id) finish(false, ["code" => "CREATE-FAIL", "message" => "Ansprechpartner konnte nicht angelegt werden"]);

        $bCMS->auditLog("INSERT", "client_contacts", json_encode($_POST), $AUTH->data['users_userid']);
        finish(true, null, ["id" => $id]);
        break;

    case 'update':
        $contactId = (int)($_POST['contact_id'] ?? 0);
        $clientId  = (int)($_POST['clients_id'] ?? 0);
        if (!$contactId || !$clientId) {
            finish(false, ["code" => "PARAM-ERROR", "message" => "contact_id und clients_id sind erforderlich"]);
        }

        $ok = $service->updateContact($contactId, $clientId, $_POST);
        if (!$ok) finish(false, ["code" => "UPDATE-FAIL", "message" => "Aktualisierung fehlgeschlagen"]);

        $bCMS->auditLog("EDIT", "client_contacts", json_encode($_POST), $AUTH->data['users_userid']);
        finish(true);
        break;

    case 'delete':
        $contactId = (int)($_POST['contact_id'] ?? 0);
        $clientId  = (int)($_POST['clients_id'] ?? 0);
        if (!$contactId || !$clientId) {
            finish(false, ["code" => "PARAM-ERROR", "message" => "contact_id und clients_id sind erforderlich"]);
        }

        $ok = $service->deleteContact($contactId, $clientId);
        if (!$ok) finish(false, ["code" => "DELETE-FAIL", "message" => "Loeschen fehlgeschlagen"]);

        $bCMS->auditLog("DELETE", "client_contacts", "id=" . $contactId, $AUTH->data['users_userid']);
        finish(true);
        break;

    default:
        finish(false, ["code" => "UNKNOWN-ACTION", "message" => "Unbekannte Aktion: " . $action]);
}
