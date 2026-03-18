<?php

/**
 * OllamaAdapter - Local Ollama API Provider Implementation
 *
 * Ollama provides a local, self-hosted alternative to cloud-based LLMs.
 * Uses OpenAI-compatible API format but with no authentication.
 *
 * Models are determined at runtime from the Ollama instance.
 * Cost is essentially free (no API charges), only compute cost.
 */
class OllamaAdapter extends OpenAiAdapter
{
    private const DEFAULT_COST_PER_M_TOKENS = 0.0; // No API cost, only compute

    public function __construct(
        private string $baseUrl = 'http://localhost:11434',
        private string $defaultModel = 'llama2',
    ) {
        // Don't call parent constructor - Ollama has no API key
        parent::__construct(
            apiKey: 'ollama-local',
            defaultModel: $defaultModel,
            baseUrl: $baseUrl,
        );
    }

    public function chatCompletion(array $messages, array $options = []): LlmResponse
    {
        // Reuse parent OpenAI implementation, just different URL/format
        return parent::chatCompletion($messages, $options);
    }

    public function isAvailable(): bool
    {
        // Test connectivity to Ollama instance
        $ch = curl_init($this->baseUrl . '/api/tags');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 2,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200;
    }

    public function listModels(): array
    {
        // Dynamically fetch available models from Ollama
        $ch = curl_init($this->baseUrl . '/api/tags');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);

        if (!$raw) return ['llama2']; // Fallback

        $data = json_decode($raw, true);
        $models = [];
        if (isset($data['models']) && is_array($data['models'])) {
            foreach ($data['models'] as $model) {
                if (isset($model['name'])) {
                    $models[] = $model['name'];
                }
            }
        }

        return !empty($models) ? $models : ['llama2'];
    }

    public function getProviderName(): string
    {
        return 'Ollama';
    }

    public function getEstimatedCost(int $inputTokens, int $outputTokens): float
    {
        // Ollama is self-hosted, no per-token cost
        return 0.0;
    }

    public function supportsVision(): bool
    {
        // Some models like llava support vision, but not all
        return false;
    }

    public function supportsStreaming(): bool
    {
        return true;
    }
}
