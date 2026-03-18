# Multi-KI-Provider System - Deployment Checklist

## Pre-Deployment (Development)

- [ ] Review `/docs/AI_PROVIDER_SYSTEM.md` to understand architecture
- [ ] Review `/examples/ai_usage_example.php` for integration patterns
- [ ] Read `/IMPLEMENTATION_SUMMARY.md` for overview
- [ ] Test migration locally: `vendor/bin/phinx migrate -e development`

## Database Deployment

- [ ] Run Phinx migration on production:
  ```bash
  vendor/bin/phinx migrate -e production
  ```
  Migration file: `/db/migrations/20260318190000_multi_ai_providers.php`

- [ ] Verify tables created:
  ```sql
  SHOW TABLES LIKE 'ai_%';
  -- Should show: ai_providers, ai_task_routing, ai_usage_log, ai_fallback_chain
  ```

## Application Bootstrap

- [ ] Add to application bootstrap (e.g., index.php or app.php):
  ```php
  require_once __DIR__ . '/config/ai_bootstrap.php';
  setupAiSystem($db);
  ```
  File: `/config/ai_bootstrap.php`

- [ ] Test bootstrap code loads correctly
- [ ] Verify no PHP errors during bootstrap

## Environment Configuration

- [ ] Set `AI_ENCRYPTION_KEY` in `.env` (or will fall back to `APP_SECRET`)
  ```bash
  AI_ENCRYPTION_KEY=your-256-bit-key-here
  ```

- [ ] Optional: Set monthly budget in instances table
  ```sql
  UPDATE instances SET instances_aiMonthlyBudgetEur = 100.00;
  ```

## Access Control

- [ ] Add permission `AI:VIEW` to admin role
  - Allows viewing providers, usage stats, models

- [ ] Add permission `AI:CONFIGURE` to admin role
  - Allows adding/editing/deleting providers, configuring routing

- [ ] Test permissions on non-admin user (should see 403)

## Web UI Deployment

- [ ] Create route/menu item for `/admin/ai_settings.php` (path configurable)
- [ ] Verify AdminLTE3 is available (for styling)
- [ ] Test Web UI loads without errors
- [ ] Verify CSS/JS loads correctly

## First Provider Setup

### Add a Test Provider (Recommended: Claude or OpenAI)

1. Log in as admin
2. Navigate to AI Settings
3. Click "Add Provider"
4. Fill in:
   - **Name:** "Claude Main" (or your choice)
   - **Type:** "claude" (or "openai")
   - **API Key:** Your valid API key
   - **Model:** "claude-haiku-4-5-20251001" (or default for your provider)
   - Check "Set as Default"
5. Click "Add Provider"
6. Click "Test" button to verify connectivity
7. Should see: "✓ Provider connection successful"

## Configuration & Testing

### Test in Code

Create a test endpoint or script:

```php
require_once 'config/ai_bootstrap.php';
setupAiSystem($db);

$handler = getAiHandler($db);
$response = $handler->processRequest(
    'test_task',
    'Hello, this is a test. Respond with one word.'
);

echo "Response: " . $response->content . "\n";
echo "Model: " . $response->model . "\n";
echo "Tokens: " . $response->inputTokens . " input, " . $response->outputTokens . " output\n";
```

Expected output: One-word response from your provider

### Test Web UI

1. Go to Providers tab → Should see your provider listed
2. Go to Costs & Budget tab → Should see usage appear after a test request
3. Try adding a Task Routing rule
4. Verify all tabs load without JavaScript errors (check browser console)

## Integration with Existing Services

### Optional: Migrate ClaudeService References

1. Find references to `ClaudeService` in codebase
2. For each usage, consider migration to `AiRequestHandler`
3. Example before/after at `/examples/ai_usage_example.php` Example 7

No urgent migration needed - `ClaudeService` continues to work

### For AiAssetLookupService

If you want to update it to use new system:
- See `/examples/ai_usage_example.php` Example 7 for refactoring pattern
- No breaking changes to public API
- Gradually update as convenient

## Production Hardening

- [ ] Verify `AI_ENCRYPTION_KEY` is stored securely (not in git)
- [ ] Review API key rotation policy
- [ ] Set up monitoring for failed AI requests
- [ ] Configure error logging for `AiRequestHandler` exceptions
- [ ] Review usage logs regularly (via Web UI or database queries)

## Monitoring & Maintenance

### Monitor These Metrics

- **Usage volume:** Check `ai_usage_log` table growth
- **API errors:** Check application error logs for provider failures
- **Budget:** Monitor monthly costs via Web UI
- **Performance:** Check average latency_ms in `ai_usage_log`

### Regular Maintenance

