<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("DOCUMENTS:VIEW") && !$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

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
// and compute expired flag for quotes
$today = date('Y-m-d');
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

    // Add expired flag for quotes with valid_until in the past
    $d['is_expired'] = false;
    if (!empty($d['valid_until']) && $d['valid_until'] < $today
        && !in_array($d['status'], ['accepted', 'rejected', 'cancelled', 'expired'])) {
        $d['is_expired'] = true;
    }

    // Versionsinformationen fuer Angebote aus document_exports laden
    $d['version'] = null;
    $d['document_exports_id'] = null;
    $d['has_versions'] = false;
    $d['version_count'] = 0;
    if ($d['doc_type'] === 'quote' && !empty($d['document_exports_id'])) {
        $DBLIB->where('id', $d['document_exports_id']);
        $exportInfo = $DBLIB->getOne('document_exports', [
            'id', 'document_exports_version', 'document_exports_parentVersionId'
        ]);
        if ($exportInfo) {
            $d['version'] = (int)($exportInfo['document_exports_version'] ?? 1);
            $d['document_exports_id'] = (int)$exportInfo['id'];

            // Pruefen ob weitere Versionen existieren
            $parentId = !empty($exportInfo['document_exports_parentVersionId'])
                ? (int)$exportInfo['document_exports_parentVersionId']
                : (int)$exportInfo['id'];
            $DBLIB->where('document_exports_parentVersionId', $parentId);
            $childCount = (int)$DBLIB->getValue('document_exports', 'count(*)');
            $d['has_versions'] = $childCount > 0;
            $d['version_count'] = $childCount + 1; // +1 fuer das Original
            $d['version_parent_id'] = $parentId;
        }
    } elseif ($d['doc_type'] === 'quote' && !empty($d['doc_number'])) {
        // Fallback: document_exports ueber doc_number suchen
        $DBLIB->where('instances_id', $instanceId);
        $DBLIB->where('doc_number', $d['doc_number']);
        $DBLIB->where('type', 'quote');
        $DBLIB->orderBy('document_exports_version', 'DESC');
        $exportInfo = $DBLIB->getOne('document_exports', [
            'id', 'document_exports_version', 'document_exports_parentVersionId'
        ]);
        if ($exportInfo) {
            $d['version'] = (int)($exportInfo['document_exports_version'] ?? 1);
            $d['document_exports_id'] = (int)$exportInfo['id'];
        }
    }
}
unset($d);

finish(true, null, ["documents" => $docs]);
