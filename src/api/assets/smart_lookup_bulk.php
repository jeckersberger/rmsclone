<?php
/**
 * Smart Asset Bulk Lookup
 *
 * POST /api/assets/smart_lookup_bulk.php
 * Input: {items: [{manufacturer, model, ean?}, ...]}
 * Output: {jobId, itemsCount} - immediate response, poll for results
 */
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->hasPermission('ASSETS:CREATE')) {
    finish(false, ['code' => 'FORBIDDEN', 'message' => 'Insufficient permissions']);
}

$payload = json_decode(file_get_contents('php://input'), true) ?? [];
$items = $payload['items'] ?? [];

if (!is_array($items) || count($items) === 0) {
    finish(false, ['code' => 'INVALID_INPUT', 'message' => 'items array is required and must not be empty']);
}

if (count($items) > 100) {
    finish(false, ['code' => 'TOO_MANY_ITEMS', 'message' => 'Maximum 100 items per request']);
}

$instanceId = $AUTH->data['instance']['instances_id'];

try {
    $aiRequestHandler = new AiRequestHandler($DBLIB, new AiProviderRegistry($DBLIB), new AiUsageTracker($DBLIB));
    $service = new SmartAssetLookupService($DBLIB, $aiRequestHandler);

    // Start bulk lookup job (returns immediately)
    $jobInfo = $service->bulkLookup($items, $instanceId);

    finish(true, null, $jobInfo);
} catch (Exception $e) {
    error_log("[SmartLookupBulk] Error: {$e->getMessage()}");
    finish(false, ['code' => 'BULK_LOOKUP_FAILED', 'message' => $e->getMessage()]);
}