- [ ] Archive old usage logs (older than 90 days) if dataset grows large
- [ ] Update `COST_MAP` in adapter classes if pricing changes
- [ ] Test fallback chains monthly to ensure they work
- [ ] Review and update `instances_aiMonthlyBudgetEur` based on actual usage

## Rollback Plan

If something goes wrong:

1. **Revert migration:** `vendor/bin/phinx rollback`
   - Deletes all 4 new tables
   - Old `ClaudeService` continues to work
   - No data loss in other tables

2. **Disable AI UI:** Remove menu item for AI Settings

3. **Disable AI Calls:** Comment out `setupAiSystem()` in bootstrap

No permanent data stored outside these 4 tables

## File Locations for Reference

### Core Services
- `/src/services/AI/` - All AI service classes
- `/src/services/AI/Providers/` - Provider adapters (6 files)

### API Endpoints
- `/src/api/ai/providers.php` - Provider CRUD
- `/src/api/ai/provider_test.php` - Test connection
- `/src/api/ai/provider_delete.php` - Delete provider
- `/src/api/ai/task_routing.php` - Task routing CRUD
- `/src/api/ai/usage.php` - Usage statistics (updated)
- `/src/api/ai/models.php` - List models

### UI
- `/src/templates/ai/ai_settings.twig` - Admin interface

### Database
- `/db/migrations/20260318190000_multi_ai_providers.php` - Schema

### Configuration
- `/config/ai_bootstrap.php` - Initialization helper
- `.env` - Set `AI_ENCRYPTION_KEY` here

### Documentation
- `/docs/AI_PROVIDER_SYSTEM.md` - Full documentation
- `/IMPLEMENTATION_SUMMARY.md` - Implementation overview
- `/examples/ai_usage_example.php` - 10 usage examples
- `/DEPLOYMENT_CHECKLIST.md` - This file

## Post-Deployment Testing

Run these checks after deployment:

```bash
# 1. Verify tables exist
mysql> SELECT TABLE_NAME FROM information_schema.TABLES
       WHERE TABLE_SCHEMA = 'your_db' AND TABLE_NAME LIKE 'ai_%';

# 2. Verify migration ran
mysql> SELECT * FROM phinxlog WHERE version = 20260318190000;

# 3. Check encryption key is set
php -r "echo getenv('AI_ENCRYPTION_KEY') ? 'Set' : 'Not set';"

# 4. Test Web UI loads
curl http://yourdomain/admin/ai_settings.php | grep -q "AI Provider" && echo "UI OK"

# 5. Test API endpoint
curl -H "Authorization: Bearer $TOKEN" http://yourdomain/api/ai/providers | jq .

# 6. Test provider connection
curl -X POST http://yourdomain/api/ai/provider_test \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"provider_id": 1}'
```

## Known Limitations & Future Improvements

### Current Limitations
- No streaming support (responses wait for completion)
- No batch processing (submit multiple requests at once)
- Usage logs not automatically archived (can grow large)
- No API rate limiting per user

### Future Enhancements (I4+)
- Streaming response support
- Redis caching layer
- Per-provider rate limiting
- Advanced analytics dashboard
- Budget alerts via email
- Model fine-tuning support
- Vision pipeline optimization

## Support & Troubleshooting

### Common Issues

**"No default AI provider configured"**
- Set a provider as default in Web UI
- Or use `getAiProvider()` with specific task routing

**"API key encryption failed"**
- Verify `AI_ENCRYPTION_KEY` or `APP_SECRET` is set
- Check key is 256+ bits long (64 hex characters)

**"Provider connection failed"**
- Verify API key is correct
- Test provider connectivity in Web UI
- Check network access to provider API
- Review provider's status page

**High API costs**
- Review usage statistics in Web UI
- Consider routing expensive tasks to cheaper providers
- Implement request caching upstream
- Set monthly budget limit to prevent overspend

### Debug Commands

```php
// Check what provider is being used for a task
$registry = new AiProviderRegistry($db);
$provider = $registry->getForTask('asset_lookup', $instanceId);
echo $provider->getProviderName();

// Get current month's usage
$tracker = new AiUsageTracker($db);
$summary = $tracker->getUsageSummary($instanceId, 'month');
echo "Cost: €" . $summary['total_cost_eur'];

// List all providers
$providers = $registry->listAvailable($instanceId);
foreach ($providers as $p) {
    echo $p['name'] . " (" . $p['provider_type'] . ")\n";
}
```

## Sign-Off

- [ ] Database migration tested and applied
- [ ] Bootstrap code integrated and tested
- [ ] Permissions configured
- [ ] At least one provider configured and tested
- [ ] Web UI loads and is functional
- [ ] Usage logging works
- [ ] Team trained on new system
- [ ] Documentation reviewed by team
- [ ] Rollback plan understood

**Deployment Date:** _______________
**Deployed By:** _______________
**Reviewed By:** _______________

---

**System Ready for Production Use**
