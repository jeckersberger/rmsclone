<?php
/**
 * Kundenhistorie API
 *
 * Liefert alle Projekte, Dokumente (Angebote/Rechnungen/Gutschriften),
 * Mahnungen und Umsatzsummen fuer einen einzelnen Kunden.
 */
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$clientId = filter_var($_POST['clients_id'] ?? 0, FILTER_VALIDATE_INT);
if (!$clientId || $clientId <= 0) finish(false, ["code" => "INVALID", "message" => "Valid clients_id required"]);

$instanceId = $AUTH->data['instance']['instances_id'];

// 1) Client-Stammdaten (with instance isolation)
$DBLIB->where('clients_id', $clientId);
$DBLIB->where('instances_id', $instanceId);
$client = $DBLIB->getOne('clients');
if (!$client) finish(false, ["code" => "NOT_FOUND", "message" => "Client not found"]);

// 2) Alle Projekte des Kunden
$DBLIB->where('clients_id', $clientId);
$DBLIB->where('instances_id', $instanceId);
$DBLIB->where('projects_deleted', 0);
$DBLIB->orderBy('projects_dates_use_start', 'DESC');
$DBLIB->join('projectsStatuses', 'projects.projectsStatuses_id = projectsStatuses.projectsStatuses_id', 'LEFT');
$projects = $DBLIB->get('projects', null, [
    'projects.projects_id', 'projects.projects_name',
    'projects.projects_dates_use_start', 'projects.projects_dates_use_end',
    'projects.projects_dates_deliver_start', 'projects.projects_dates_deliver_end',
    'projects.projects_archived',
    'projectsStatuses.projectsStatuses_name', 'projectsStatuses.projectsStatuses_backgroundColour',
    'projectsStatuses.projectsStatuses_foregroundColour',
]) ?: [];

// 3) Dokumente (Rechnungen, Angebote, Gutschriften)
$documents = [];
if ($this->hasTable ?? true) { // document_lifecycle might not exist yet
    $sql = "SELECT dl.*, p.projects_name
            FROM document_lifecycle dl
            LEFT JOIN projects p ON dl.projects_id = p.projects_id
            WHERE dl.instances_id = ? AND p.clients_id = ?
            ORDER BY dl.created_at DESC";
    $documents = $DBLIB->rawQuery($sql, [$instanceId, $clientId]) ?: [];
}

// 4) Umsatz-Zusammenfassung
$totalRevenue = 0;
$totalPaid = 0;
$totalOutstanding = 0;
foreach ($documents as $doc) {
    if ($doc['doc_type'] === 'invoice') {
        $totalRevenue += (float)$doc['gross_amount'];
        $totalPaid += (float)$doc['paid_amount'];
    }
}
$totalOutstanding = $totalRevenue - $totalPaid;

// 5) Mahnhistorie
$dunnings = [];
$sql = "SELECT dh.*, dl.doc_number, dl.gross_amount
        FROM dunning_history dh
        JOIN document_lifecycle dl ON dh.document_lifecycle_id = dl.id
        JOIN projects p ON dl.projects_id = p.projects_id
        WHERE dh.instances_id = ? AND p.clients_id = ?
        ORDER BY dh.dunning_date DESC";
$dunnings = $DBLIB->rawQuery($sql, [$instanceId, $clientId]) ?: [];

// 6) Kontakte
$DBLIB->where('clients_id', $clientId);
$DBLIB->where('deleted', 0);
$contacts = $DBLIB->get('client_contacts') ?: [];

finish(true, null, [
    'client' => $client,
    'projects' => $projects,
    'project_count' => count($projects),
    'documents' => $documents,
    'dunnings' => $dunnings,
    'contacts' => $contacts,
    'summary' => [
        'total_revenue' => round($totalRevenue, 2),
        'total_paid' => round($totalPaid, 2),
        'total_outstanding' => round($totalOutstanding, 2),
        'first_project' => !empty($projects) ? end($projects)['projects_dates_use_start'] : null,
        'last_project' => !empty($projects) ? $projects[0]['projects_dates_use_start'] : null,
    ],
]);
