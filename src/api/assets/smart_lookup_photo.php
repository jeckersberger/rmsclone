<?php
/**
 * Smart Asset Lookup from Photo
 *
 * POST /api/assets/smart_lookup_photo.php
 * Input: multipart/form-data with file upload
 * Output: AssetLookupResult extracted from image
 */
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->hasPermission('ASSETS:CREATE')) {
    finish(false, ['code' => 'FORBIDDEN', 'message' => 'Insufficient permissions']);
}

// Check file upload
if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    finish(false, ['code' => 'NO_FILE', 'message' => 'No valid photo file uploaded']);
}

$tmpFile = $_FILES['photo']['tmp_name'];
$fileName = $_FILES['photo']['name'];
$mimeType = mime_content_type($tmpFile) ?: 'image/jpeg';

// Validate image type
if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'])) {
    finish(false, ['code' => 'INVALID_TYPE', 'message' => 'Only image files are supported']);
}

$instanceId = $AUTH->data['instance']['instances_id'];

try {
    $aiRequestHandler = new AiRequestHandler($DBLIB, new AiProviderRegistry($DBLIB), new AiUsageTracker($DBLIB));
    $service = new SmartAssetLookupService($DBLIB, $aiRequestHandler);

    // Perform lookup from image
    $result = $service->lookupFromPhoto($tmpFile, $instanceId);

    // Add suggested rental price
    if ($result->newPrice) {
        $result->suggestedRentalPrice = $service->suggestRentalPrice(
            $result->newPrice,
            $result->category ?? 'Sonstiges',
            $instanceId
        );
    }

    finish(true, null, $result->toArray());
} catch (Exception $e) {
    error_log("[SmartLookupPhoto] Error: {$e->getMessage()}");
    finish(false, ['code' => 'PHOTO_LOOKUP_FAILED', 'message' => $e->getMessage()]);
}
