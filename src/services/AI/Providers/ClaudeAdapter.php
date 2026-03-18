<?php

/**
 * ClaudeAdapter - Anthropic Claude Provider Implementation
 *
 * Wraps the Anthropic Messages API (v1) with support for:
 * - Text and multi-modal messages (images, PDFs)
 * - Multiple Claude models (Haiku, Sonnet, Opus)
 * - Token counting and cost estimation
 */
class ClaudeAdapter implements LlmProviderInterface
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    private const COST_MAP = [
        'claude-haiku-4-5-20251001' => ['input' => 0.80, 'output' => 4.00],
        'claude-sonnet-4-6' => ['input' => 3.00, 'output' => 15.00],
        'claude-opus-4-6' => ['input' => 15.00, 'output' => 75.00],
    ];

    private const AVAILABLE_MODELS = [
        'claude-opus-4-6',
        'claude-sonnet-4-6',
        'claude-haiku-4-5-20251001',
    ];

    public function __construct(
        private string $apiKey,
        private string $defaultModel = 'claude-haiku-4-5-20251001',
    ) {}

    public function chatCompletion(array $messages, array $options = []): LlmResponse
    {
        $startTime = microtime(true);

        $body = [
            'model' => $options['model'] ?? $this->defaultModel,
            'max_tokens' => $options['max_tokens'] ?? 2048,
            'system' => $options['system'] ?? '',
            'messages' => $messages,
        ];

        if (isset($options['temperature'])) {
            $body['temperature'] = $options['temperature'];
        }
        if (isset($options['top_p'])) {
            $body['top_p'] = $options['top_p'];
        }

        $response = $this->callApi($body);
        $latencyMs = (int)((microtime(true) - $startTime) * 1000);

        if (!$response || !isset($response['content'][0]['text'])) {
            throw new Exception('Invalid response from Claude API');
        }

        $inputTokens = $response['usage']['input_tokens'] ?? 0;
        $outputTokens = $response['usage']['output_tokens'] ?? 0;

        return new LlmResponse(
            content: $response['content'][0]['text'],
            model: $body['model'],
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            latencyMs: $latencyMs,
            finishReason: $response['stop_reason'] ?? 'stop',
            rawResponse: $response,
        );
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    public function listModels(): array
    {
        return self::AVAILABLE_MODELS;
    }

    public function getProviderName(): string
    {
        return 'Claude';
    }

    public function getEstimatedCost(int $inputTokens, int $outputTokens): float
    {
        $costs = self::COST_MAP[$this->defaultModel] ?? ['input' => 0.80, 'output' => 4.00];
        return ($inputTokens * $costs['input'] + $outputTokens * $costs['output']) / 1_000_000;
    }

    public function supportsVision(): bool
    {
        return true;
    }

    public function supportsStreaming(): bool
    {
        return false; // For now, we implement non-streaming
    }

    // ── Private Methods ──

    private function callApi(array $body): ?array
    {
        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: ' . self::API_VERSION,
            ],
            CURLOPT_POSTFIELDS => json_encode($body),
        ]);

        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$raw) {
            error_log("Claude API error: HTTP {$httpCode}");
            return null;
        }

        $data = json_decode($raw, true);
        return $data ?: null;
    }
}
