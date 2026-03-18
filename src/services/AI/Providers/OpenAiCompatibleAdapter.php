<?php

/**
 * OpenAiCompatibleAdapter - Generic OpenAI-Compatible API Provider
 *
 * Handles any API that follows the OpenAI chat.completions format:
 * - Deepseek
 * - Together AI
 * - Perplexity
 * - Custom private deployments
 * - etc.
 */
class OpenAiCompatibleAdapter implements LlmProviderInterface
{
    private const COST_MAP = [
        'default' => ['input' => 0.50, 'output' => 1.50],
    ];

    public function __construct(
        private string $apiKey,
        private string $baseUrl,
        private string $defaultModel = 'default',
        private array $config = [],
    ) {}

    public function chatCompletion(array $messages, array $options = []): LlmResponse
    {
        $startTime = microtime(true);

        $body = [
            'model' => $options['model'] ?? $this->defaultModel,
            'messages' => $messages,
            'max_tokens' => $options['max_tokens'] ?? 2048,
        ];

        if (isset($options['temperature'])) {
            $body['temperature'] = $options['temperature'];
        }
        if (isset($options['top_p'])) {
            $body['top_p'] = $options['top_p'];
        }

        // Allow custom request parameters from config
        if (!empty($this->config['extra_params']) && is_array($this->config['extra_params'])) {
            $body = array_merge($body, $this->config['extra_params']);
        }

        $response = $this->callApi($body);
        $latencyMs = (int)((microtime(true) - $startTime) * 1000);

        if (!$response || !isset($response['choices'][0]['message']['content'])) {
            throw new Exception('Invalid response from OpenAI-compatible API');
        }

        $inputTokens = $response['usage']['prompt_tokens'] ?? 0;
        $outputTokens = $response['usage']['completion_tokens'] ?? 0;

        return new LlmResponse(
            content: $response['choices'][0]['message']['content'],
            model: $body['model'],
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            latencyMs: $latencyMs,
            finishReason: $response['choices'][0]['finish_reason'] ?? 'stop',
            rawResponse: $response,
        );
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey) && !empty($this->baseUrl);
    }

    public function listModels(): array
    {
        // Try to fetch from API if supported
        $models = $this->config['models'] ?? [];
        return is_array($models) && !empty($models) ? $models : [$this->defaultModel];
    }

    public function getProviderName(): string
    {
        return $this->config['display_name'] ?? 'OpenAI Compatible';
    }

    public function getEstimatedCost(int $inputTokens, int $outputTokens): float
    {
        $costs = self::COST_MAP['default'];
        if (isset($this->config['cost_per_mtokens_input']) && isset($this->config['cost_per_mtokens_output'])) {
            $costs = [
                'input' => (float)$this->config['cost_per_mtokens_input'],
                'output' => (float)$this->config['cost_per_mtokens_output'],
            ];
        }
        return ($inputTokens * $costs['input'] + $outputTokens * $costs['output']) / 1_000_000;
    }

    public function supportsVision(): bool
    {
        return $this->config['supports_vision'] ?? false;
    }

    public function supportsStreaming(): bool
    {
        return $this->config['supports_streaming'] ?? true;
    }

    // ── Private Methods ──

    private function callApi(array $body): ?array
    {
        $url = rtrim($this->baseUrl, '/') . '/v1/chat/completions';

        $headers = [
            'Content-Type: application/json',
        ];

        // Support custom auth headers
        if (isset($this->config['auth_header_name'])) {
            $headers[] = $this->config['auth_header_name'] . ': ' . $this->apiKey;
        } else {
            // Default to Bearer token
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }

        // Add any custom headers
        if (isset($this->config['extra_headers']) && is_array($this->config['extra_headers'])) {
            $headers = array_merge($headers, $this->config['extra_headers']);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->config['request_timeout_seconds'] ?? 60,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($body),
        ]);

        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$raw) {
            error_log("OpenAI-compatible API error: HTTP {$httpCode} at {$url}");
            return null;
        }

        $data = json_decode($raw, true);
        return $data ?: null;
    }
}
