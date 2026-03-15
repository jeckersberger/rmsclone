<?php
/**
 * Dunning Queue API - Approval Workflow Endpoint
 *
 * Handles dunning approval queue operations:
 *   - generate: Scan for overdue invoices and create proposals
 *   - list: Get queue entries (with optional status filter)
 *   - approve: Approve a single proposal
 *   - reject: Reject a single proposal
 *   - approve_send: Approve, generate PDF, and send email in one step
 *   - bulk_approve: Approve multiple proposals at once
 *   - stats: Get queue statistics
 *
 * POST parameters:
 *   action (required): One of the above
 *   status (optional): For list action - 'pending', 'approved', 'rejected', 'sent', or 'all'
 *   queue_id (required for: approve, reject, approve_send)
 *   queue_ids (required for: bulk_approve - comma-separated)
 *   notes (optional for: approve, reject - additional notes)
 */

require_once __DIR__ . '/../apiHeadSecure.php';

// Permission check
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:VIEW")) {
    finish(false, ["code" => "PERMISSIONS", "message" => "Insufficient permissions"]);
}

$instanceId = $AUTH->data['instance']['instances_id'];
$userId = $AUTH->data['users_userid'];
$action = trim($_POST['action'] ?? '');

if (empty($action)) {
    finish(false, ["code" => "INVALID", "message" => "Action parameter required"]);
}

$queueService = new DunningQueueService($DBLIB);

try {
    switch ($action) {
        case 'generate':
            return handleGenerate($queueService, $instanceId);

        case 'list':
            return handleList($queueService, $instanceId);

        case 'approve':
            return handleApprove($queueService, $userId);

        case 'reject':
            return handleReject($queueService, $userId);

        case 'approve_send':
            return handleApproveSend($queueService, $userId);

        case 'bulk_approve':
            return handleBulkApprove($queueService, $userId);

        case 'stats':
            return handleStats($queueService, $instanceId);

        default:
            finish(false, ["code" => "INVALID_ACTION", "message" => "Unknown action: $action"]);
    }
} catch (\Exception $e) {
    error_log('Dunning queue API error: ' . $e->getMessage());
    finish(false, ["code" => "ERROR", "message" => $e->getMessage()]);
}

/**
 * Generate new dunning proposals
 */
function handleGenerate($queueService, $instanceId)
{
    $result = $queueService->generateProposals($instanceId);

    finish(true, null, [
        "created" => $result['created'],
        "skipped" => $result['skipped'],
        "details" => $result['details'],
        "message" => "Generated {$result['created']} new proposals",
    ]);
}

/**
 * List queue entries
 */
function handleList($queueService, $instanceId)
{
    $status = trim($_GET['status'] ?? $_POST['status'] ?? 'pending');
    $queue = $queueService->getQueue($instanceId, $status);

    // Format for display
    $formatted = [];
    foreach ($queue as $entry) {
        $formatted[] = [
            'id' => (int)$entry['id'],
            'invoice_number' => $entry['doc_number'],
            'client_name' => $entry['clients_name'],
            'client_email' => $entry['clients_email'],
            'project_name' => $entry['projects_name'],
            'invoice_amount' => (float)$entry['gross_amount'],
            'days_overdue' => (int)$entry['days_overdue'],
            'dunning_level' => (int)$entry['dunning_level'],
            'fee_amount' => (float)$entry['fee_amount'],
            'interest_amount' => (float)$entry['interest_amount'],
            'total_with_fees' => (float)$entry['total_with_fees'],
            'status' => $entry['status'],
            'proposed_at' => $entry['proposed_at'],
            'approved_at' => $entry['approved_at'],
            'approved_by' => $entry['approved_by'],
            'notes' => $entry['notes'],
        ];
    }

    finish(true, null, [
        "count" => count($formatted),
        "entries" => $formatted,
        "filter" => ["status" => $status],
    ]);
}

/**
 * Approve a single proposal
 */
function handleApprove($queueService, $userId)
{
    $queueId = (int)($_POST['queue_id'] ?? 0);
    if ($queueId <= 0) {
        finish(false, ["code" => "INVALID", "message" => "queue_id required"]);
    }

    $notes = trim($_POST['notes'] ?? '');
    $success = $queueService->approve($queueId, $userId, !empty($notes) ? $notes : null);

    if (!$success) {
        finish(false, ["code" => "NOT_FOUND", "message" => "Queue entry not found"]);
    }

    finish(true, null, [
        "queue_id" => $queueId,
        "status" => "approved",
        "message" => "Proposal approved successfully",
    ]);
}

/**
 * Reject a proposal
 */
function handleReject($queueService, $userId)
{
    $queueId = (int)($_POST['queue_id'] ?? 0);
    if ($queueId <= 0) {
        finish(false, ["code" => "INVALID", "message" => "queue_id required"]);
    }

    $notes = trim($_POST['notes'] ?? '');
    $success = $queueService->reject($queueId, $userId, !empty($notes) ? $notes : null);

    if (!$success) {
        finish(false, ["code" => "NOT_FOUND", "message" => "Queue entry not found"]);
    }

    finish(true, null, [
        "queue_id" => $queueId,
        "status" => "rejected",
        "message" => "Proposal rejected",
    ]);
}

/**
 * Approve proposal and send email with PDF in one operation
 */
function handleApproveSend($queueService, $userId)
{
    $queueId = (int)($_POST['queue_id'] ?? 0);
    if ($queueId <= 0) {
        finish(false, ["code" => "INVALID", "message" => "queue_id required"]);
    }

    $notes = trim($_POST['notes'] ?? '');
    $result = $queueService->approveAndSend($queueId, $userId, !empty($notes) ? $notes : null);

    if (!$result['success']) {
        finish(false, ["code" => "ERROR", "message" => $result['message']]);
    }

    finish(true, null, [
        "queue_id" => $queueId,
        "dunning_id" => $result['dunning_id'],
        "pdf_path" => $result['pdf_path'],
        "email_sent" => $result['email_sent'],
        "message" => $result['message'],
    ]);
}

/**
 * Approve multiple proposals at once
 */
function handleBulkApprove($queueService, $userId)
{
    $queueIdsStr = trim($_POST['queue_ids'] ?? '');
    if (empty($queueIdsStr)) {
        finish(false, ["code" => "INVALID", "message" => "queue_ids required (comma-separated)"]);
    }

    $queueIds = array_filter(array_map('intval', explode(',', $queueIdsStr)));
    if (empty($queueIds)) {
        finish(false, ["code" => "INVALID", "message" => "Invalid queue_ids format"]);
    }

    $result = $queueService->bulkApprove($queueIds, $userId);

    $response = [
        "approved" => $result['approved'],
        "failed" => $result['failed'],
        "message" => "Approved {$result['approved']} proposals",
    ];

    if (!empty($result['failures'])) {
        $response['failures'] = $result['failures'];
    }

    finish(true, null, $response);
}

/**
 * Get queue statistics
 */
function handleStats($queueService, $instanceId)
{
    $stats = $queueService->getStats($instanceId);

    finish(true, null, [
        "pending" => $stats['pending'],
        "approved" => $stats['approved'],
        "sent" => $stats['sent'],
        "rejected" => $stats['rejected'],
        "total_pending_amount" => $stats['total_pending_amount'],
        "total_all" => $stats['pending'] + $stats['approved'] + $stats['sent'] + $stats['rejected'],
    ]);
}
