<?php
/**
 * Quick Search API - lightweight live search for the navbar dropdown.
 * Searches projects, clients, assets, and invoices with a limit of 5 per type.
 */
require_once __DIR__ . '/../apiHeadSecure.php';

$term = isset($_POST['term']) ? trim($_POST['term']) : '';
if (strlen($term) < 2) finish(true, null, ["results" => []]);

$instanceId = $AUTH->data['instance']['instances_id'];
$results = [];

// ── Projects ──
if ($AUTH->instancePermissionCheck("PROJECTS:VIEW")) {
    $DBLIB->where("projects.instances_id", $instanceId);
    $DBLIB->where("projects.projects_deleted", 0);
    $DBLIB->where("(projects.projects_name LIKE '%" . $DBLIB->escape($term) . "%')");
    $DBLIB->join("clients", "projects.clients_id=clients.clients_id", "LEFT");
    $DBLIB->orderBy("projects.projects_created", "DESC");
    $projects = $DBLIB->get("projects", 5, ["projects.projects_id", "projects.projects_name", "clients.clients_name"]);
    foreach (($projects ?: []) as $p) {
        $results[] = [
            'type' => 'project',
            'icon' => 'fas fa-folder',
            'badge' => 'pink',
            'title' => $p['projects_name'],
            'subtitle' => $p['clients_name'] ?: '',
            'url' => '/project/?id=' . $p['projects_id']
        ];
    }
}

// ── Clients ──
if ($AUTH->instancePermissionCheck("CLIENTS:VIEW")) {
    $DBLIB->where("clients.instances_id", $instanceId);
    $DBLIB->where("clients.clients_deleted", 0);
    $DBLIB->where("(clients.clients_name LIKE '%" . $DBLIB->escape($term) . "%' OR clients.clients_email LIKE '%" . $DBLIB->escape($term) . "%')");
    $DBLIB->orderBy("clients.clients_name", "ASC");
    $clients = $DBLIB->get("clients", 5, ["clients.clients_id", "clients.clients_name", "clients.clients_email"]);
    foreach (($clients ?: []) as $c) {
        $results[] = [
            'type' => 'client',
            'icon' => 'fas fa-briefcase',
            'badge' => 'lightblue',
            'title' => $c['clients_name'],
            'subtitle' => $c['clients_email'] ?: '',
            'url' => '/clients.php?q=' . urlencode($c['clients_name'])
        ];
    }
}

// ── Assets (by tag or type name) ──
$DBLIB->where("assets.instances_id", $instanceId);
$DBLIB->where("assets.assets_deleted", 0);
$DBLIB->join("assetTypes", "assets.assetTypes_id=assetTypes.assetTypes_id", "LEFT");
$DBLIB->where("(assets.assets_tag LIKE '%" . $DBLIB->escape($term) . "%' OR assetTypes.assetTypes_name LIKE '%" . $DBLIB->escape($term) . "%')");
$DBLIB->orderBy("assetTypes.assetTypes_name", "ASC");
$assets = $DBLIB->get("assets", 5, ["assets.assets_id", "assets.assets_tag", "assetTypes.assetTypes_name"]);
foreach (($assets ?: []) as $a) {
    $results[] = [
        'type' => 'asset',
        'icon' => 'fas fa-warehouse',
        'badge' => 'success',
        'title' => $a['assetTypes_name'] . ($a['assets_tag'] ? ' #' . $a['assets_tag'] : ''),
        'subtitle' => '',
        'url' => '/asset.php?id=' . $a['assets_id']
    ];
}

finish(true, null, ["results" => $results]);
