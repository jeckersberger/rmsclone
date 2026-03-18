# Multi-KI-Provider System - Quick Reference

## Installation (30 seconds)

```bash
# 1. Run migration
vendor/bin/phinx migrate -e production

# 2. Add to bootstrap (in index.php or app.php)
require_once __DIR__ . '/config/ai_bootstrap.php';
setupAiSystem($db);

# 3. Set encryption key in .env
AI_ENCRYPTION_KEY=your-key-here
```

## Quick API Usage

```php
// Initialize (already done in bootstrap)
$handler = getAiHandler($db);

// Simple request
$response = $handler->processRequest(
    'asset_lookup',
    'Find specs for Canon EOS R5'
);
echo $response->content;

// With options
$response = $handler->processRequest(
    'invoice_scan',
    $pdfData,
    ['max_tokens' => 2048, 'model' => 'gpt-4-vision']
);

// With retries
$response = $handler->processRequestWithRetry(
    'chat',
    'Your question',
    maxRetries: 3
);

// Get usage stats
$tracker = getAiUsageTracker($db);
$stats = $tracker->getUsageSummary($instanceId, 'month');
echo "Total cost: €" . $stats['total_cost_eur'];
```

## Supported Providers

| Provider | Type | Models | Cost |
|----------|------|--------|------|
| Claude | `claude` | Haiku, Sonnet, Opus | $$ |
| OpenAI | `openai` | GPT-4, GPT-3.5 | $$ |
| Gemini | `gemini` | 1.5 Pro, Flash, Pro | $ |
| Mistral | `mistral` | Large, Medium, Small | $ |
| Ollama | `ollama` | llama2, mistral, etc. | FREE |
| Custom | `openai_compatible` | Any OpenAI-like API | Varies |

## Database Tables

```sql
-- Providers
ai_providers (id, name, provider_type, api_key_encrypted, default_model, is_default)

-- Route tasks to providers
ai_task_routing (task_type, provider_id, model_override, priority)

-- Log all calls
ai_usage_log (provider_id, model, task_type, input_tokens, output_tokens, latency_ms, estimated_cost_eur)

-- Fallback chains
ai_fallback_chain (task_type, provider_id, fallback_order)
```

## Web UI Path

```
/admin/ai_settings.php
```

Tabs:
- **Providers** - Add/edit/test providers
- **Task Routing** - Route tasks to specific providers
- **Costs & Budget** - Usage statistics and budget tracking
- **Fallback** - Configure fallback chains

## Core Classes

```php
// Get handler
$handler = getAiHandler($db, $instanceId, $userId);

// Make request
$response = $handler->processRequest($taskType, $prompt, $options);

// Analyze response
echo $response->content;
echo $response->model;
echo $response->inputTokens;
echo $response->outputTokens;
echo $response->latencyMs;

// Get provider directly
$registry = new AiProviderRegistry($db);
$provider = $registry->getForTask('task_name', $instanceId);
$provider = $registry->getDefault($instanceId);

// Track usage
$tracker = new AiUsageTracker($db);
$tracker->logUsage(...);
$stats = $tracker->getUsageSummary($instanceId, 'month');
$budget = $tracker->getMonthlyBudgetStatus($instanceId);
```

## API Endpoints

```bash
# List providers
GET /api/ai/providers

# Create provider
POST /api/ai/providers
{
  "name": "Claude Main",
  "provider_type": "claude",
  "api_key": "sk-...",
  "default_model": "claude-haiku-4-5-20251001"
}

# Test provider
POST /api/ai/provider_test
{ "provider_id": 1 }

# Delete provider
POST /api/ai/provider_delete
{ "provider_id": 1 }

# Configure task routing
POST /api/ai/task_routing
{
  "task_type": "asset_lookup",
  "provider_id": 1,
  "model_override": null,
  "priority": 0
}

# Get usage stats
GET /api/ai/usage?period=month&provider_id=1

# List models for provider
GET /api/ai/models?provider_id=1
```

## Permissions

```php
// Required for viewing
'AI:VIEW'

// Required for configuring
'AI:CONFIGURE'
```

## Environment Variables

```bash
# API key encryption (optional, defaults to APP_SECRET)
AI_ENCRYPTION_KEY=your-256-bit-key

# Monthly budget limit (in instances table)
UPDATE instances SET instances_aiMonthlyBudgetEur = 100.00;
```

## Provider Model List

**Claude:**
```
claude-opus-4-6
claude-sonnet-4-6
claude-haiku-4-5-20251001
```

