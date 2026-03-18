<?php
/**
 * Smart Asset Creator (I9) - Usage Examples
 *
 * This file demonstrates how to use the SmartAssetLookupService
 * in different scenarios. Copy and adapt patterns as needed.
 */

// ============================================================================
// SETUP: Initialize the service
// ============================================================================

// Assuming you have $DBLIB (MeekroDB instance) available
$aiRequestHandler = new AiRequestHandler(
    $DBLIB,
    new AiProviderRegistry($DBLIB),
    new AiUsageTracker($DBLIB)
);

$smartAssetService = new SmartAssetLookupService($DBLIB, $aiRequestHandler);

// ============================================================================
// EXAMPLE 1: Simple lookup by manufacturer + model
// ============================================================================

echo "=== EXAMPLE 1: Simple Lookup ===\n";

$result = $smartAssetService->lookup(
    manufacturer: 'Martin',
    model: 'MAC 300 Spot',
    instanceId: 1
);

echo "Name: " . $result->name . "\n";
echo "Manufacturer: " . $result->manufacturer . "\n";
echo "Model: " . $result->model . "\n";
echo "Weight: " . $result->weightKg . " kg\n";
echo "Power: " . $result->powerWatts . " W\n";
echo "Price: " . $result->newPrice . " EUR\n";
echo "Confidence: " . ($result->overallConfidence * 100) . "%\n";
echo "Category: " . $result->category . "\n";
echo "\n";

// ============================================================================
// EXAMPLE 2: Check per-field confidence and sources
// ============================================================================

echo "=== EXAMPLE 2: Per-Field Analysis ===\n";

$fieldsToCheck = ['name', 'powerWatts', 'weight_kg', 'newPrice'];

foreach ($fieldsToCheck as $field) {
    $confidence = $result->getFieldConfidence($field);
    $source = $result->getFieldSource($field);
    $sourceEmoji = $source === 'ai' ? '🤖' : ($source === 'web' ? '🌐' : '✅');

    echo "{$field}: {$sourceEmoji} {$source} - {$confidence}% confidence\n";
}
echo "\n";

// ============================================================================
// EXAMPLE 3: Suggest rental price based on new price and category
// ============================================================================

echo "=== EXAMPLE 3: Rental Price Suggestion ===\n";

if ($result->newPrice) {
    $suggestedRental = $smartAssetService->suggestRentalPrice(
        newPrice: $result->newPrice,
        category: $result->category ?? 'Beleuchtung',
        instanceId: 1
    );

    echo "New Price: {$result->newPrice} EUR\n";
    echo "Category: " . ($result->category ?? 'Beleuchtung') . "\n";
    echo "Suggested Rental Price: {$suggestedRental} EUR/day\n";
}
echo "\n";

// ============================================================================
// EXAMPLE 4: Suggest category for new asset
// ============================================================================

echo "=== EXAMPLE 4: Category Suggestion ===\n";

$categorySuggestion = $smartAssetService->suggestCategory(
    name: 'Martin MAC 300 Spot',
    description: 'Moving head spot light with 300W discharge lamp',
    instanceId: 1
);

echo "Suggested Category: " . ($categorySuggestion['category'] ?? 'Not matched') . "\n";
if ($categorySuggestion['categoryId']) {
    echo "Category ID: " . $categorySuggestion['categoryId'] . "\n";
}
echo "Other suggestions:\n";
foreach ($categorySuggestion['suggestions'] as $cat) {
    echo "  - " . $cat['assetCategories_name'] . "\n";
}
echo "\n";

// ============================================================================
// EXAMPLE 5: Record user correction (for learning loop)
// ============================================================================

echo "=== EXAMPLE 5: User Correction ===\n";

// User noticed that weight_kg was incorrect in cache
$smartAssetService->recordCorrection(
    cacheId: 42,              // The lookup cache ID
    field: 'weight_kg',       // Field that was wrong
    originalValue: '25.5',    // What AI said
    correctedValue: '26.0',   // What it should be
    userId: 5,                // Who corrected it
    instanceId: 1
);

echo "Correction recorded: weight_kg (25.5 -> 26.0)\n";
echo "This feedback helps improve future AI lookups.\n";
echo "\n";

// ============================================================================
// EXAMPLE 6: Clear cache if product data becomes outdated
// ============================================================================

echo "=== EXAMPLE 6: Cache Management ===\n";

$cleared = $smartAssetService->clearCache(
    manufacturer: 'Martin',
    model: 'MAC 300 Spot',
    instanceId: 1
);

echo "Cache cleared: " . ($cleared ? 'yes' : 'no') . "\n";
echo "Next lookup will use AI web search instead of cache.\n";
echo "\n";

// ============================================================================
// EXAMPLE 7: Manufacturer autocomplete (for UI)
// ============================================================================

echo "=== EXAMPLE 7: Autocomplete ===\n";

$manufacturerSuggestions = $smartAssetService->getManufacturerSuggestions(
    query: 'mar',
    instanceId: 1
);

echo "Manufacturers matching 'mar':\n";
foreach ($manufacturerSuggestions as $mfg) {
    echo "  - {$mfg['name']} (ID: {$mfg['id']})\n";
}
echo "\n";

// ============================================================================
// EXAMPLE 8: Model autocomplete for specific manufacturer
// ============================================================================

echo "=== EXAMPLE 8: Model Autocomplete ===\n";

$modelSuggestions = $smartAssetService->getModelSuggestions(
    manufacturer: 'Martin',
    query: 'mac',
    instanceId: 1
);

echo "Models from Martin matching 'mac':\n";
foreach ($modelSuggestions as $model) {
    echo "  - {$model['name']} (ID: {$model['id']})\n";
}
echo "\n";

