<?php

/**
 * MistralAdapter - Mistral AI Provider Implementation
 *
 * Supports Mistral models (Mistral Small, Medium, Large)
 */
class MistralAdapter implements LlmProviderInterface
{
    private const API_URL = 'https://api.mistral.ai/v1/chat/completions';

    private const COST_MAP = [
        'mistral-small' => ['input' => 0.14, 'output' => 0.42],
        'mistral-medium' => ['input' => 0.27, 'output' => 0.81],
        'mistral-large' => ['input' => 0.81, 'output' => 2.43],
    ];

    private const AVAILABLE_MODELS = [
        'mistral-large',
        'mistral-medium',
        'mistral-small',
    ];

    public function __construct(
        private string $apiKey,
        private string $defaultModel = 'mistral-small',
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
            throw new Exception('Invalid response from Mistral API');
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
        return 'Mistral';
    }

    public function getEstimatedCost(int $inputTokens, int $outputTokens): float
    {
        $costs = self::COST_MAP[$this->defaultModel] ?? ['input' => 0.14, 'output' => 0.42];
        return ($inputTokens * $costs['input'] + $outputTokens * $costs['output']) / 1_000_000;
    }

    public function supportsVision(): bool
    {
        return false;
    }

    public function supportsStreaming(): bool
    {
        return true;
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
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($body),
        ]);

        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$raw) {
            error_log("Mistral API error: HTTP {$httpCode}");
            return null;
        }

        $data = json_decode($raw, true);
        return $data ?: null;
    }
}
