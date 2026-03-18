# Multi-KI-Provider System (I1-I3) Documentation

## Overview

The Multi-KI-Provider System provides a unified, provider-agnostic abstraction layer for integrating multiple Large Language Models (LLMs) into MyRMS. It supports:

- **OpenAI** (GPT-4, GPT-3.5)
- **Anthropic Claude** (Haiku, Sonnet, Opus)
- **Google Gemini** (Pro, Flash, 1.5)
- **Mistral AI** (Small, Medium, Large)
- **Ollama** (Self-hosted, open-source)
- **OpenAI-Compatible APIs** (Deepseek, Together AI, custom deployments)

### Key Features

- ✓ Task-based provider routing and fallback chains
- ✓ Encrypted API key storage (AES-256-GCM)
- ✓ Usage tracking and cost estimation per provider/task/model
- ✓ Multi-tenancy support
- ✓ Budget tracking with warnings
- ✓ Web UI for provider management
- ✓ Zero downtime provider switching

## Architecture

### Database Schema

Four new tables manage the provider ecosystem:

#### `ai_providers`
Defines available LLM providers and their configuration.

```sql
- id (PK)
- instances_id (FK, multi-tenancy)
- name VARCHAR(128) -- User-friendly name
- provider_type ENUM('openai','claude','gemini','mistral','ollama','openai_compatible')
- api_key_encrypted TEXT -- Encrypted with AES-256-GCM
- base_url VARCHAR(255) -- Custom URLs for Ollama, compatible APIs
- default_model VARCHAR(128) -- Default model for this provider
- is_active BOOLEAN -- Whether provider is available
- is_default BOOLEAN -- Default provider for the instance
- config JSON -- Provider-specific settings
- created_at, updated_at
```

#### `ai_task_routing`
Maps task types to preferred providers and models.

```sql
- id (PK)
- instances_id (FK)
- task_type VARCHAR(64) -- e.g., 'asset_lookup', 'invoice_scan'
- provider_id (FK) -- Preferred provider
- model_override VARCHAR(128) -- Optional model override
- priority INT -- Order for fallback chain
```

#### `ai_usage_log`
Tracks all AI API calls for billing and analytics.

```sql
- id (PK, auto-increment bigint)
- instances_id (FK)
- provider_id (FK)
- model VARCHAR(128) -- Model used
- task_type VARCHAR(64) -- Task type
- input_tokens INT
- output_tokens INT
- latency_ms INT -- Response time
- estimated_cost_eur DECIMAL(10,6)
- users_userid (FK, nullable)
- created_at
```

#### `ai_fallback_chain`
Defines fallback order when primary provider fails.

```sql
- id (PK)
- instances_id (FK)
- task_type VARCHAR(64)
- provider_id (FK)
- fallback_order INT -- 0=primary, 1=secondary, etc.
```

## PHP Classes

### Core Interfaces & Value Objects

**`LlmProviderInterface`** - Abstract interface all providers implement:
```php
public function chatCompletion(array $messages, array $options = []): LlmResponse;
public function isAvailable(): bool;
public function listModels(): array;
public function getProviderName(): string;
public function getEstimatedCost(int $inputTokens, int $outputTokens): float;
public function supportsVision(): bool;
public function supportsStreaming(): bool;
```

**`LlmResponse`** - Standardized response value object:
```php
public string $content;
public string $model;
public int $inputTokens;
public int $outputTokens;
public int $latencyMs;
public string $finishReason;
```

### Provider Adapters

Located in `/src/services/AI/Providers/`:

- **`ClaudeAdapter`** - Anthropic Claude (Messages API v1)
- **`OpenAiAdapter`** - OpenAI (Chat Completions API)
- **`GeminiAdapter`** - Google Gemini (GenerativeLanguage API)
- **`MistralAdapter`** - Mistral AI
- **`OllamaAdapter`** - Self-hosted Ollama (extends OpenAiAdapter)
- **`OpenAiCompatibleAdapter`** - Generic OpenAI-format API wrapper

### Core Services

**`AiProviderRegistry`** - Factory and registry for providers:
```php
$registry = new AiProviderRegistry($db);

// Get specific provider
$provider = $registry->get('claude_main');

// Get default for instance
$provider = $registry->getDefault($instanceId);

// Get for task with fallback
$provider = $registry->getWithFallback('asset_lookup', $instanceId);

// List available providers
$providers = $registry->listAvailable($instanceId);

// Encryption utilities
$encrypted = $registry->encryptApiKey($plaintext);
$plaintext = $registry->decryptApiKey($encrypted);
```

