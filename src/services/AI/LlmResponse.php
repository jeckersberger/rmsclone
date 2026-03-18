<?php

/**
 * LlmResponse - Value Object for LLM API responses
 *
 * Standardized response format from any LLM provider adapter.
 */
class LlmResponse
{
    public function __construct(
        public string $content,
        public string $model,
        public int $inputTokens,
        public int $outputTokens,
        public int $latencyMs,
        public string $finishReason = 'stop',
        public ?array $rawResponse = null,
    ) {}

    /**
     * Extract text content (handles both string and complex content blocks)
     */
    public static function extractText(?array $response): string
    {
        if (!$response) return '';

        // If response has 'content' field (Claude/OpenAI format)
        if (isset($response['content']) && is_array($response['content'])) {
            foreach ($response['content'] as $block) {
                if (($block['type'] ?? '') === 'text') {
                    return $block['text'] ?? '';
                }
            }
        }

        // If response has a direct 'text' field
        if (isset($response['text']) && is_string($response['text'])) {
            return $response['text'];
        }

        // If response has 'choices' field (OpenAI format)
        if (isset($response['choices'][0]['message']['content'])) {
            return $response['choices'][0]['message']['content'];
        }

        return '';
    }

    /**
     * Convert to array for serialization/logging
     */
    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'model' => $this->model,
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'latency_ms' => $this->latencyMs,
            'finish_reason' => $this->finishReason,
        ];
    }
}
