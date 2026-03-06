<?php
/**
 * Dashboard Overview API
 * Returns data for the enhanced dashboard: today's projects, overdue items,
 * unpaid invoices, projects without invoices, and upcoming 7-day schedule.
 */
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$today = date('Y-m-d');
$todayStart = $today . ' 00:00:00';
$todayEnd = $today . ' 23:59:59';
$weekEnd = date('Y-m-d', strtotime('+7 days')) . ' 23:59:59';

$response = [];

// ── 1. Today's projects (delivery or use dates overlap today) ──
$DBLIB->where("projects.instances_id", $instanceId);
$DBLIB->where("projects.projects_deleted", 0);
$DBLIB->where("projects.projects_archived", 0);
$DBLIB->where("(
    (projects.projects_dates_use_start <= '$todayEnd' AND projects.projects_dates_use_end >= '$todayStart')
    OR (projects.projects_dates_deliver_start <= '$todayEnd' AND projects.projects_dates_deliver_end >= '$todayStart')
)");
$DBLIB->join("clients", "projects.clients_id=clients.clients_id", "LEFT");
$DBLIB->join("projectsStatuses", "projects.projectsStatuses_id=projectsStatuses.projectsStatuses_id", "LEFT");
$DBLIB->orderBy("projects.projects_dates_use_start", "ASC");
$todayProjects = $DBLIB->get("projects", null, [
    "projects.projects_id", "projects.projects_name",
    "clients.clients_name",
    "projects.projects_dates_use_start", "projects.projects_dates_use_end",
    "projects.projects_dates_deliver_start", "projects.projects_dates_deliver_end",
    "projectsStatuses.projectsStatuses_name",
    "projectsStatuses.projectsStatuses_backgroundColour",
    "projectsStatuses.projectsStatuses_foregroundColour"
]);
$response['today_projects'] = $todayProjects ?: [];

// ── 2. Overdue returns (deliver_end in the past, not archived) ──
$DBLIB->where("projects.instances_id", $instanceId);
$DBLIB->where("projects.projects_deleted", 0);
$DBLIB->where("projects.projects_archived", 0);
$DBLIB->where("projects.projects_dates_deliver_end < '$todayStart'");
$DBLIB->where("projects.projects_dates_deliver_end IS NOT NULL");
$DBLIB->join("clients", "projects.clients_id=clients.clients_id", "LEFT");
$DBLIB->join("projectsStatuses", "projects.projectsStatuses_id=projectsStatuses.projectsStatuses_id", "LEFT");
$DBLIB->orderBy("projects.projects_dates_deliver_end", "ASC");
$overdueReturns = $DBLIB->get("projects", null, [
    "projects.projects_id", "projects.projects_name",
    "clients.clients_name",
    "projects.projects_dates_deliver_end",
    "projectsStatuses.projectsStatuses_name",
    "projectsStatuses.projectsStatuses_backgroundColour"
]);
$response['overdue_returns'] = $overdueReturns ?: [];

// ── 3. Next 7 days schedule ──
$DBLIB->where("projects.instances_id", $instanceId);
$DBLIB->where("projects.projects_deleted", 0);
$DBLIB->where("projects.projects_archived", 0);
$DBLIB->where("(
    (projects.projects_dates_use_start <= '$weekEnd' AND projects.projects_dates_use_end >= '$todayStart')
    OR (projects.projects_dates_deliver_start <= '$weekEnd' AND projects.projects_dates_deliver_end >= '$todayStart')
)");
$DBLIB->join("clients", "projects.clients_id=clients.clients_id", "LEFT");
$DBLIB->join("projectsStatuses", "projects.projectsStatuses_id=projectsStatuses.projectsStatuses_id", "LEFT");
$DBLIB->orderBy("projects.projects_dates_use_start", "ASC");
$upcomingProjects = $DBLIB->get("projects", null, [
    "projects.projects_id", "projects.projects_name",
    "clients.clients_name",
    "projects.projects_dates_use_start", "projects.projects_dates_use_end",
    "projects.projects_dates_deliver_start", "projects.projects_dates_deliver_end",
    "projectsStatuses.projectsStatuses_name",
    "projectsStatuses.projectsStatuses_backgroundColour",
    "projectsStatuses.projectsStatuses_foregroundColour"
]);
$response['upcoming_projects'] = $upcomingProjects ?: [];