**`AiRequestHandler`** - Main entry point for all AI requests:
```php
$handler = new AiRequestHandler($db, $registry, $tracker);

// Process single request with fallback
$response = $handler->processRequest(
    taskType: 'asset_lookup',
    prompt: 'Find specs for Canon EOS R5',
    options: ['max_tokens' => 1000],
    userId: 123,
    instanceId: 1
);

// Process with automatic retries
$response = $handler->processRequestWithRetry(
    taskType: 'invoice_scan',
    prompt: $invoicePdf,
    maxRetries: 3
);

// Test provider connectivity
$result = $handler->testProvider($providerId, $instanceId);
```

**`AiUsageTracker`** - Track and analyze AI usage:
```php
$tracker = new AiUsageTracker($db);

// Log a single request
$tracker->logUsage(
    providerId: 1,
    model: 'gpt-4-turbo',
    taskType: 'asset_lookup',
    inputTokens: 150,
    outputTokens: 250,
    latencyMs: 1234,
    costEur: 0.00523,
    userId: 123,
    instanceId: 1
);

// Get usage summary
$summary = $tracker->getUsageSummary($instanceId, period: 'month');
// Returns: by_provider, total_cost_eur, total_calls, avg_cost_per_call

// Get budget status
$budget = $tracker->getMonthlyBudgetStatus($instanceId);
// Returns: budget_limit_eur, current_month_cost_eur, percentage_used, remaining_eur

// Get provider-specific stats
$stats = $tracker->getProviderStats($providerId, $instanceId, 'month');
```

**`AiInitializer`** - Bootstrap helper:
```php
// In your application bootstrap:
AiInitializer::init($db);

// Get request handler for current context
$handler = AiInitializer::getRequestHandler($db, $instanceId, $userId);

// Get default provider
$provider = AiInitializer::getDefaultProvider($db, $instanceId);
```

## API Endpoints

All endpoints require authentication and appropriate permissions:
- `AI:VIEW` - View providers and usage
- `AI:CONFIGURE` - Manage providers and routing

### `GET /api/ai/providers`
List all configured providers for instance.

Response:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Claude Main",
      "provider_type": "claude",
      "api_key_encrypted": "***ENCRYPTED***",
      "default_model": "claude-haiku-4-5-20251001",
      "is_active": 1,
      "is_default": 1,
      "config": null,
      "created_at": "2026-03-18T10:00:00Z"
    }
  ]
}
```

### `POST /api/ai/providers`
Create or update a provider.

Request:
```json
{
  "name": "OpenAI GPT-4",
  "provider_type": "openai",
  "api_key": "sk-...",
  "default_model": "gpt-4-turbo",
  "is_active": 1,
  "is_default": 0,
  "config": {
    "temperature": 0.7,
    "request_timeout_seconds": 60
  }
}
```

### `POST /api/ai/provider_test`
Test provider connectivity.

Request:
```json
{ "provider_id": 1 }
```

Response:
```json
{
  "success": true,
  "message": "Provider connection successful",
  "model": "gpt-4-turbo",
  "response": "Hello! I'm working..."
}
```

### `POST /api/ai/provider_delete`
Remove a provider.

Request:
```json
{ "provider_id": 1 }
```

### `GET /api/ai/task_routing`
List all task routing rules.

Response:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "task_type": "asset_lookup",
      "provider_id": 1,
      "model_override": null,
      "priority": 0,
      "provider": {
        "id": 1,
        "name": "Claude Main",
        "provider_type": "claude"
      }
    }
  ]
}
```

### `POST /api/ai/task_routing`
Create/update task routing.

Request:
```json
{
  "task_type": "invoice_scan",
  "provider_id": 2,
  "model_override": "gpt-4-vision",
  "priority": 0
}
```

### `GET /api/ai/usage?period=month&provider_id={id}`
Get usage statistics and budget status.

Response:
```json
{
  "success": true,
  "data": {
    "summary": {
      "period": "month",
      "by_provider": [...],
      "total_cost_eur": 12.34,
      "total_calls": 456,
      "avg_cost_per_call": 0.027
    },
    "monthly_budget": {
      "budget_limit_eur": 100,
      "current_month_cost_eur": 12.34,
      "percentage_used": 12.34,
      "remaining_eur": 87.66
    },
    "by_task_type": [
      {
        "task_type": "asset_lookup",
        "calls": 100,
        "total_cost_eur": 2.50
      }
    ]
  }
}
```

