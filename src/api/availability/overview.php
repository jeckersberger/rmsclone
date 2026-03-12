<?php
/**
 * Equipment Availability Overview API
 * Returns all asset assignments with date ranges for a visual availability calendar.
 * Shows which assets are booked when across the entire inventory.
 */
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];

// Date range: default to current month +-1 month
$dateFrom = isset($_POST['date_from']) ? date('Y-m-d', strtotime($_POST['date_from'])) : date('Y-m-d', strtotime('-1 month'));
$dateTo = isset($_POST['date_to']) ? date('Y-m-d', strtotime($_POST['date_to'])) : date('Y-m-d', strtotime('+2 months'));

// Optional filter by asset type
$assetTypeFilter = isset($_POST['asset_type_id']) ? (int)$_POST['asset_type_id'] : null;

// Get all asset types for filter dropdown
$DBLIB->where("assetTypes.instances_id", $instanceId);
$DBLIB->where("assetTypes.assetTypes_deleted", 0);
$DBLIB->join("assetCategories", "assetTypes.assetCategories_id=assetCategories.assetCategories_id", "LEFT");
$DBLIB->orderBy("assetCategories.assetCategories_rank", "ASC");
$DBLIB->orderBy("assetTypes.assetTypes_name", "ASC");
$assetTypes = $DBLIB->get("assetTypes", null, [
    "assetTypes.assetTypes_id", "assetTypes.assetTypes_name",
    "assetCategories.assetCategories_name"
]);

// Get assignments with project dates
$DBLIB->where("projects.instances_id", $instanceId);
$DBLIB->where("projects.projects_deleted", 0);
$DBLIB->where("projects.projects_archived", 0);
$DBLIB->where("assetsAssignments.assetsAssignments_deleted", 0);
$DBLIB->where("assets.assets_deleted", 0);
// Filter: project dates overlap with requested range (parameterized)
$dateToEnd = $dateTo . ' 23:59:59';
$dateFromStart = $dateFrom . ' 00:00:00';
$DBLIB->where("(
    (projects.projects_dates_use_start IS NOT NULL AND projects.projects_dates_use_end IS NOT NULL
     AND projects.projects_dates_use_start <= ?
     AND projects.projects_dates_use_end >= ?)
    OR
    (projects.projects_dates_deliver_start IS NOT NULL AND projects.projects_dates_deliver_end IS NOT NULL
     AND projects.projects_dates_deliver_start <= ?
     AND projects.projects_dates_deliver_end >= ?)
)", [$dateToEnd, $dateFromStart, $dateToEnd, $dateFromStart]);

if ($assetTypeFilter) {
    $DBLIB->where("assets.assetTypes_id", $assetTypeFilter);
}

$DBLIB->join("projects", "assetsAssignments.projects_id=projects.projects_id", "INNER");
$DBLIB->join("assets", "assetsAssignments.assets_id=assets.assets_id", "INNER");
$DBLIB->join("assetTypes", "assets.assetTypes_id=assetTypes.assetTypes_id", "LEFT");
$DBLIB->join("clients", "projects.clients_id=clients.clients_id", "LEFT");
$DBLIB->join("projectsStatuses", "projects.projectsStatuses_id=projectsStatuses.projectsStatuses_id", "LEFT");
$DBLIB->orderBy("assetTypes.assetTypes_name", "ASC");
$DBLIB->orderBy("assets.assets_tag", "ASC");

$assignments = $DBLIB->get("assetsAssignments", null, [
    "assets.assets_id", "assets.assets_tag",
    "assetTypes.assetTypes_id", "assetTypes.assetTypes_name",
    "projects.projects_id", "projects.projects_name",
    "projects.projects_dates_use_start", "projects.projects_dates_use_end",
    "projects.projects_dates_deliver_start", "projects.projects_dates_deliver_end",
    "clients.clients_name",
    "projectsStatuses.projectsStatuses_name",
    "projectsStatuses.projectsStatuses_backgroundColour"
]);

// Build calendar events
$events = [];
foreach (($assignments ?: []) as $a) {
    $events[] = [
        'asset_id' => $a['assets_id'],
        'asset_tag' => $a['assets_tag'],
        'asset_type' => $a['assetTypes_name'],
        'asset_type_id' => $a['assetTypes_id'],
        'project_id' => $a['projects_id'],
        'project_name' => $a['projects_name'],
        'client_name' => $a['clients_name'],
        'status' => $a['projectsStatuses_name'],
        'color' => $a['projectsStatuses_backgroundColour'] ?: '#007bff',
        'start' => $a['projects_dates_deliver_start'] ?: $a['projects_dates_use_start'],
        'end' => $a['projects_dates_deliver_end'] ?: $a['projects_dates_use_end'],
        'use_start' => $a['projects_dates_use_start'],
        'use_end' => $a['projects_dates_use_end'],
    ];
}

finish(true, null, [
    'events' => $events,
    'asset_types' => $assetTypes ?: [],
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
]);
