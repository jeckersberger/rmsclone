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
    $DBLIB->where("(projects.projects_name LIKE ?)", ['%' . $bCMS->escapeLikeWildcards($term) . '%']);
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
    $DBLIB->where("(clients.clients_name LIKE ? OR clients.clients_email LIKE ?)", ['%' . $bCMS->escapeLikeWildcards($term) . '%', '%' . $bCMS->escapeLikeWildcards($term) . '%']);
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
$DBLIB->where("(assets.assets_tag LIKE ? OR assetTypes.assetTypes_name LIKE ?)", ['%' . $bCMS->escapeLikeWildcards($term) . '%', '%' . $bCMS->escapeLikeWildcards($term) . '%']);
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

// ── Invoices / Documents ──
if ($AUTH->instancePermissionCheck("PROJECTS:VIEW")) {
    $escapedTerm = $bCMS->escapeLikeWildcards($term);
    $DBLIB->where("de.instances_id", $instanceId);
    $DBLIB->where("de.document_exports_deleted", 0);
    $DBLIB->join("projects p", "de.projects_id=p.projects_id", "LEFT");
    $DBLIB->join("clients c", "p.clients_id=c.clients_id", "LEFT");
    $DBLIB->where("(de.document_exports_number LIKE ? OR p.projects_name LIKE ? OR c.clients_name LIKE ?)", [
        '%' . $escapedTerm . '%', '%' . $escapedTerm . '%', '%' . $escapedTerm . '%'
    ]);
    $DBLIB->orderBy("de.document_exports_date", "DESC");
    $docs = $DBLIB->get("document_exports de", 5, [
        "de.document_exports_id", "de.document_exports_number", "de.document_exports_type",
        "de.document_exports_gross", "p.projects_name", "c.clients_name"
    ]);
    $typeIcons = ['invoice' => 'fa-file-invoice-dollar', 'quote' => 'fa-file-alt', 'credit' => 'fa-file-medical-alt'];
    $typeLabels = ['invoice' => 'Rechnung', 'quote' => 'Angebot', 'credit' => 'Gutschrift'];
    foreach (($docs ?: []) as $d) {
        $docType = $d['document_exports_type'] ?? 'invoice';
        $results[] = [
            'type' => 'document',
            'icon' => 'fas ' . ($typeIcons[$docType] ?? 'fa-file'),
            'badge' => 'warning',
            'title' => ($typeLabels[$docType] ?? $docType) . ' ' . ($d['document_exports_number'] ?: '#' . $d['document_exports_id']),
            'subtitle' => ($d['clients_name'] ?: ($d['projects_name'] ?: '')) . ($d['document_exports_gross'] ? ' | ' . number_format($d['document_exports_gross'] / 100, 2, ',', '.') . ' EUR' : ''),
            'url' => '/project/?id=' . $d['document_exports_id'] . '&tab=documents'
        ];
    }
}

// ── Maintenance Jobs ──
if ($AUTH->instancePermissionCheck("MAINTENANCE:VIEW")) {
    $escapedTerm = $bCMS->escapeLikeWildcards($term);
    $DBLIB->where("maintenanceJobs.instances_id", $instanceId);
    $DBLIB->where("maintenanceJobs.maintenanceJobs_deleted", 0);
    $DBLIB->where("(maintenanceJobs.maintenanceJobs_title LIKE ? OR maintenanceJobs.maintenanceJobs_faultDescription LIKE ?)", [
        '%' . $escapedTerm . '%', '%' . $escapedTerm . '%'
    ]);
    $DBLIB->orderBy("maintenanceJobs.maintenanceJobs_created", "DESC");
    $jobs = $DBLIB->get("maintenanceJobs", 5, [
        "maintenanceJobs.maintenanceJobs_id", "maintenanceJobs.maintenanceJobs_title",
        "maintenanceJobs.maintenanceJobs_priority", "maintenanceJobs.maintenanceJobs_status"
    ]);
    $prioColors = [1 => 'secondary', 2 => 'info', 3 => 'warning', 4 => 'danger', 5 => 'danger'];
    foreach (($jobs ?: []) as $j) {
        $results[] = [
            'type' => 'maintenance',
            'icon' => 'fas fa-tools',
            'badge' => $prioColors[$j['maintenanceJobs_priority']] ?? 'secondary',
            'title' => $j['maintenanceJobs_title'],
            'subtitle' => 'Status: ' . ($j['maintenanceJobs_status'] ?: 'Offen'),
            'url' => '/maintenance/job.php?id=' . $j['maintenanceJobs_id']
        ];
    }
}

// ── Users ──
if ($AUTH->instancePermissionCheck("USERS:VIEW")) {
    $escapedTerm = $bCMS->escapeLikeWildcards($term);
    $DBLIB->where("users.users_deleted", 0);
    $DBLIB->where("(users.users_name1 LIKE ? OR users.users_name2 LIKE ? OR users.users_email LIKE ? OR users.users_username LIKE ?)", [
        '%' . $escapedTerm . '%', '%' . $escapedTerm . '%', '%' . $escapedTerm . '%', '%' . $escapedTerm . '%'
    ]);
    $DBLIB->join("userInstances", "users.users_userid=userInstances.users_userid AND userInstances.instances_id = " . (int)$instanceId, "INNER");
    $DBLIB->orderBy("users.users_name1", "ASC");
    $users = $DBLIB->get("users", 5, [
        "users.users_userid", "users.users_name1", "users.users_name2", "users.users_email"
    ]);
    foreach (($users ?: []) as $u) {
        $results[] = [
            'type' => 'user',
            'icon' => 'fas fa-user',
            'badge' => 'info',
            'title' => trim(($u['users_name1'] ?: '') . ' ' . ($u['users_name2'] ?: '')),
            'subtitle' => $u['users_email'] ?: '',
            'url' => '/user.php?id=' . $u['users_userid']
        ];
    }
}

finish(true, null, ["results" => $results, "total" => count($results)]);
