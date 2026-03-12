<?php
/**
 * Clone/Template a Project
 * Creates a new project copying name, description, type, client, location,
 * and optionally the assigned assets from the source project.
 */
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->instancePermissionCheck("PROJECTS:CREATE")) finish(false, ["code" => "PERMISSIONS"]);
if (!isset($_POST['source_project_id'])) finish(false, ["code" => "MISSING_PARAM", "message" => "source_project_id required"]);

$sourceId = (int)$_POST['source_project_id'];
if (!$bCMS->instanceHasProjectCapacity($AUTH->data['instance']['instances_id'])) {
    finish(false, ["code" => "PROJECT-LIMIT-REACHED", "message" => "Project limit reached"]);
}

// Load source project
$DBLIB->where("projects.instances_id", $AUTH->data['instance']['instances_id']);
$DBLIB->where("projects.projects_deleted", 0);
$DBLIB->where("projects.projects_id", $sourceId);
$source = $DBLIB->getOne("projects");
if (!$source) finish(false, ["code" => "NOT_FOUND", "message" => "Source project not found"]);

// Get initial status
$DBLIB->where("instances_id", $AUTH->data['instance']['instances_id']);
$DBLIB->where("projectsStatuses_deleted", 0);
$DBLIB->orderBy("projectsStatuses_rank", "ASC");
$initialStatus = $DBLIB->getValue("projectsStatuses", "projectsStatuses_id", 1);

$newName = isset($_POST['new_name']) && trim($_POST['new_name']) !== ''
    ? trim($_POST['new_name'])
    : $source['projects_name'] . ' (Kopie)';

$newProject = $DBLIB->insert("projects", [
    "projects_name" => $newName,
    "instances_id" => $AUTH->data['instance']['instances_id'],
    "projects_description" => $source['projects_description'],
    "projects_created" => date('Y-m-d H:i:s'),
    "projects_manager" => $AUTH->data['users_userid'],
    "projectsTypes_id" => $source['projectsTypes_id'],
    "projectsStatuses_id" => $initialStatus,
    "clients_id" => $source['clients_id'],
    "locations_id" => $source['locations_id'],
    "projects_invoiceNotes" => $source['projects_invoiceNotes'],
    "projects_deliveryNotes" => $source['projects_deliveryNotes'],
    // Dates are NOT copied - user sets new dates
    "projects_dates_use_start" => isset($_POST['dates_start']) ? date("Y-m-d H:i:s", strtotime($_POST['dates_start'])) : null,
    "projects_dates_use_end" => isset($_POST['dates_end']) ? date("Y-m-d H:i:s", strtotime($_POST['dates_end'])) : null,
]);

if (!$newProject) finish(false, ["code" => "CREATE-FAIL", "message" => "Could not create cloned project"]);

$bCMS->auditLog("INSERT", "projects", "Cloned from project #" . $sourceId, $AUTH->data['users_userid'], null, $newProject);

// Optionally clone asset assignments
if (isset($_POST['clone_assets']) && $_POST['clone_assets'] == '1') {
    $DBLIB->where("projects_id", $sourceId);
    $DBLIB->where("assetsAssignments_deleted", 0);
    $sourceAssets = $DBLIB->get("assetsAssignments", null, [
        "assets_id", "assetsAssignments_customPrice", "assetsAssignments_discount", "assetsAssignments_comment"
    ]);

    foreach ($sourceAssets as $asset) {
        $DBLIB->insert("assetsAssignments", [
            "projects_id" => $newProject,
            "assets_id" => $asset['assets_id'],
            "assetsAssignments_customPrice" => $asset['assetsAssignments_customPrice'],
            "assetsAssignments_discount" => $asset['assetsAssignments_discount'],
            "assetsAssignments_comment" => $asset['assetsAssignments_comment'],
            "assetsAssignments_timestamp" => date("Y-m-d H:i:s"),
        ]);
    }
}

// Optionally clone crew assignments
if (isset($_POST['clone_crew']) && $_POST['clone_crew'] == '1') {
    $DBLIB->where("projects_id", $sourceId);
    $DBLIB->where("crewAssignments_deleted", 0);
    $sourceCrew = $DBLIB->get("crewAssignments", null, [
        "users_userid", "crewAssignments_role", "crewAssignments_comment", "crewAssignments_rank"
    ]);

    foreach ($sourceCrew as $crew) {
        $DBLIB->insert("crewAssignments", [
            "projects_id" => $newProject,
            "users_userid" => $crew['users_userid'],
            "crewAssignments_role" => $crew['crewAssignments_role'],
            "crewAssignments_comment" => $crew['crewAssignments_comment'],
            "crewAssignments_rank" => $crew['crewAssignments_rank'],
        ]);
    }
}

finish(true, null, ["projects_id" => $newProject]);