// ── 4. Dunning suggestions (overdue invoices not yet dunned) ──
if ($AUTH->instancePermissionCheck("FINANCE:PAYMENTS_LEDGER:VIEW")) {
    // Projects with outstanding balance (payments total > 0) and use_end in the past
    $DBLIB->where("projects.instances_id", $instanceId);
    $DBLIB->where("projects.projects_deleted", 0);
    $DBLIB->where("projectsFinanceCache.projectsFinanceCache_grandTotal > 0");
    $DBLIB->where("projects.projects_dates_use_end < '$todayStart'");
    $DBLIB->where("projects.projects_dates_use_end IS NOT NULL");
    $DBLIB->join("projectsFinanceCache", "projects.projects_id=projectsFinanceCache.projects_id", "INNER");
    $DBLIB->join("clients", "projects.clients_id=clients.clients_id", "LEFT");
    $DBLIB->orderBy("projects.projects_dates_use_end", "ASC");
    $DBLIB->groupBy("projects.projects_id");
    $unpaidProjects = $DBLIB->get("projects", null, [
        "projects.projects_id", "projects.projects_name",
        "clients.clients_name", "clients.clients_email",
        "projects.projects_dates_use_end",
        "projectsFinanceCache.projectsFinanceCache_grandTotal"
    ]);
    $response['unpaid_projects'] = $unpaidProjects ?: [];

    // Dunning suggestions: invoices overdue > 14 days
    $response['dunning_suggestions'] = [];
    foreach ($response['unpaid_projects'] as $proj) {
        $daysOverdue = (int)((time() - strtotime($proj['projects_dates_use_end'])) / 86400);
        if ($daysOverdue > 14) {
            $response['dunning_suggestions'][] = [
                'projects_id' => $proj['projects_id'],
                'projects_name' => $proj['projects_name'],
                'clients_name' => $proj['clients_name'],
                'clients_email' => $proj['clients_email'],
                'days_overdue' => $daysOverdue,
                'outstanding_amount' => $proj['projectsFinanceCache_grandTotal'],
                'suggested_action' => $daysOverdue > 60 ? 'final_dunning' :
                    ($daysOverdue > 30 ? 'second_dunning' :
                    ($daysOverdue > 21 ? 'first_dunning' : 'payment_reminder'))
            ];
        }
    }
} else {
    $response['unpaid_projects'] = [];
    $response['dunning_suggestions'] = [];
}

// ── 5. Quick stats ──
// Active projects count
$DBLIB->where("projects.instances_id", $instanceId);
$DBLIB->where("projects.projects_deleted", 0);
$DBLIB->where("projects.projects_archived", 0);
$response['stats']['active_projects'] = $DBLIB->getValue("projects", "COUNT(*)");

// Projects without client
$DBLIB->where("projects.instances_id", $instanceId);
$DBLIB->where("projects.projects_deleted", 0);
$DBLIB->where("projects.projects_archived", 0);
$DBLIB->where("projects.clients_id IS NULL");
$response['stats']['projects_no_client'] = $DBLIB->getValue("projects", "COUNT(*)");

// ── 6. Expiring quotes (within 7 days) ──
$sevenDaysOut = date('Y-m-d', strtotime('+7 days'));
$DBLIB->where('dl.instances_id', $instanceId);
$DBLIB->where('dl.doc_type', 'quote');
$DBLIB->where('dl.status', 'sent');
$DBLIB->where('dl.valid_until IS NOT NULL');
$DBLIB->where('dl.valid_until', $sevenDaysOut, '<=');
$DBLIB->where('dl.valid_until', $today, '>=');
$DBLIB->join('projects p', 'dl.projects_id=p.projects_id', 'LEFT');
$DBLIB->join('clients c', 'p.clients_id=c.clients_id', 'LEFT');
$DBLIB->orderBy('dl.valid_until', 'ASC');
$expiringQuotes = $DBLIB->get('document_lifecycle dl', null, [
    'dl.id', 'dl.doc_number', 'dl.valid_until', 'dl.gross_amount',
    'p.projects_id', 'p.projects_name', 'c.clients_name'
]) ?: [];

$response['expiring_quotes'] = [];
foreach ($expiringQuotes as $q) {
    $daysLeft = max(0, (int)((strtotime($q['valid_until']) - time()) / 86400));
    $response['expiring_quotes'][] = [
        'doc_number' => $q['doc_number'],
        'projects_id' => $q['projects_id'],
        'projects_name' => $q['projects_name'],
        'clients_name' => $q['clients_name'],
        'valid_until' => $q['valid_until'],
        'days_left' => $daysLeft,
        'gross_amount' => $q['gross_amount'],
    ];
}

// ── 7. Pending approval links ──
$DBLIB->where('instances_id', $instanceId);
$DBLIB->where('status', 'pending');
$DBLIB->where("(expires_at IS NULL OR expires_at >= '{$today}')");
$DBLIB->orderBy('created_at', 'DESC');
$pendingApprovals = $DBLIB->get('quote_approval_tokens', 5, [
    'id', 'doc_number', 'client_name', 'created_at', 'expires_at', 'projects_id'
]) ?: [];
$response['pending_approvals'] = $pendingApprovals;

finish(true, null, $response);