// ============================================================================
// EXAMPLE 9: Lookup from photo (requires vision-capable AI provider)
// ============================================================================

echo "=== EXAMPLE 9: Photo Lookup ===\n";

// Assumes file exists
$photoPath = '/tmp/product_photo.jpg';

if (file_exists($photoPath)) {
    try {
        $photoResult = $smartAssetService->lookupFromPhoto(
            imagePath: $photoPath,
            instanceId: 1
        );

        echo "Extracted from photo:\n";
        echo "  Manufacturer: " . $photoResult->manufacturer . "\n";
        echo "  Model: " . $photoResult->model . "\n";
        echo "  Name: " . $photoResult->name . "\n";
        echo "  Confidence: " . ($photoResult->overallConfidence * 100) . "%\n";
    } catch (Exception $e) {
        echo "Photo lookup failed: " . $e->getMessage() . "\n";
    }
} else {
    echo "Photo file not found for demo.\n";
}
echo "\n";

// ============================================================================
// EXAMPLE 10: Bulk lookup (background job)
// ============================================================================

echo "=== EXAMPLE 10: Bulk Lookup ===\n";

$items = [
    ['manufacturer' => 'Martin', 'model' => 'MAC 300 Spot'],
    ['manufacturer' => 'Chauvet', 'model' => 'Maverick'],
    ['manufacturer' => 'ETC', 'model' => 'Source Four'],
];

$jobInfo = $smartAssetService->bulkLookup(
    items: $items,
    instanceId: 1
);

echo "Bulk lookup started:\n";
echo "  Job ID: " . $jobInfo['jobId'] . "\n";
echo "  Items: " . $jobInfo['itemsCount'] . "\n";
echo "  Status: Check via getBulkLookupStatus() with this Job ID\n";
echo "\n";

// Polling example (in production, do this from client-side)
$jobId = $jobInfo['jobId'];
for ($attempt = 0; $attempt < 30; $attempt++) {
    sleep(1); // Poll every second

    $status = $smartAssetService->getBulkLookupStatus($jobId, 1);

    if (!$status) {
        echo "Job not found!\n";
        break;
    }

    echo "Progress: {$status['completedCount']}/{$status['itemsCount']} ({$status['progress']}%)\n";

    if ($status['status'] === 'completed') {
        echo "Job completed! Results:\n";
        foreach ($status['results'] as $idx => $result) {
            if (isset($result['error'])) {
                echo "  Item {$idx}: ERROR - " . $result['error'] . "\n";
            } else {
                echo "  Item {$idx}: {$result['name']} ({$result['manufacturer']} {$result['model']})\n";
            }
        }
        break;
    }

    if ($status['status'] === 'failed') {
        echo "Job failed: " . $status['error'] . "\n";
        break;
    }
}
echo "\n";

// ============================================================================
// EXAMPLE 11: Convert result to array (for JSON API responses)
// ============================================================================

echo "=== EXAMPLE 11: Result Serialization ===\n";

$resultArray = $result->toArray();

echo json_encode($resultArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "\n";

// ============================================================================
// EXAMPLE 12: Recreate result from cached array
// ============================================================================

echo "=== EXAMPLE 12: Deserialization ===\n";

$cachedArray = [
    'name' => 'Martin MAC 300 Spot',
    'manufacturer' => 'Martin',
    'model' => 'MAC 300',
    'weightKg' => 25.5,
    'powerWatts' => 300,
    'newPrice' => 1899.00,
    'sources' => [
        'name' => ['source' => 'ai', 'confidence' => 0.98],
        'powerWatts' => ['source' => 'ai', 'confidence' => 0.95],
    ],
    'overallConfidence' => 0.92,
];

$reconstructed = AssetLookupResult::fromArray($cachedArray);

echo "Reconstructed result:\n";
echo "  Name: " . $reconstructed->name . "\n";
echo "  Weight: " . $reconstructed->weightKg . " kg\n";
echo "  Confidence: " . ($reconstructed->overallConfidence * 100) . "%\n";
echo "\n";

// ============================================================================
// EXAMPLE 13: Error handling in production
// ============================================================================

echo "=== EXAMPLE 13: Error Handling ===\n";

try {
    $result = $smartAssetService->lookup('Unknown', 'Product', null, 1);

    // Even with unknown products, service returns a result with low confidence
    if ($result->overallConfidence < 0.5) {
        echo "Low confidence result (" . ($result->overallConfidence * 100) . "%)\n";
        echo "Manual verification recommended.\n";
    }
} catch (Exception $e) {
    // Service doesn't throw exceptions, but wrap for safety
    error_log("Lookup failed: " . $e->getMessage());
}
echo "\n";

// ============================================================================
// EXAMPLE 14: Integration with asset creation
// ============================================================================

echo "=== EXAMPLE 14: Asset Creation Integration ===\n";

// After user confirms lookup data in UI, create asset
$lookupData = $result; // Result from example 1

$assetData = [
    'assetTypes_name' => $lookupData->name,
    'assetTypes_description' => $lookupData->description,
    'manufacturers_id' => 1, // Would need to look up by name in production
    'assetCategories_id' => 5, // Would need to match $lookupData->category
    'assetTypes_weight' => $lookupData->weightKg,
    'assetTypes_dimensions' => $lookupData->dimensions,
    'assetTypes_powerConsumption' => $lookupData->powerWatts,
    'assetTypes_purchasePrice' => $lookupData->newPrice,
    'assetTypes_rentalDayPrice' => $lookupData->suggestedRentalPrice,
];

echo "Asset data ready for creation:\n";
echo json_encode($assetData, JSON_PRETTY_PRINT) . "\n";
echo "\n";

echo "=== All Examples Complete ===\n";
