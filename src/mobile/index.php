<?php
require_once __DIR__ . '/../common/headSecure.php';

$PAGEDATA['pageConfig'] = ['TITLE' => 'Mobile', 'BREADCRUMB' => false, 'NOMENU' => true];

// Lade Grunddaten für Quick-Access
if ($AUTH->data['instance']) {
    $instanceId = $AUTH->data['instance']['instances_id'];

    // Aktive Projekte
    $DBLIB->where("projects.instances_id", $instanceId);
    $DBLIB->where("projects.projects_deleted", 0);
    $DBLIB->where("projects.projects_archived", 0);
    $DBLIB->join("clients", "projects.clients_id=clients.clients_id", "LEFT");
    $DBLIB->join("projectsStatuses", "projects.projectsStatuses_id=projectsStatuses.projectsStatuses_id", "LEFT");
    $DBLIB->orderBy("projects.projects_dates_use_start", "DESC");
    $PAGEDATA['recentProjects'] = $DBLIB->get("projects", 20, [
        "projects.projects_id", "projects.projects_name",
        "projects.projects_dates_use_start", "projects.projects_dates_use_end",
        "clients.clients_name",
        "projectsStatuses.projectsStatuses_name", "projectsStatuses.projectsStatuses_backgroundColour"
    ]) ?: [];

    // Kunden
    $DBLIB->where("instances_id", $instanceId);
    $DBLIB->where("clients_deleted", 0);
    $DBLIB->orderBy("clients_name", "ASC");
    $PAGEDATA['clients'] = $DBLIB->get("clients", null, ["clients_id", "clients_name", "clients_email", "clients_phone"]) ?: [];

    // Projekt-Typen
    $DBLIB->where("projectsTypes_deleted", 0);
    $DBLIB->where("instances_id", $instanceId);
    $DBLIB->orderBy("projectsTypes_name", "ASC");
    $PAGEDATA['projectTypes'] = $DBLIB->get("projectsTypes") ?: [];

    // Projekt-Status
    $DBLIB->where("instances_id", $instanceId);
    $DBLIB->where("projectsStatuses_deleted", 0);
    $DBLIB->orderBy("projectsStatuses_rank", "ASC");
    $PAGEDATA['projectStatuses'] = $DBLIB->get("projectsStatuses", null, [
        "projectsStatuses_id", "projectsStatuses_name"
    ]) ?: [];
}

echo $TWIG->render('mobile/mobile_app.twig', $PAGEDATA);
