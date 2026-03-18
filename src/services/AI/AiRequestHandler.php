<?php

/**
 * AiRequestHandler - Main entry point for all AI requests
 *
 * Handles:
 * - Provider selection and task routing
 * - Fallback chain execution
 * - Usage logging and cost tracking
 * - Error handling and retries
 */
class AiRequestHandler
{
    public function __construct(
        private $db,
        private AiProviderRegistry $registry,
        private AiUsageTracker $tracker,
    ) {}

    /**
     * Process an AI request with automatic fallback and logging
     *
     * @param string $taskType Identifier for the task (e.g., 'asset_lookup', 'invoice_scan')
     * @param string|array $prompt The user's prompt (can be simple text or structured message array)
     * @param array $options Additional options: model_override, max_tokens, temperature, etc.
     * @param int $userId User who triggered the request (0 for automated)
     * @param int $instanceId Instance ID for multi-tenancy
     * @return LlmResponse
     * @throws Exception if all providers fail
     */
    public function processRequest(
        string $taskType,
        string|array $prompt,
        array $options = [],
        int $userId = 0,
        int $instanceId = 1,
    ): LlmResponse {
        $startTime = microtime(true);

        // Normalize prompt to message format
        $messages = $this->normalizePrompt($prompt, $options);

        // Get provider with fallback chain
        try {
            $provider = $this->registry->getWithFallback($taskType, $instanceId);
        } catch (Exception $e) {
            throw new Exception("No provider available for task '{$taskType}': {$e->getMessage()}");
        }

        // Get provider info from database for logging
        $this->db->where('instances_id', $instanceId);
        $this->db->where('provider_type', 'openai');
        $providerRecord = $this->db->getOne('ai_providers') ?: ['id' => 0];

        try {
            // Execute the request
            $response = $provider->chatCompletion($messages, $options);

            // Log usage
            $this->tracker->logUsage(
                providerId: $providerRecord['id'],
                model: $response->model,
                taskType: $taskType,
                inputTokens: $response->inputTokens,
                outputTokens: $response->outputTokens,
                latencyMs: $response->latencyMs,
                costEur: $provider->getEstimatedCost($response->inputTokens, $response->outputTokens),
                userId: $userId ?: null,
                instanceId: $instanceId,
            );

            return $response;
        } catch (Exception $e) {
            error_log("AI Request failed: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Process request with retry logic
     *
     * Attempts the request up to maxRetries times if provider fails.
     */
    public function processRequestWithRetry(
        string $taskType,
        string|array $prompt,
        array $options = [],
        int $userId = 0,
        int $instanceId = 1,
        int $maxRetries = 3,
    ): LlmResponse {
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                return $this->processRequest($taskType, $prompt, $options, $userId, $instanceId);
            } catch (Exception $e) {
                $lastException = $e;
                if ($attempt < $maxRetries) {
                    // Exponential backoff: 1s, 2s, 4s
                    sleep(2 ** ($attempt - 1));
                }
            }
        }

        throw new Exception("Request failed after {$maxRetries} attempts: {$lastException->getMessage()}");
    }

    /**
     * Normalize different prompt formats to standard message format
     *
     * Supports:
     * - Simple string: "Your prompt here"
     * - Array of messages: [['role' => 'user', 'content' => '...'], ...]
     * - Options-included: $options['system'] = "System prompt"
     */
    private function normalizePrompt(string|array $prompt, array &$options): array
    {
        if (is_array($prompt)) {
            // Already in message format
            return $prompt;
        }

        // Simple string prompt
        $messages = [];

        // Add system prompt if provided
        if (!empty($options['system'])) {
            $messages[] = [
                'role' => 'system',
                'content' => $options['system'],
            ];
            unset($options['system']);
        }

        // Add user prompt
        $messages[] = [
            'role' => 'user',
            'content' => $prompt,
        ];

        return $messages;
    }

    /**
     * Test a provider connection
     *
     * @return array ['success' => bool, 'message' => string, 'model' => string|null]
     */
    public function testProvider(int $providerId, int $instanceId): array
    {
        try {
            // Load provider
            $this->db->where('id', $providerId);
            $this->db->where('instances_id', $instanceId);
            $providerRecord = $this->db->getOne('ai_providers');

            if (!$providerRecord) {
                return ['success' => false, 'message' => 'Provider not found'];
            }

            // Instantiate provider
            $registry = new AiProviderRegistry($this->db);
            $provider = $registry->loadProvider($providerRecord);

            // Test availability
            if (!$provider->isAvailable()) {
                return [
                    'success' => false,
                    'message' => 'Provider not available (connectivity or auth issue)',
                ];
            }

            // Try a simple request
            $testPrompt = 'Hello, are you working?';
            $response = $provider->chatCompletion(
                [['role' => 'user', 'content' => $testPrompt]],
                ['max_tokens' => 50],
            );

            return [
                'success' => true,
                'message' => 'Provider connection successful',
                'model' => $response->model,
                'response' => substr($response->content, 0, 100),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ];
        }
    }
}

// Add method to registry to make it public (for testProvider)
class AiProviderRegistry
{
    // ... existing code ...

    public function loadProvider(array $record): LlmProviderInterface
    {
        $cacheKey = $record['id'];
        if (isset($this->instances[$cacheKey])) {
            return $this->instances[$cacheKey];
        }

        $apiKey = '';
        if (!empty($record['api_key_encrypted'])) {
            $apiKey = $this->decryptApiKey($record['api_key_encrypted']);
        }

        $config = [];
        if (!empty($record['config'])) {
            $config = is_string($record['config'])
                ? json_decode($record['config'], true) ?: []
                : $record['config'];
        }

        $provider = match ($record['provider_type']) {
            'claude' => new ClaudeAdapter($apiKey, $record['default_model']),
            'openai' => new OpenAiAdapter($apiKey, $record['default_model']),
            'gemini' => new GeminiAdapter($apiKey, $record['default_model']),
            'mistral' => new MistralAdapter($apiKey, $record['default_model']),
            'ollama' => new OllamaAdapter(
                $record['base_url'] ?? 'http://localhost:11434',
                $record['default_model'],
            ),
            'openai_compatible' => new OpenAiCompatibleAdapter(
                $apiKey,
                $record['base_url'] ?? '',
                $record['default_model'],
                $config,
            ),
            default => throw new Exception("Unknown provider type: {$record['provider_type']}"),
        };

        $this->instances[$cacheKey] = $provider;
        return $provider;
    }
}
