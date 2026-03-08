<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/TransportService.php';

if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) die("404");

if (empty($_POST['id'])) finish(false, ["message" => "Plan-ID erforderlich"]);

$service = new TransportService($DBLIB);
$instanceId = $AUTH->data['instance']['instances_id'];

// Pruefen, dass Plan zur Instanz gehoert
$plan = $service->getPlan((int) $_POST['id'], $instanceId);
if (!$plan) finish(false, ["message" => "Transportplan nicht gefunden"]);

// Aktion: Position als geladen markieren
if (!empty($_POST['action']) && $_POST['action'] === 'mark_loaded' && !empty($_POST['item_id'])) {
    $result = $service->markLoaded((int) $_POST['item_id']);
    if ($result) {
        $bCMS->auditLog("UPDATE", "transport_items", "Position {$_POST['item_id']} als geladen markiert", $AUTH->data['users_userid']);
        finish(true);
    }
    finish(false, ["message" => "Konnte Position nicht als geladen markieren"]);
}

// Aktion: Position als geliefert markieren
if (!empty($_POST['action']) && $_POST['action'] === 'mark_delivered' && !empty($_POST['item_id'])) {
    $result = $service->markDelivered((int) $_POST['item_id']);
    if ($result) {
        $bCMS->auditLog("UPDATE", "transport_items", "Position {$_POST['item_id']} als geliefert markiert", $AUTH->data['users_userid']);
        finish(true);
    }
    finish(false, ["message" => "Konnte Position nicht als geliefert markieren"]);
}

// Aktion: Position hinzufuegen
if (!empty($_POST['action']) && $_POST['action'] === 'add_item') {
    if (empty($_POST['assetTypes_id']) || empty($_POST['quantity'])) {
        finish(false, ["message" => "Equipment-Typ und Menge erforderlich"]);
    }
    $itemId = $service->addItem((int) $_POST['id'], (int) $_POST['assetTypes_id'], (int) $_POST['quantity']);
    if ($itemId) {
        finish(true, ["item_id" => $itemId]);
    }
    finish(false, ["message" => "Position konnte nicht hinzugefuegt werden"]);
}

// Standard: Plan-Daten aktualisieren
$data = [];
$fields = ['projects_id', 'from_warehouse_id', 'to_warehouse_id', 'to_address',
           'driver_name', 'vehicle', 'planned_date', 'planned_time', 'status', 'notes'];
foreach ($fields as $field) {
    if (isset($_POST[$field])) $data[$field] = $_POST[$field];
}

$result = $service->updatePlan((int) $_POST['id'], $data);
if ($result) {
    $bCMS->auditLog("UPDATE", "transport_plans", "Transportplan {$_POST['id']} aktualisiert", $AUTH->data['users_userid']);
    finish(true);
}
finish(false, ["message" => "Aktualisierung fehlgeschlagen"]);
