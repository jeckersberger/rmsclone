<?php
/**
 * Smart Asset Lookup API
 *
 * POST /api/assets/smart_lookup.php
 * Input: {manufacturer, model, ean?}
 * Output: AssetLookupResult with confidence indicators
 */
require_once __DIR__ . '/../apiHeadSecure.php';

// Verify permission
if (!$AUTH->hasPermission('ASSETS:CREATE')) {
    finish(false, ['code' => 'FORBIDDEN', 'message' => 'Insufficient permissions']);
}

$manufacturer = trim($_POST['manufacturer'] ?? '');
$model = trim($_POST['model'] ?? '');
$ean = trim($_POST['ean'] ?? null);
$instanceId = $AUTH->data['instance']['instances_id'];

if (!$manufacturer || !$model) {
    finish(false, ['code' => 'INVALID_INPUT', 'message' => 'manufacturer and model are required']);
}

try {
    // Initialize service
    $aiRequestHandler = new AiRequestHandler($DBLIB, new AiProviderRegistry($DBLIB), new AiUsageTracker($DBLIB));
    $service = new SmartAssetLookupService($DBLIB, $aiRequestHandler);

    // Perform lookup
    $result = $service->lookup($manufacturer, $model, $ean, $instanceId);

    // Add suggested rental price if we have new price
    if ($result->newPrice) {
        $result->suggestedRentalPrice = $service->suggestRentalPrice(
            $result->newPrice,
            $result->category ?? 'Sonstiges',
            $instanceId
        );
    }

    finish(true, null, $result->toArray());
} catch (Exception $e) {
    error_log("[SmartLookup] Error: {$e->getMessage()}");
    finish(false, ['code' => 'LOOKUP_FAILED', 'message' => $e->getMessage()]);
}
