<?php
/**
 * Get Smart Lookup Job Status
 *
 * GET /api/assets/smart_lookup_status.php?jobId=uuid
 * Output: {jobId, status, itemsCount, completedCount, progress, results?, error?}
 */
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->hasPermission('ASSETS:CREATE')) {
    finish(false, ['code' => 'FORBIDDEN', 'message' => 'Insufficient permissions']);
}

$jobId = $_GET['jobId'] ?? null;
if (!$jobId) {
    finish(false, ['code' => 'INVALID_INPUT', 'message' => 'jobId parameter is required']);
}

$instanceId = $AUTH->data['instance']['instances_id'];

try {
    $aiRequestHandler = new AiRequestHandler($DBLIB, new AiProviderRegistry($DBLIB), new AiUsageTracker($DBLIB));
    $service = new SmartAssetLookupService($DBLIB, $aiRequestHandler);

    $status = $service->getBulkLookupStatus($jobId, $instanceId);

    if (!$status) {
        finish(false, ['code' => 'JOB_NOT_FOUND', 'message' => 'Job not found']);
    }

    finish(true, null, $status);
} catch (Exception $e) {
    error_log("[SmartLookupStatus] Error: {$e->getMessage()}");
    finish(false, ['code' => 'STATUS_CHECK_FAILED', 'message' => $e->getMessage()]);
}
