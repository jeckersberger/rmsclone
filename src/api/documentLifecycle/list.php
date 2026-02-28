<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : null;

$svc = new DocumentLifecycleService($DBLIB);

if ($projectId) {
    $docs = $svc->getProjectDocuments($instanceId, $projectId);
} else {
    // All documents for instance
    $DBLIB->where('dl.instances_id', $instanceId);
    $DBLIB->join('projects p', 'dl.projects_id=p.projects_id', 'LEFT');
    $DBLIB->join('clients c', 'p.clients_id=c.clients_id', 'LEFT');
    $DBLIB->orderBy('dl.created_at', 'DESC');
    $docs = $DBLIB->get('document_lifecycle dl', 100, [
        'dl.*', 'p.projects_name', 'c.clients_name'
    ]) ?: [];
}

finish(true, null, ["documents" => $docs]);
