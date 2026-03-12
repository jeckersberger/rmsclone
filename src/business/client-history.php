<?php
require_once __DIR__ . '/../common/headSecure.php';

$PAGEDATA['pageConfig'] = ["TITLE" => "Kundenhistorie", "BREADCRUMB" => false];

if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) die($TWIG->render('404.twig', $PAGEDATA));

// Client ID from URL
$clientId = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
if (!$clientId) die($TWIG->render('404.twig', $PAGEDATA));

$instanceId = $AUTH->data['instance']['instances_id'];

// Client data
$DBLIB->where('clients_id', $clientId);
$client = $DBLIB->getOne('clients');
if (!$client) die($TWIG->render('404.twig', $PAGEDATA));
$PAGEDATA['client'] = $client;

// All projects for this client
$DBLIB->where('clients_id', $clientId);
$DBLIB->where('instances_id', $instanceId);
$DBLIB->where('projects_deleted', 0);
$DBLIB->orderBy('projects_dates_use_start', 'DESC');
$DBLIB->join('projectsStatuses', 'projects.projectsStatuses_id = projectsStatuses.projectsStatuses_id', 'LEFT');
$PAGEDATA['projects'] = $DBLIB->get('projects', null, [
    'projects.projects_id', 'projects.projects_name',
    'projects.projects_dates_use_start', 'projects.projects_dates_use_end',
    'projects.projects_archived',
    'projectsStatuses.projectsStatuses_name', 'projectsStatuses.projectsStatuses_backgroundColour',
    'projectsStatuses.projectsStatuses_foregroundColour',
]) ?: [];

// Documents (invoices, quotes)
$sql = "SELECT dl.*
        FROM document_lifecycle dl
        LEFT JOIN projects p ON dl.projects_id = p.projects_id
        WHERE dl.instances_id = ? AND p.clients_id = ?
        ORDER BY dl.created_at DESC";
$PAGEDATA['documents'] = $DBLIB->rawQuery($sql, [$instanceId, $clientId]) ?: [];

// Revenue summary
$totalRevenue = 0;
$totalPaid = 0;
foreach ($PAGEDATA['documents'] as $doc) {
    if ($doc['doc_type'] === 'invoice') {
        $totalRevenue += (float)$doc['gross_amount'];
        $totalPaid += (float)$doc['paid_amount'];
    }
}
$PAGEDATA['summary'] = [
    'total_revenue' => $totalRevenue,
    'total_paid' => $totalPaid,
    'total_outstanding' => $totalRevenue - $totalPaid,
    'project_count' => count($PAGEDATA['projects']),
];

// Contacts
$DBLIB->where('clients_id', $clientId);
$DBLIB->where('deleted', 0);
$PAGEDATA['contacts'] = $DBLIB->get('client_contacts') ?: [];

echo $TWIG->render('business/client-history.twig', $PAGEDATA);
