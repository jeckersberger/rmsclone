# Smart Asset Creator (I9) - Quick Start Guide

## Files Location Summary

```
Database:
  db/migrations/20260318210000_smart_asset_creator.php

Services:
  src/services/AssetLookupResult.php
  src/services/SmartAssetLookupService.php

API Endpoints:
  src/api/assets/smart_lookup.php
  src/api/assets/smart_lookup_photo.php
  src/api/assets/smart_lookup_bulk.php
  src/api/assets/smart_lookup_status.php
  src/api/assets/manufacturer_suggest.php
  src/api/assets/model_suggest.php
  src/api/assets/lookup_correction.php

UI:
  src/assets/smart_create_modal.twig

Docs:
  SMART_ASSET_CREATOR_README.md (complete reference)
  SMART_ASSET_CREATOR_QUICKSTART.md (this file)
```

## Installation Steps

### 1. Run Migration
```bash
cd /path/to/rmsclone
vendor/bin/phinx migrate up
```

This creates:
- `asset_lookup_cache` table
- `asset_lookup_corrections` table
- `smart_lookup_jobs` table

### 2. Configure AI Task Routing (if needed)
In database or admin panel, ensure these task types are configured:
- `asset_lookup` - Standard web search (use default provider)
- `asset_lookup_photo` - Vision-capable (GPT-4o, Claude, Gemini)

### 3. Include Modal in Asset Creation Page
In your asset creation template:
```twig
{% include 'smart_create_modal.twig' %}

<button type="button" class="btn btn-info" data-toggle="modal" data-target="#smartAssetCreatorModal">
    <i class="fas fa-wand-magic-sparkles"></i> Smart Creator
</button>
```

## API Quick Reference

### Lookup by Manufacturer & Model
```bash
curl -X POST http://localhost/src/api/assets/smart_lookup.php \
  -F "manufacturer=Martin" \
  -F "model=MAC 300 Spot"
```

Response: `AssetLookupResult` JSON with confidence scores per field

### Lookup from Photo
```bash
curl -X POST http://localhost/src/api/assets/smart_lookup_photo.php \
  -F "photo=@product.jpg"
```

Response: `AssetLookupResult` with extracted manufacturer+model

### Bulk Lookup (Background Job)
```bash
curl -X POST http://localhost/src/api/assets/smart_lookup_bulk.php \
  -H "Content-Type: application/json" \
  -d '{
    "items": [
      {"manufacturer": "Martin", "model": "MAC 300"},
      {"manufacturer": "Chauvet", "model": "Maverick"}
    ]
  }'
```

Response: `{jobId: "abc123...", itemsCount: 2}`

### Check Job Status
```bash
curl http://localhost/src/api/assets/smart_lookup_status.php?jobId=abc123...
```

Response: `{status: "processing", progress: 50, completedCount: 1, ...}`

### Manufacturer Autocomplete
```bash
curl "http://localhost/src/api/assets/manufacturer_suggest.php?q=mar"
```

Response: `[{id: 1, name: "Martin"}, ...]`

### Record User Correction
```bash
curl -X POST http://localhost/src/api/assets/lookup_correction.php \
  -F "cacheId=42" \
  -F "field=weightKg" \
  -F "originalValue=25.5" \
  -F "correctedValue=26.0"
```

Response: `{success: true, message: "Correction recorded"}`

## Usage from PHP

```php
// Initialize service
$aiHandler = new AiRequestHandler($DBLIB, new AiProviderRegistry($DBLIB), new AiUsageTracker($DBLIB));
$service = new SmartAssetLookupService($DBLIB, $aiHandler);

// Single lookup
$result = $service->lookup('Martin', 'MAC 300 Spot', null, 1);

echo $result->name;                    // "Martin MAC 300 Spot"
echo $result->newPrice;                // 1899.00
echo $result->overallConfidence;       // 0.95
echo $result->getFieldConfidence('name');        // 0.98
echo $result->getFieldSource('powerWatts');      // "ai"

// Suggest rental price
$rentalPrice = $service->suggestRentalPrice(1899.00, 'Beleuchtung', 1);

// Suggest category
$category = $service->suggestCategory('Martin MAC 300 Spot', 'Spot light with 300W lamp', 1);

// Record correction for learning
$service->recordCorrection(42, 'weightKg', '25.5', '26.0', 5, 1);
```

## Understanding Confidence Scores

Each lookup result includes:

1. **Per-field confidence**: `sources[field] = {source: string, confidence: 0.0-1.0}`
   - Green UI: confidence >= 0.9 (90%)
   - Yellow UI: confidence 0.7-0.9 (70-90%)
   - Red UI: confidence < 0.7 (below 70%)

