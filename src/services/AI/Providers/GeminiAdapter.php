<?php

/**
 * GeminiAdapter - Google Gemini Provider Implementation
 *
 * Supports Gemini models (Gemini Pro, Gemini 1.5, etc.)
 */
class GeminiAdapter implements LlmProviderInterface
{
    private const API_URL_TEMPLATE = 'https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent';

    private const COST_MAP = [
        'gemini-1.5-flash' => ['input' => 0.075, 'output' => 0.30],
        'gemini-1.5-pro' => ['input' => 1.25, 'output' => 5.00],
        'gemini-pro' => ['input' => 0.50, 'output' => 1.50],
    ];

    private const AVAILABLE_MODELS = [
        'gemini-1.5-pro',
        'gemini-1.5-flash',
        'gemini-pro',
    ];

    public function __construct(
        private string $apiKey,
        private string $defaultModel = 'gemini-1.5-flash',
    ) {}

    public function chatCompletion(array $messages, array $options = []): LlmResponse
    {
        $startTime = microtime(true);
        $model = $options['model'] ?? $this->defaultModel;

        // Convert OpenAI format messages to Gemini format
        $geminiMessages = $this->convertMessages($messages);

        $body = [
            'contents' => $geminiMessages,
            'generationConfig' => [
                'maxOutputTokens' => $options['max_tokens'] ?? 2048,
            ],
        ];

        if (isset($options['temperature'])) {
            $body['generationConfig']['temperature'] = $options['temperature'];
        }

        $response = $this->callApi($model, $body);
        $latencyMs = (int)((microtime(true) - $startTime) * 1000);

        if (!$response || !isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            throw new Exception('Invalid response from Gemini API');
        }

        $inputTokens = $response['usageMetadata']['promptTokenCount'] ?? 0;
        $outputTokens = $response['usageMetadata']['candidatesTokenCount'] ?? 0;

        return new LlmResponse(
            content: $response['candidates'][0]['content']['parts'][0]['text'],
            model: $model,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            latencyMs: $latencyMs,
            finishReason: $response['candidates'][0]['finishReason'] ?? 'STOP',
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
        return 'Gemini';
    }

    public function getEstimatedCost(int $inputTokens, int $outputTokens): float
    {
        $costs = self::COST_MAP[$this->defaultModel] ?? ['input' => 0.075, 'output' => 0.30];
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

    private function convertMessages(array $messages): array
    {
        $contents = [];

        foreach ($messages as $msg) {
            $role = $msg['role'] === 'user' ? 'user' : 'model';
            $content = $msg['content'] ?? '';

            // Handle system message by prepending to first user message
            if ($role === 'model' && is_string($content)) {
                $contents[] = [
                    'role' => $role,
                    'parts' => [['text' => $content]],
                ];
            } elseif (is_array($content)) {
                // Handle multi-part messages
                $parts = [];
                foreach ($content as $part) {
                    if (($part['type'] ?? '') === 'text') {
                        $parts[] = ['text' => $part['text'] ?? ''];
                    } elseif (($part['type'] ?? '') === 'image') {
                        // Gemini expects inline_data format for images
                        if (isset($part['source']['data'])) {
                            $parts[] = [
                                'inlineData' => [
                                    'mimeType' => $part['source']['media_type'] ?? 'image/jpeg',
                                    'data' => $part['source']['data'],
                                ],
                            ];
                        }
                    }
                }
                if (!empty($parts)) {
                    $contents[] = [
                        'role' => $role,
                        'parts' => $parts,
                    ];
                }
            } else {
                $contents[] = [
                    'role' => $role,
                    'parts' => [['text' => (string)$content]],
                ];
            }
        }

        return $contents;
    }

    private function callApi(string $model, array $body): ?array
    {
        $url = str_replace('{model}', $model, self::API_URL_TEMPLATE);
        $url .= '?key=' . urlencode($this->apiKey);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($body),
        ]);

        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$raw) {
            error_log("Gemini API error: HTTP {$httpCode}");
            return null;
        }

        $data = json_decode($raw, true);
        return $data ?: null;
    }
}
