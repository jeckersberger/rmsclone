# Smart Asset Creator (I9) - Implementation Guide

## Overview

The Smart Asset Creator is an AI-powered feature for MyRMS that intelligently enriches asset data by combining:

1. **Cache lookups** (30-day TTL)
2. **AI web search** via AiRequestHandler
3. **Vision-based photo analysis** (image → manufacturer + model)
4. **Bulk lookups** (background job queue)
5. **Price suggestions** (category-aware markup)
6. **Category suggestions** (AI-powered classification)
7. **User correction feedback** (learning loop)

## Architecture

### Database Layer
**Migration:** `20260318210000_smart_asset_creator.php`

Three main tables:

#### `asset_lookup_cache`
Stores AI lookup results for reuse (30-day TTL):
- `manufacturer`, `model`, `ean` - search keys
- `data_json` - full structured asset data
- `sources` - per-field confidence & source tracking
- `confidence` - overall confidence score (0.0-1.0)
- `expires_at` - for automatic purging

#### `asset_lookup_corrections`
User corrections for ML feedback:
- `cache_id` - references lookup result being corrected
- `field_name` - which field was corrected
- `original_value`, `corrected_value` - before/after
- `corrected_by` - user ID who made the correction

#### `smart_lookup_jobs`
Background job tracking for bulk operations:
- `id` - UUID job identifier
- `status` - pending/processing/completed/failed
- `items_count`, `completed_count` - progress tracking
- `payload` - original request
- `results` - JSON array of results when complete

### Service Layer

#### `AssetLookupResult` (Value Object)
Immutable data structure representing a complete lookup result:
```php
public function __construct(
    public string $name,                    // Official product name
    public string $manufacturer,            // Brand
    public string $model,                   // Model identifier
    public ?string $category = null,        // Suggested category
    public ?string $categorySubId = null,   // Category ID if matched
    public ?float $weightKg = null,
    public ?string $dimensions = null,      // "LxWxH" in mm
    public ?int $powerWatts = null,
    public ?float $newPrice = null,         // RRP in EUR
    public ?float $suggestedRentalPrice = null,
    public ?string $description = null,
    public ?string $imageUrl = null,
    public array $technicalSpecs = [],      // key-value pairs
    public array $accessories = [],
    public array $sources = [],             // [field => [source, confidence]]
    public float $overallConfidence = 0.0,
)
```

#### `SmartAssetLookupService` (~400 lines)

**Key Methods:**

```php
// Main lookup: manufacturer + model → enriched data
lookup(manufacturer, model, ean?, instanceId): AssetLookupResult
  → Checks 30-day cache first
  → Falls back to AI web search via AiRequestHandler
  → Caches result with confidence scores

// Image analysis: photo → extract manufacturer + model, then lookup
lookupFromPhoto(imagePath, instanceId): AssetLookupResult
  → Uses vision-capable provider (Gemini/Claude/GPT-4o)
  → Extracts manufacturer + model from image
  → Runs standard lookup() with extracted info

// Bulk operations: array of {manufacturer, model} → returns job ID immediately
bulkLookup(items, instanceId): {jobId, itemsCount}
  → Creates smart_lookup_jobs record
  → Returns immediately with job ID
  → Client polls getBulkLookupStatus() for progress

// Pricing suggestions: newPrice + category → rental price
suggestRentalPrice(newPrice, category, instanceId): float
  → Checks internal price history for similar category
  → Falls back to default markup (15%)

// Category suggestions: name + description → suggested category
suggestCategory(name, description, instanceId): {category, categoryId, suggestions[]}
  → Uses AI to analyze product info
  → Matches against existing categories in database

// User corrections: records field corrections for learning
recordCorrection(cacheId, field, originalValue, correctedValue, userId, instanceId)

// Cache management
getCachedLookup(manufacturer, model, instanceId): ?AssetLookupResult
clearCache(manufacturer, model, instanceId): bool

// Autocomplete for UI
getManufacturerSuggestions(query, instanceId): [{id, name}, ...]
getModelSuggestions(manufacturer, query, instanceId): [{id, name}, ...]
```

## API Endpoints

All endpoints require `ASSETS:CREATE` permission.

### POST `/api/assets/smart_lookup.php`
```
Input:  {manufacturer, model, ean?}
Output: AssetLookupResult (JSON)
        ├─ name, manufacturer, model
        ├─ weightKg, dimensions, powerWatts
        ├─ newPrice, suggestedRentalPrice
        ├─ description, category
        ├─ technicalSpecs, accessories
        ├─ sources[field] = {source: "ai|db|web", confidence: 0.95}
        └─ overallConfidence (0.0-1.0)
```

