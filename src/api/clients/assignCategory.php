<?php
/**
 * Kategorie einem Kunden zuweisen / entfernen
 *
 * Parameter: action = assign|remove, clients_id, category_id
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/ClientCategoryService.php';

if (!$AUTH->instancePermissionCheck("CLIENTS:EDIT")) die("404");

$service    = new ClientCategoryService($DBLIB);
$action     = $_POST['action'] ?? 'assign';
$clientId   = (int)($_POST['clients_id'] ?? 0);
$categoryId = (int)($_POST['category_id'] ?? 0);

if (!$clientId || !$categoryId) {
    finish(false, ["code" => "PARAM-ERROR", "message" => "clients_id und category_id sind erforderlich"]);
}

// Pruefen ob Kunde zur Instanz gehoert
$DBLIB->where('clients_id', $clientId);
$DBLIB->where('instances_id', $AUTH->data['instance']['instances_id']);
if (!$DBLIB->getOne('clients')) {
    finish(false, ["code" => "FORBIDDEN", "message" => "Kunde nicht gefunden"]);
}

switch ($action) {
    case 'assign':
        $ok = $service->assignCategory($clientId, $categoryId);
        if (!$ok) finish(false, ["code" => "ASSIGN-FAIL", "message" => "Zuordnung fehlgeschlagen"]);
        $bCMS->auditLog("INSERT", "client_category_assignments", "client={$clientId},cat={$categoryId}", $AUTH->data['users_userid']);
        finish(true);
        break;

    case 'remove':
        $ok = $service->removeCategory($clientId, $categoryId);
        if (!$ok) finish(false, ["code" => "REMOVE-FAIL", "message" => "Entfernen fehlgeschlagen"]);
        $bCMS->auditLog("DELETE", "client_category_assignments", "client={$clientId},cat={$categoryId}", $AUTH->data['users_userid']);
        finish(true);
        break;

    default:
        finish(false, ["code" => "UNKNOWN-ACTION", "message" => "Unbekannte Aktion: " . $action]);
}