### `GET /api/ai/models?provider_id={id}`
List available models for a provider.

Response:
```json
{
  "success": true,
  "provider": "OpenAI",
  "models": [
    "gpt-4-turbo",
    "gpt-4",
    "gpt-3.5-turbo"
  ]
}
```

## Web UI

Accessible at `/admin/ai_settings.php` (or configured path).

### Providers Tab
- View all providers with status indicators (green=connected, red=error)
- Test provider connectivity
- Add/edit providers with encrypted API key storage
- Set default provider
- Delete providers (if not default)

### Task Routing Tab
- View current task-to-provider mappings
- Configure task-specific providers
- Set model overrides
- Configure priority for fallback chains

### Costs & Budget Tab
- Monthly budget status with visual progress bar
- Usage breakdown by provider, model, task type
- Cost per call analysis
- Monthly cost vs. budget comparison

### Fallback Tab
- Configure backup providers for each task
- Visual display of fallback chains

## Integration with Existing Services

### Migration from Direct Claude Calls

**Before:**
```php
$claude = new ClaudeService($db, $instanceId);
$response = $claude->ask('asset_lookup', $systemPrompt, $userMessage);
```

**After:**
```php
$handler = AiInitializer::getRequestHandler($db, $instanceId, $userId);
$response = $handler->processRequest(
    'asset_lookup',
    $userMessage,
    ['system' => $systemPrompt]
);
// response is now LlmResponse instead of raw array
```

The request handler automatically:
1. Routes to the configured provider for this task type
2. Falls back to other providers if primary fails
3. Logs usage and cost
4. Retries on transient failures

### For AiActionQueueService

No changes needed. The service queues actions. When actions execute, use the new request handler instead of ClaudeService.

### For AiAssetLookupService

Update to use request handler:
```php
// Instead of: $this->claudeService->ask(...)
$handler = AiInitializer::getRequestHandler($this->db, $instanceId);
$response = $handler->processRequest('asset_lookup', $prompt);
$text = $response->content; // Same content, cleaner API
```

## Cost Estimation

All adapters provide accurate cost estimation based on:
- Provider's official token pricing (USD per million tokens)
- Converted to EUR at build-time rate
- Includes both input and output tokens

Example pricing (as of 2026-03-18):
- Claude Haiku: $0.80/$4.00 per 1M tokens (input/output)
- GPT-4 Turbo: $10.00/$30.00 per 1M tokens
- Gemini 1.5 Flash: $0.075/$0.30 per 1M tokens
- Ollama: €0.00 (self-hosted)

Pricing can be updated in provider adapter `COST_MAP` constants.

## Security

### API Key Encryption

API keys are encrypted at rest using AES-256-GCM:
- Encryption key from `AI_ENCRYPTION_KEY` environment variable
- Falls back to `APP_SECRET` if not set
- Keys are never logged or displayed in plaintext
- Web UI shows `***ENCRYPTED***` instead of actual key

### Permissions

Two permissions control access:
- `AI:VIEW` - View providers, usage, models
- `AI:CONFIGURE` - Add/edit/delete providers, configure routing

Both should be restricted to administrators.

## Troubleshooting

### Provider Connection Failed

1. Check API key is valid in provider settings
2. Click "Test" button to diagnose connection
3. Verify network connectivity to API endpoint
4. Check provider's service status page

### High API Costs

1. Review usage statistics in Costs tab
2. Check task routing - may be routing to expensive provider
3. Set `model_override` to use cheaper model
4. Implement rate limiting or caching upstream

### Stuck in Fallback

If all providers in fallback chain fail:
1. First provider error is logged to application logs
2. Request handler throws exception
3. Check logs for specific API error messages
4. Test each provider individually

## Future Enhancements

Potential additions for I4+:

1. **Streaming Support** - Stream responses for real-time UI
2. **Caching Layer** - Redis-backed response caching
3. **Rate Limiting** - Per-provider request throttling
4. **Analytics Dashboard** - Advanced charts and reports
5. **Budget Alerts** - Email notifications when nearing limits
6. **Model Fine-tuning** - Store custom models in config
7. **Batch Processing** - Queue requests for off-peak execution
8. **Vision Pipeline** - Dedicated image/PDF processors