### POST `/api/assets/smart_lookup_photo.php`
```
Input:  multipart/form-data: photo=<image file>
Output: AssetLookupResult (same as smart_lookup.php)
        (Vision provider extracts manufacturer+model from image)
```

### POST `/api/assets/smart_lookup_bulk.php`
```
Input:  {items: [{manufacturer, model, ean?}, ...]}
Output: {jobId: "uuid", itemsCount: int}
        (Returns immediately, client polls for results)
```

### GET `/api/assets/smart_lookup_status.php`
```
Query:  ?jobId=uuid
Output: {
          jobId, status (pending|processing|completed|failed),
          itemsCount, completedCount, progress (0-100),
          results?: [...],    // When status === completed
          error?: string      // When status === failed
        }
```

### GET `/api/assets/manufacturer_suggest.php`
```
Query:  ?q=query (minimum 2 chars)
Output: [{id, name}, ...] (up to 10 matches)
```

### GET `/api/assets/model_suggest.php`
```
Query:  ?manufacturer=name&q=query (minimum 2 chars)
Output: [{id, name}, ...] (up to 10 matches, filtered by manufacturer)
```

### POST `/api/assets/lookup_correction.php`
```
Input:  {cacheId, field, originalValue, correctedValue}
Output: {success: bool, message: string}
        (Records correction for learning feedback)
```

## UI Integration

### Twig Template: `smart_create_modal.twig`

Multi-step wizard modal (Bootstrap 4 / AdminLTE3):

**Step 1: Input**
- Photo upload (drag & drop or click)
- Manual input: Manufacturer + Model (with autocomplete)
- Optional: EAN code

**Step 2: Loading**
- Animated spinner
- Live status indicators showing:
  - ⏳ Retrieving basic data
  - ⏳ Processing technical specs
  - ⏳ Fetching price & category

**Step 3: Review & Confirm**
- Pre-filled form with all extracted fields
- Source badges per field:
  - 🤖 AI
  - 🌐 Web
  - ✅ Database
- Confidence indicators (green >90%, yellow 70-90%, red <70%)
- "Asset speichern" button submits to asset creation endpoint

## Usage Example

### From JavaScript (client-side)

```javascript
// Step 1: Initiate lookup
const formData = new FormData();
formData.append('manufacturer', 'Martin');
formData.append('model', 'MAC 300 Spot');

const response = await fetch('/src/api/assets/smart_lookup.php', {
    method: 'POST',
    body: formData
});

const result = await response.json();
// result.data contains AssetLookupResult

// Step 2: If user corrects a field
const correctionData = new FormData();
correctionData.append('cacheId', cacheId);
correctionData.append('field', 'weightKg');
correctionData.append('originalValue', '25.5');
correctionData.append('correctedValue', '26.0');

await fetch('/src/api/assets/lookup_correction.php', {
    method: 'POST',
    body: correctionData
});
```

### From PHP (server-side)

```php
// Get the service
$aiRequestHandler = new AiRequestHandler($DBLIB, new AiProviderRegistry($DBLIB), new AiUsageTracker($DBLIB));
$service = new SmartAssetLookupService($DBLIB, $aiRequestHandler);

// Lookup
$result = $service->lookup('Martin', 'MAC 300 Spot', null, 1);

// Check confidence
if ($result->overallConfidence >= 0.9) {
    // High confidence - safe to use
    $price = $result->newPrice;
}

// Get suggestions
$priceRental = $service->suggestRentalPrice($result->newPrice ?? 0, 'Beleuchtung', 1);
$category = $service->suggestCategory($result->name, $result->description, 1);
```

## AI Integration

### Task Routing
- **asset_lookup**: Standard web search for specs → uses configured default provider
- **asset_lookup_photo**: Vision-capable provider (GPT-4o, Claude, Gemini)
- **asset_categorize**: Category classification → can use lightweight model (Mistral Small)

