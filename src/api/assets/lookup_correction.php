<?php
/**
 * Record Lookup Correction
 *
 * POST /api/assets/lookup_correction.php
 * Input: {cacheId, field, originalValue, correctedValue}
 * Output: {success: bool}
 */
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->hasPermission('ASSETS:CREATE')) {
    finish(false, ['code' => 'FORBIDDEN', 'message' => 'Insufficient permissions']);
}

$cacheId = (int) ($_POST['cacheId'] ?? 0);
$field = trim($_POST['field'] ?? '');
$originalValue = trim($_POST['originalValue'] ?? '');
$correctedValue = trim($_POST['correctedValue'] ?? '');

if (!$cacheId || !$field || !$correctedValue) {
    finish(false, ['code' => 'INVALID_INPUT', 'message' => 'cacheId, field, and correctedValue are required']);
}

$instanceId = $AUTH->data['instance']['instances_id'];
$userId = $AUTH->data['user']['users_id'];

try {
    $aiRequestHandler = new AiRequestHandler($DBLIB, new AiProviderRegistry($DBLIB), new AiUsageTracker($DBLIB));
    $service = new SmartAssetLookupService($DBLIB, $aiRequestHandler);

    $service->recordCorrection($cacheId, $field, $originalValue, $correctedValue, $userId, $instanceId);

    finish(true, null, ['message' => 'Correction recorded']);
} catch (Exception $e) {
    error_log("[LookupCorrection] Error: {$e->getMessage()}");
    finish(false, ['code' => 'CORRECTION_FAILED', 'message' => $e->getMessage()]);
}
