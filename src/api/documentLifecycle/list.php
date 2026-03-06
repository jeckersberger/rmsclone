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

// Enrich with ZUGFeRD XML file IDs from document_exports
foreach ($docs as &$d) {
    if ($d['doc_type'] === 'invoice' && !empty($d['doc_number'])) {
        $DBLIB->where('instances_id', $instanceId);
        $DBLIB->where('doc_number', $d['doc_number']);
        $DBLIB->where('zugferd_xml_s3files_id IS NOT NULL');
        $export = $DBLIB->getOne('document_exports', ['zugferd_xml_s3files_id']);
        $d['zugferd_s3files_id'] = $export ? $export['zugferd_xml_s3files_id'] : null;
    } else {
        $d['zugferd_s3files_id'] = null;
    }
}
unset($d);

finish(true, null, ["documents" => $docs]);
