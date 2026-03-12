<?php
/**
 * Kundenkategorien-API (CRUD)
 *
 * Parameter: action = list|get|create|update|delete
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/ClientCategoryService.php';

if (!$AUTH->instancePermissionCheck("CLIENTS:EDIT")) die("404");

$service    = new ClientCategoryService($DBLIB);
$instanceId = $AUTH->data['instance']['instances_id'];
$action     = $_POST['action'] ?? $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        $categories = $service->getCategories($instanceId);
        finish(true, null, $categories);
        break;

    case 'get':
        $categoryId = (int)($_POST['category_id'] ?? $_GET['category_id'] ?? 0);
        if (!$categoryId) finish(false, ["code" => "PARAM-ERROR", "message" => "category_id fehlt"]);

        $cat = $service->getCategory($categoryId);
        if (!$cat) finish(false, ["code" => "NOT-FOUND", "message" => "Kategorie nicht gefunden"]);
        finish(true, null, $cat);
        break;

    case 'create':
        $name  = trim($_POST['name'] ?? '');
        $color = trim($_POST['color'] ?? '#6c757d');
        if (!$name) finish(false, ["code" => "PARAM-ERROR", "message" => "Name ist erforderlich"]);

        $id = $service->createCategory($instanceId, $name, $color);
        if (!$id) finish(false, ["code" => "CREATE-FAIL", "message" => "Kategorie konnte nicht angelegt werden"]);

        $bCMS->auditLog("INSERT", "client_categories", "name={$name}", $AUTH->data['users_userid']);
        finish(true, null, ["id" => $id]);
        break;

    case 'update':
        $categoryId = (int)($_POST['category_id'] ?? 0);
        if (!$categoryId) finish(false, ["code" => "PARAM-ERROR", "message" => "category_id fehlt"]);

        $ok = $service->updateCategory($categoryId, $instanceId, $_POST);
        if (!$ok) finish(false, ["code" => "UPDATE-FAIL", "message" => "Aktualisierung fehlgeschlagen"]);

        $bCMS->auditLog("EDIT", "client_categories", json_encode($_POST), $AUTH->data['users_userid']);
        finish(true);
        break;

    case 'delete':
        $categoryId = (int)($_POST['category_id'] ?? 0);
        if (!$categoryId) finish(false, ["code" => "PARAM-ERROR", "message" => "category_id fehlt"]);

        $ok = $service->deleteCategory($categoryId, $instanceId);
        if (!$ok) finish(false, ["code" => "DELETE-FAIL", "message" => "Loeschen fehlgeschlagen"]);

        $bCMS->auditLog("DELETE", "client_categories", "id=" . $categoryId, $AUTH->data['users_userid']);
        finish(true);
        break;

    case 'clients':
        // Alle Kategorien eines bestimmten Kunden laden
        $clientId = (int)($_POST['clients_id'] ?? $_GET['clients_id'] ?? 0);
        if (!$clientId) finish(false, ["code" => "PARAM-ERROR", "message" => "clients_id fehlt"]);

        $cats = $service->getClientCategories($clientId);
        finish(true, null, $cats);
        break;

    case 'byCategory':
        // Alle Kunden einer Kategorie laden
        $categoryId = (int)($_POST['category_id'] ?? $_GET['category_id'] ?? 0);
        if (!$categoryId) finish(false, ["code" => "PARAM-ERROR", "message" => "category_id fehlt"]);

        $clients = $service->getClientsByCategory($categoryId);
        finish(true, null, $clients);
        break;

    default:
        finish(false, ["code" => "UNKNOWN-ACTION", "message" => "Unbekannte Aktion: " . $action]);
}
