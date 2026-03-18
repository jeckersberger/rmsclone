<?php

/**
 * OpenAiAdapter - OpenAI API Provider Implementation
 *
 * Supports GPT-4, GPT-4 Turbo, GPT-3.5 Turbo, and other OpenAI models.
 * Can also be used as base for other OpenAI-compatible APIs.
 */
class OpenAiAdapter implements LlmProviderInterface
{
    private const API_URL = 'https://api.openai.com/v1/chat/completions';

    private const COST_MAP = [
        'gpt-4-turbo' => ['input' => 10.00, 'output' => 30.00],
        'gpt-4' => ['input' => 30.00, 'output' => 60.00],
        'gpt-3.5-turbo' => ['input' => 0.50, 'output' => 1.50],
        'gpt-4o' => ['input' => 5.00, 'output' => 15.00],
    ];

    private const AVAILABLE_MODELS = [
        'gpt-4-turbo',
        'gpt-4',
        'gpt-4o',
        'gpt-3.5-turbo',
    ];

    public function __construct(
        private string $apiKey,
        private string $defaultModel = 'gpt-4-turbo',
        private ?string $baseUrl = null,
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

        $response = $this->callApi($body);
        $latencyMs = (int)((microtime(true) - $startTime) * 1000);

        if (!$response || !isset($response['choices'][0]['message']['content'])) {
            throw new Exception('Invalid response from OpenAI API');
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
        return !empty($this->apiKey);
    }

    public function listModels(): array
    {
        return self::AVAILABLE_MODELS;
    }

    public function getProviderName(): string
    {
        return 'OpenAI';
    }

    public function getEstimatedCost(int $inputTokens, int $outputTokens): float
    {
        $costs = self::COST_MAP[$this->defaultModel] ?? ['input' => 10.00, 'output' => 30.00];
        return ($inputTokens * $costs['input'] + $outputTokens * $costs['output']) / 1_000_000;
    }

    public function supportsVision(): bool
    {
        return true;
    }

    public function supportsStreaming(): bool
    {
        return true;
    }

    // ── Private Methods ──

    protected function getApiUrl(): string
    {
        return $this->baseUrl ? rtrim($this->baseUrl, '/') . '/v1/chat/completions' : self::API_URL;
    }

    private function callApi(array $body): ?array
    {
        $ch = curl_init($this->getApiUrl());
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($body),
        ]);

        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$raw) {
            error_log("OpenAI API error: HTTP {$httpCode}");
            return null;
        }

        $data = json_decode($raw, true);
        return $data ?: null;
    }
}