**OpenAI:**
```
gpt-4-turbo
gpt-4
gpt-4o
gpt-3.5-turbo
```

**Gemini:**
```
gemini-1.5-pro
gemini-1.5-flash
gemini-pro
```

**Mistral:**
```
mistral-large
mistral-medium
mistral-small
```

**Ollama:**
```
Dynamic - fetched from your Ollama instance
```

## File Structure

```
src/
  services/AI/
    LlmResponse.php
    LlmProviderInterface.php
    AiProviderRegistry.php
    AiUsageTracker.php
    AiRequestHandler.php
    AiInitializer.php
    Providers/
      ClaudeAdapter.php
      OpenAiAdapter.php
      GeminiAdapter.php
      MistralAdapter.php
      OllamaAdapter.php
      OpenAiCompatibleAdapter.php
  api/ai/
    providers.php
    provider_test.php
    provider_delete.php
    task_routing.php
    usage.php
    models.php
  templates/ai/
    ai_settings.twig

db/migrations/
  20260318190000_multi_ai_providers.php

config/
  ai_bootstrap.php

docs/
  AI_PROVIDER_SYSTEM.md
examples/
  ai_usage_example.php
```

## Common Tasks

### Add Provider via Code

```php
$db->insert('ai_providers', [
    'instances_id' => 1,
    'name' => 'My Provider',
    'provider_type' => 'openai',
    'api_key_encrypted' => (new AiProviderRegistry($db))->encryptApiKey('sk-...'),
    'default_model' => 'gpt-4-turbo',
    'is_active' => 1,
    'is_default' => 0,
]);
```

### Configure Task Routing

```php
$db->insert('ai_task_routing', [
    'instances_id' => 1,
    'task_type' => 'asset_lookup',
    'provider_id' => 1,
    'model_override' => null,
    'priority' => 0,
]);
```

### Check Usage

```php
$tracker = new AiUsageTracker($db);
$summary = $tracker->getUsageSummary($instanceId, 'month');
echo "€" . $summary['total_cost_eur'];
```

### Test Provider

```php
$handler = new AiRequestHandler($db, new AiProviderRegistry($db), new AiUsageTracker($db));
$result = $handler->testProvider($providerId, $instanceId);
if ($result['success']) {
    echo "✓ Connected";
} else {
    echo "✗ " . $result['message'];
}
```

## Error Handling

```php
try {
    $response = $handler->processRequest('task', 'prompt');
} catch (Exception $e) {
    error_log("AI error: " . $e->getMessage());
    // Fall back to default behavior
}
```

## Monitoring

```sql
-- Check today's usage
SELECT SUM(estimated_cost_eur) as cost, COUNT(*) as calls
FROM ai_usage_log
WHERE instances_id = 1
AND created_at >= DATE(NOW());

-- Top expensive tasks
SELECT task_type, SUM(estimated_cost_eur) as cost, COUNT(*) as calls
FROM ai_usage_log
WHERE instances_id = 1
AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
GROUP BY task_type
ORDER BY cost DESC;

-- Check which providers were used
SELECT provider_id, model, COUNT(*) as calls, SUM(estimated_cost_eur) as cost
FROM ai_usage_log
WHERE instances_id = 1
GROUP BY provider_id, model;
```

## Pricing Examples (per 1M tokens)

| Model | Input | Output | Example Cost |
|-------|-------|--------|--------------|
| Claude Haiku | $0.80 | $4.00 | 1000 tokens = €0.004 |
| GPT-4 Turbo | $10.00 | $30.00 | 1000 tokens = €0.010 |
| Gemini Flash | $0.075 | $0.30 | 1000 tokens = €0.0003 |
| Ollama | €0.00 | €0.00 | FREE |

## Integration Checklist

- [ ] Run migration: `vendor/bin/phinx migrate`
- [ ] Add bootstrap: `setupAiSystem($db);`
- [ ] Set permissions: `AI:VIEW`, `AI:CONFIGURE`
- [ ] Add provider via Web UI
- [ ] Test provider connectivity
- [ ] Configure task routing (optional)
- [ ] Start using in code: `getAiHandler($db)->processRequest(...)`

## Links

- **Full Docs:** `/docs/AI_PROVIDER_SYSTEM.md`
- **Examples:** `/examples/ai_usage_example.php`
- **Summary:** `/IMPLEMENTATION_SUMMARY.md`
- **Deployment:** `/DEPLOYMENT_CHECKLIST.md`

---

**Ready to use. Add to your app in 2 minutes.**
