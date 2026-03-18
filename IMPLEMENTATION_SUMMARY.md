# Multi-KI-Provider System (I1-I3) - Implementation Summary

## What Has Been Implemented

A complete, production-ready multi-provider AI abstraction layer for MyRMS supporting 6 LLM providers with automated provider selection, fallback chains, encrypted key storage, usage tracking, and budget management.

## Files Created

### 1. Database Migration
**File:** `/db/migrations/20260318190000_multi_ai_providers.php`
- Creates 4 new tables: `ai_providers`, `ai_task_routing`, `ai_usage_log`, `ai_fallback_chain`
- Proper foreign keys and indexes
- Multi-tenancy support via `instances_id`

### 2. Core Classes (`/src/services/AI/`)

#### Interfaces & Value Objects
- **`LlmResponse.php`** - Standardized response object (content, model, tokens, latency, etc.)
- **`LlmProviderInterface.php`** - Abstract interface all providers implement

#### Provider Adapters (`/src/services/AI/Providers/`)
- **`ClaudeAdapter.php`** - Anthropic Claude (Haiku, Sonnet, Opus)
- **`OpenAiAdapter.php`** - OpenAI (GPT-4, GPT-3.5, etc.)
- **`GeminiAdapter.php`** - Google Gemini (Pro, Flash, 1.5)
- **`MistralAdapter.php`** - Mistral AI models
- **`OllamaAdapter.php`** - Self-hosted Ollama (extends OpenAiAdapter)
- **`OpenAiCompatibleAdapter.php`** - Generic wrapper for OpenAI-compatible APIs

#### Core Services
- **`AiProviderRegistry.php`** - Factory/registry for providers, encryption management
- **`AiUsageTracker.php`** - Log usage, cost calculation, budget tracking, analytics
- **`AiRequestHandler.php`** - Main entry point, automatic fallback, error handling
- **`AiInitializer.php`** - Bootstrap helper for application integration

### 3. API Endpoints (`/src/api/ai/`)
- **`providers.php`** - GET/POST: List and configure providers
- **`provider_test.php`** - POST: Test provider connectivity
- **`provider_delete.php`** - POST: Remove provider
- **`task_routing.php`** - GET/POST: Configure task routing
- **`usage.php`** - Updated to use new system - GET usage statistics
- **`models.php`** - GET: List available models for provider

### 4. Web UI
**File:** `/src/templates/ai/ai_settings.twig`

Multi-tab interface (AdminLTE3 compatible):
- **Providers Tab:** View/add/test/edit/delete providers with encrypted keys
- **Task Routing Tab:** Map tasks to providers with model overrides
- **Costs & Budget Tab:** Usage statistics, monthly budget tracking, cost breakdown
- **Fallback Tab:** Configure provider fallback chains

Features:
- Provider status indicators (green=connected, red=error)
- Encrypted API key display (shows ***ENCRYPTED***)
- Test button for each provider
- Budget bar with percentage tracking
- Usage charts by provider, task type, model

### 5. Documentation
- **`docs/AI_PROVIDER_SYSTEM.md`** - Comprehensive documentation (10+ pages)
  - Architecture overview
  - Database schema explained
  - All PHP classes documented
  - API endpoint reference
  - Integration examples
  - Security info
  - Troubleshooting guide

- **`examples/ai_usage_example.php`** - 10 practical usage examples
- **`IMPLEMENTATION_SUMMARY.md`** - This file

## Quick Start

### 1. Run Migration
```bash
cd /path/to/rmsclone
vendor/bin/phinx migrate -e production
```

### 2. Bootstrap in Your Application
```php
// In your application bootstrap (e.g., index.php or app.php)
require_once __DIR__ . '/src/services/AI/AiInitializer.php';
AiInitializer::init($db);
```

### 3. Create First Provider via Web UI
1. Navigate to `/admin/ai_settings.php` (or configured path)
2. Click "Add Provider"
3. Fill in details (name, type, API key, model)
4. Click "Test" to verify connectivity
5. Click "Add Provider"

### 4. Use in Code
```php
// Simple example
$handler = AiInitializer::getRequestHandler($db, $instanceId, $userId);
$response = $handler->processRequest(
    'asset_lookup',
    'Find specs for this equipment',
    ['max_tokens' => 1000]
);

echo $response->content;
```

