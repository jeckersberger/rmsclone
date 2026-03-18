<?php

/**
 * AiProviderRegistry - Factory and Registry for LLM Providers
 *
 * Manages provider instances, selection, and fallback chains.
 * Acts as a service locator for the entire AI system.
 */
class AiProviderRegistry
{
    private array $providers = [];
    private array $instances = [];

    public function __construct(private $db) {}

    /**
     * Register a provider instance
     */
    public function register(string $name, LlmProviderInterface $provider): void
    {
        $this->providers[$name] = $provider;
    }

    /**
     * Get a provider by name
     *
     * @throws Exception if provider not found
     */
    public function get(string $name): LlmProviderInterface
    {
        if (!isset($this->providers[$name])) {
            throw new Exception("Provider '{$name}' not registered");
        }
        return $this->providers[$name];
    }

    /**
     * Get the default provider for an instance
     *
     * @throws Exception if no default provider is configured
     */
    public function getDefault(int $instanceId): LlmProviderInterface
    {
        // Query database for default provider
        $this->db->where('instances_id', $instanceId);
        $this->db->where('is_default', 1);
        $this->db->where('is_active', 1);
        $provider = $this->db->getOne('ai_providers');

        if (!$provider) {
            throw new Exception("No default AI provider configured for instance {$instanceId}");
        }

        return $this->loadProvider($provider);
    }

    /**
     * Get provider for a specific task type
     *
     * Uses task_routing table, falls back to default if not configured
     */
    public function getForTask(string $taskType, int $instanceId): LlmProviderInterface
    {
        // Check if task has explicit routing
        $this->db->where('instances_id', $instanceId);
        $this->db->where('task_type', $taskType);
        $this->db->orderBy('priority', 'ASC');
        $routing = $this->db->getOne('ai_task_routing');

        if ($routing && isset($routing['provider_id'])) {
            $this->db->where('id', $routing['provider_id']);
            $provider = $this->db->getOne('ai_providers');
            if ($provider) {
                return $this->loadProvider($provider);
            }
        }

        // Fall back to default
        return $this->getDefault($instanceId);
    }

    /**
     * Get provider with automatic fallback chain
     *
     * If primary provider fails, tries fallback chain in order
     */
    public function getWithFallback(string $taskType, int $instanceId): LlmProviderInterface
    {
        // Get fallback chain for this task
        $this->db->where('instances_id', $instanceId);
        $this->db->where('task_type', $taskType);
        $this->db->orderBy('fallback_order', 'ASC');
        $chain = $this->db->get('ai_fallback_chain') ?: [];

        if (empty($chain)) {
            // No fallback chain, just return task-based routing
            return $this->getForTask($taskType, $instanceId);
        }

        // Try each provider in chain
        foreach ($chain as $fallback) {
            $this->db->where('id', $fallback['provider_id']);
            $this->db->where('is_active', 1);
            $provider = $this->db->getOne('ai_providers');

            if ($provider) {
                $instance = $this->loadProvider($provider);
                if ($instance->isAvailable()) {
                    return $instance;
                }
            }
        }

        // All providers unavailable, return first anyway (will error during use)
        if (!empty($chain)) {
            $this->db->where('id', $chain[0]['provider_id']);
            $provider = $this->db->getOne('ai_providers');
            if ($provider) {
                return $this->loadProvider($provider);
            }
        }

        throw new Exception("No available providers for task '{$taskType}' in fallback chain");
    }

    /**
     * List all active providers for an instance
     *
     * @return array List of provider records
     */
    public function listAvailable(int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('is_active', 1);
        $this->db->orderBy('created_at', 'DESC');
        return $this->db->get('ai_providers') ?: [];
    }

    /**
     * Load a provider instance from database record
     *
     * @throws Exception if provider type unknown
     */
    private function loadProvider(array $record): LlmProviderInterface
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

    // ── Encryption/Decryption ──

    public function encryptApiKey(string $plaintext): string
    {
        $key = $this->getEncryptionKey();
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($plaintext, 'AES-256-GCM', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return base64_encode($iv . $tag . $encrypted);
    }

    private function decryptApiKey(string $encrypted): string
    {
        try {
            $key = $this->getEncryptionKey();
            $data = base64_decode($encrypted, true);
            if ($data === false) return '';

            $iv = substr($data, 0, 16);
            $tag = substr($data, 16, 16);
            $ciphertext = substr($data, 32);

            $plaintext = openssl_decrypt($ciphertext, 'AES-256-GCM', $key, OPENSSL_RAW_DATA, $iv, $tag);
            return $plaintext ?: '';
        } catch (Exception $e) {
            error_log("Error decrypting API key: {$e->getMessage()}");
            return '';
        }
    }

    private function getEncryptionKey(): string
    {
        // Try to get from environment variable
        $key = getenv('AI_ENCRYPTION_KEY');
        if ($key) {
            return substr(hash('sha256', $key, true), 0, 32);
        }

        // Fall back to app secret (should exist)
        $appSecret = getenv('APP_SECRET');
        if (!$appSecret) {
            throw new Exception('Neither AI_ENCRYPTION_KEY nor APP_SECRET is set');
        }

        return substr(hash('sha256', $appSecret, true), 0, 32);
    }
}