### Prompt Design
The lookup prompt requests structured JSON with fields:
```json
{
  "name": "official product name",
  "weight_kg": 25.5,
  "dimensions_mm": "1200x300x150",
  "power_watts": 1200,
  "light_source": "discharge lamp",
  "color_temp": 5600,
  "beam_angle": 15,
  "protection_class": "IP65",
  "voltage": "230V",
  "connectors": "Schuko, DMX5",
  "dmx_channels": 16,
  "new_price_eur": 1899.00,
  "description": "German description...",
  "category_suggestion": "Beleuchtung",
  "accessories": ["DMX Cable", "Safety Chain"],
  "confidence": 0.95
}
```

## Implementation Notes

### MeekroDB Usage
Following the MeekroDB rules:
- `getOne($table, $where=null, $columns=null)` for single record lookups
- No `groupBy()` - using `get()` instead with limit
- No `->count()` - using `affectedRows()` for insert/update/delete checks
- `$db->where()` for building WHERE clauses

### Cache Strategy
- **TTL**: 30 days (configurable via `CACHE_TTL_DAYS`)
- **Key**: manufacturer + model (composite)
- **Expiration**: Automatic cleanup via `expires_at` column
- **Staleness**: Cache invalidated by user corrections

### Error Handling
- Lookup failures return minimal result with 0.0 confidence
- API endpoints wrap in try/catch with error codes
- AI provider fallback handled by AiRequestHandler
- No exceptions bubble up to client - always return JSON with success flag

### Multi-tenancy
- All queries filtered by `instances_id`
- Service constructor receives `$instanceId` parameter
- Cache isolated per instance

### Permissions
- All endpoints require `ASSETS:CREATE`
- Autocomplete endpoints require `ASSETS:VIEW` (read-only)
- User ID tracked in corrections table

## Future Enhancements

1. **Background Queue**: Move bulk lookups to `AiActionQueueService` for true async processing
2. **ML Feedback Loop**: Use corrections table to retrain AI prompts
3. **Image Storage**: Store extracted images with cache records
4. **Provider Optimization**: Route bulk to Mistral Small for cost savings
5. **Extended Specs**: Support for lighting-specific fields (color temp, beam angle) per category
6. **Barcode Integration**: Auto-populate EAN from barcode scanner, improve cache hit rate

## Testing

### Manual Testing Checklist
- [ ] Single lookup (manufacturer + model)
- [ ] Photo upload and image analysis
- [ ] Confidence scores rendering correctly
- [ ] Source badges showing correct provider (AI/DB/Web)
- [ ] User correction flow
- [ ] Autocomplete suggestions
- [ ] Bulk job creation and status polling
- [ ] Cache reuse on second lookup
- [ ] Cache expiration after 30 days

### Edge Cases
- Empty response from AI provider → minimal result
- Missing fields in AI JSON → null values
- Photo of non-tech item → graceful error
- Bulk job with 100 items → progress updates
- Database connection lost → fallback to 0.0 confidence

## Files Created

```
/db/migrations/20260318210000_smart_asset_creator.php
  └─ Database schema: asset_lookup_cache, asset_lookup_corrections, smart_lookup_jobs

/src/services/AssetLookupResult.php
  └─ Value object for lookup results

/src/services/SmartAssetLookupService.php
  └─ Main service (~400 lines)

/src/api/assets/smart_lookup.php
  └─ POST: Main lookup endpoint

/src/api/assets/smart_lookup_photo.php
  └─ POST: Photo analysis endpoint

/src/api/assets/smart_lookup_bulk.php
  └─ POST: Bulk lookup job creation

/src/api/assets/smart_lookup_status.php
  └─ GET: Job status polling

/src/api/assets/manufacturer_suggest.php
  └─ GET: Autocomplete manufacturers

/src/api/assets/model_suggest.php
  └─ GET: Autocomplete models

/src/api/assets/lookup_correction.php
  └─ POST: Record user corrections

/src/assets/smart_create_modal.twig
  └─ Multi-step wizard UI
```

## Performance Considerations

- Cache hit should reduce lookup time from 2-3 seconds to <100ms
- Bulk jobs process 10-20 items/minute depending on AI provider
- Photo analysis adds ~2 seconds overhead for vision API call
- Confidence scores allow UI to indicate data reliability to users

## Security

- All inputs sanitized via MeekroDB parameter binding
- File uploads validated (MIME type, size)
- Base64 image encoding for vision API (no disk writes)
- User IDs tracked for audit trail
- No PII or sensitive data stored in cache (only asset specs)

## Compliance

- Respects Anonymization Service if enabled (I6)
- User corrections logged for data improvement tracking
- Cache can be purged per instance/manufacturer/model
- No third-party data retention beyond 30 days