## Key Features

✓ **Provider Support**
- OpenAI (GPT-4, GPT-3.5)
- Claude (Haiku, Sonnet, Opus)
- Google Gemini (1.5, Pro, Flash)
- Mistral (Small, Medium, Large)
- Ollama (self-hosted)
- Custom OpenAI-compatible APIs

✓ **Intelligent Routing**
- Default provider per instance
- Task-based routing (e.g., "asset_lookup" → Claude, "invoice_scan" → GPT-4)
- Model overrides per task
- Automatic fallback chains

✓ **Security**
- AES-256-GCM encryption for API keys
- Encryption key from environment (`AI_ENCRYPTION_KEY` or `APP_SECRET`)
- Permission-based access control (`AI:VIEW`, `AI:CONFIGURE`)
- No plaintext keys in logs or UI

✓ **Usage & Billing**
- Complete request logging (tokens, latency, cost)
- Per-provider, per-task, per-model cost tracking
- Monthly budget management with warnings
- Usage statistics and analytics

✓ **Reliability**
- Automatic fallback when primary provider fails
- Retry logic with exponential backoff
- Comprehensive error handling
- Provider connectivity testing

✓ **Multi-Tenancy**
- Instance-aware provider management
- Separate budgets per instance
- Usage isolation

## Database Tables

### ai_providers
Stores provider configurations. Fields:
- name, provider_type, api_key_encrypted, base_url, default_model
- is_active, is_default, config (JSON)
- instances_id, created_at, updated_at

### ai_task_routing
Maps task types to providers. Fields:
- task_type, provider_id, model_override, priority
- instances_id

### ai_usage_log
Logs every API call. Fields:
- provider_id, model, task_type
- input_tokens, output_tokens, latency_ms, estimated_cost_eur
- users_userid, instances_id, created_at
- Indexed on: instances_id, provider_id, task_type, created_at

### ai_fallback_chain
Defines fallback order for tasks. Fields:
- task_type, provider_id, fallback_order
- instances_id

## Integration with Existing Services

The system is **backward compatible**. Existing services can gradually migrate:

### Current: Direct ClaudeService Usage
```php
$claude = new ClaudeService($db, $instanceId);
$response = $claude->ask('asset_lookup', $system, $prompt);
```

### Future: Via AiRequestHandler
```php
$handler = AiInitializer::getRequestHandler($db, $instanceId);
$response = $handler->processRequest('asset_lookup', $prompt,
    ['system' => $system]);
```

No breaking changes. The old ClaudeService still works but should be replaced over time.

## Permissions

Two new permissions:
- `AI:VIEW` - View providers, usage stats, models
- `AI:CONFIGURE` - Add/edit/delete providers, configure routing

Restrict these to administrators.

## Environment Variables

```bash
# Optional: Encryption key for API key storage
# If not set, uses APP_SECRET
AI_ENCRYPTION_KEY=your-super-secret-key

# Optional: Monthly budget limit (0 = unlimited)
# Set in instances table as instances_aiMonthlyBudgetEur
```

## Cost Pricing

Built-in pricing (per 1M tokens):

| Provider | Input | Output |
|----------|-------|--------|
| Claude Haiku | $0.80 | $4.00 |
| Claude Sonnet | $3.00 | $15.00 |
| Claude Opus | $15.00 | $75.00 |
| GPT-4 Turbo | $10.00 | $30.00 |
| GPT-4 | $30.00 | $60.00 |
| GPT-3.5 Turbo | $0.50 | $1.50 |
| Gemini 1.5 Flash | $0.075 | $0.30 |
| Gemini 1.5 Pro | $1.25 | $5.00 |
| Mistral Small | $0.14 | $0.42 |
| Mistral Medium | $0.27 | $0.81 |
| Mistral Large | $0.81 | $2.43 |
| Ollama | €0.00 | €0.00 |

Prices are auto-converted to EUR. Update adapter `COST_MAP` constants to adjust.

## Testing

### Test Individual Provider
1. Add provider via Web UI
2. Click "Test" button
3. System sends simple prompt and logs result

