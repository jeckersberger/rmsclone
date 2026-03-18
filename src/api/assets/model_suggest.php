<?php
/**
 * Model Autocomplete for Manufacturer
 *
 * GET /api/assets/model_suggest.php?manufacturer=name&q=query
 * Output: [{id, name}, ...]
 */
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->hasPermission('ASSETS:VIEW')) {
    finish(false, ['code' => 'FORBIDDEN', 'message' => 'Insufficient permissions']);
}

$manufacturer = trim($_GET['manufacturer'] ?? '');
$query = trim($_GET['q'] ?? '');

if (!$manufacturer || strlen($query) < 2) {
    finish(true, null, []);
}

$instanceId = $AUTH->data['instance']['instances_id'];

try {
    $aiRequestHandler = new AiRequestHandler($DBLIB, new AiProviderRegistry($DBLIB), new AiUsageTracker($DBLIB));
    $service = new SmartAssetLookupService($DBLIB, $aiRequestHandler);

    $suggestions = $service->getModelSuggestions($manufacturer, $query, $instanceId);

    finish(true, null, $suggestions);
} catch (Exception $e) {
    error_log("[ModelSuggest] Error: {$e->getMessage()}");
    finish(false, ['code' => 'SUGGEST_FAILED', 'message' => $e->getMessage()]);
}