2. **Source types**:
   - `ai` - From AI web search
   - `db` - From internal database
   - `web` - From web results (distinct from AI)

3. **Overall confidence**: Average of all fields
   - Used to determine if result is worth caching

Example:
```json
{
  "name": "Martin MAC 300 Spot",
  "overallConfidence": 0.92,
  "sources": {
    "name": {"source": "ai", "confidence": 0.98},
    "powerWatts": {"source": "ai", "confidence": 0.95},
    "weight_kg": {"source": "ai", "confidence": 0.85},
    "newPrice": {"source": "web", "confidence": 0.72}
  }
}
```

## Cache Behavior

**Hit Rate**: Second lookup of same manufacturer+model within 30 days returns from cache
- Cache response time: <100ms
- AI response time: 2-3 seconds
- Photo response time: 2-3 seconds (includes vision API)

**Expiration**: After 30 days, cache entry expires automatically
- Users can force refresh by calling `clearCache()`
- Corrections are non-destructive (stored separately)

## Common Issues

### "No provider available for task 'asset_lookup'"
**Solution**: Configure AI task routing in admin panel

### "Feature not enabled" (if using ClaudeService)
**Solution**: Enable 'asset_lookup' feature flag in admin

### Cache not being used on second lookup
**Solution**: Check `asset_lookup_cache.expires_at` hasn't passed

### Photo upload returns "Invalid type"
**Solution**: Ensure file is actually JPEG/PNG/WebP, not renamed incorrectly

## Performance Tips

1. **Bulk lookups**: Use `bulkLookup()` instead of repeated `lookup()` calls
2. **Cache management**: Periodically clear expired entries (automatic via `expires_at`)
3. **Provider selection**: Use Mistral Small for bulk, GPT-4o for photo, Claude for web search
4. **Polling strategy**: Check job status every 2-3 seconds for good UX

## Security Checklist

- [x] All inputs sanitized via MeekroDB parameter binding
- [x] File uploads validated (MIME type checked)
- [x] Photo data encoded as base64 (not written to disk)
- [x] Permission checks on all endpoints (ASSETS:CREATE)
- [x] User IDs tracked in corrections table
- [x] No PII stored in cache tables

## Testing Checklist

- [ ] Lookup works with existing products in database
- [ ] Lookup works with new/unknown products (AI search)
- [ ] Photo upload extracts manufacturer+model correctly
- [ ] Confidence scores render correctly in modal
- [ ] Source badges show 🤖/🌐/✅ correctly
- [ ] Cache reuse works on second lookup (same day)
- [ ] Cache expiration works (after 30 days simulated)
- [ ] User corrections saved and tracked
- [ ] Autocomplete returns up to 10 suggestions
- [ ] Bulk job returns jobId immediately
- [ ] Bulk job status polling shows progress
- [ ] Error handling shows user-friendly messages

## Troubleshooting

**Lookup returns low confidence (< 0.7)**
- Check if product exists in reference databases online
- Try with more specific model name
- Consider manual data entry if AI confidence is unreliable

**Photo analysis fails**
- Ensure photo clearly shows manufacturer/model labels
- Try with higher resolution image
- Products with generic appearance may fail

**Bulk job shows 0 completed after 5 minutes**
- Check browser console for errors
- Verify AI provider is available
- Check database connection
- Look in error logs for specific failures

## Architecture Decisions Explained

### Why AssetLookupResult as value object?
- Immutable: prevents accidental data corruption
- Type-safe: IDE autocomplete for all fields
- Serializable: easy JSON conversion
- Clean interface: clear what data is available

### Why 30-day cache?
- Balance: Products don't change frequently, but specs can be updated
- Cost: Avoids repeated AI API calls
- Freshness: Not too stale for rental pricing

### Why return jobId immediately for bulk?
- UX: No long page hangs for 20+ item lookups
- Scalability: Can move to real background queue later
- Client-controlled: Clients decide polling frequency

### Why per-field confidence?
- Transparency: Shows which fields are reliable
- Smart UI: Can highlight uncertain values
- Learning: Corrections improve data quality
- Trust: Users know where data came from

## Next Steps

1. **Integration**: Include modal in asset creation page
2. **Testing**: Test all endpoints manually
3. **Monitoring**: Track AI costs via AiUsageTracker
4. **Optimization**: Move bulk jobs to background queue
5. **ML**: Analyze corrections to improve AI prompts
6. **Expansion**: Add more technical spec fields per category

## Support

For detailed documentation, see `SMART_ASSET_CREATOR_README.md`

For implementation questions, check the inline comments in service classes.