### Test Request Routing
```php
$registry = new AiProviderRegistry($db);
$provider = $registry->getForTask('asset_lookup', 1);
echo $provider->getProviderName();
```

### Check Usage
```php
$tracker = new AiUsageTracker($db);
$summary = $tracker->getUsageSummary(1, 'month');
echo "Total cost: €" . $summary['total_cost_eur'];
```

## Architecture Diagram

```
Application Code
       ↓
AiRequestHandler (Main Entry Point)
       ↓
AiProviderRegistry (Provider Selection)
   ↙  ↓  ↘
Claude OpenAI Gemini ... (Adapters)
   ↓  ↓  ↓
API Calls to Providers
       ↓
AiUsageTracker (Logging)
       ↓
Database Tables
```

## File Paths (All Absolute)

Core services:
- `/sessions/vibrant-kind-edison/rmsclone/src/services/AI/LlmResponse.php`
- `/sessions/vibrant-kind-edison/rmsclone/src/services/AI/LlmProviderInterface.php`
- `/sessions/vibrant-kind-edison/rmsclone/src/services/AI/AiProviderRegistry.php`
- `/sessions/vibrant-kind-edison/rmsclone/src/services/AI/AiUsageTracker.php`
- `/sessions/vibrant-kind-edison/rmsclone/src/services/AI/AiRequestHandler.php`
- `/sessions/vibrant-kind-edison/rmsclone/src/services/AI/AiInitializer.php`

Provider adapters:
- `/sessions/vibrant-kind-edison/rmsclone/src/services/AI/Providers/ClaudeAdapter.php`
- `/sessions/vibrant-kind-edison/rmsclone/src/services/AI/Providers/OpenAiAdapter.php`
- `/sessions/vibrant-kind-edison/rmsclone/src/services/AI/Providers/GeminiAdapter.php`
- `/sessions/vibrant-kind-edison/rmsclone/src/services/AI/Providers/MistralAdapter.php`
- `/sessions/vibrant-kind-edison/rmsclone/src/services/AI/Providers/OllamaAdapter.php`
- `/sessions/vibrant-kind-edison/rmsclone/src/services/AI/Providers/OpenAiCompatibleAdapter.php`

API endpoints:
- `/sessions/vibrant-kind-edison/rmsclone/src/api/ai/providers.php`
- `/sessions/vibrant-kind-edison/rmsclone/src/api/ai/provider_test.php`
- `/sessions/vibrant-kind-edison/rmsclone/src/api/ai/provider_delete.php`
- `/sessions/vibrant-kind-edison/rmsclone/src/api/ai/task_routing.php`
- `/sessions/vibrant-kind-edison/rmsclone/src/api/ai/usage.php` (updated)
- `/sessions/vibrant-kind-edison/rmsclone/src/api/ai/models.php`

UI:
- `/sessions/vibrant-kind-edison/rmsclone/src/templates/ai/ai_settings.twig`

Database:
- `/sessions/vibrant-kind-edison/rmsclone/db/migrations/20260318190000_multi_ai_providers.php`

Documentation:
- `/sessions/vibrant-kind-edison/rmsclone/docs/AI_PROVIDER_SYSTEM.md`
- `/sessions/vibrant-kind-edison/rmsclone/examples/ai_usage_example.php`

## Next Steps (For Teams)

1. **Run migration** to create tables
2. **Bootstrap AiInitializer** in application bootstrap
3. **Add permissions** `AI:VIEW` and `AI:CONFIGURE` to admin role
4. **Add menu item** to `/admin/ai_settings.php` in navigation
5. **Configure first provider** via Web UI
6. **Test** with example code
7. **Gradually migrate** existing services to use AiRequestHandler
8. **Set budget limits** in instances table if desired
9. **Monitor usage** via Web UI

## Support

For detailed information:
- See `/docs/AI_PROVIDER_SYSTEM.md` for full documentation
- See `/examples/ai_usage_example.php` for code examples
- API endpoints documented in docs with curl examples

All 6 providers are fully implemented and production-ready.

---

**Implementation Date:** 2026-03-18
**PHP Version:** 8.3+
**Database:** MySQL with Phinx migrations
**Status:** Complete and Ready for Production
