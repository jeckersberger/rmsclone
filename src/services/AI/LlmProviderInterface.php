<?php

/**
 * LlmProviderInterface - Abstract interface for all LLM providers
 *
 * All provider adapters must implement this interface to ensure consistent
 * behavior across OpenAI, Claude, Gemini, Mistral, Ollama, etc.
 */
interface LlmProviderInterface
{
    /**
     * Send a chat completion request
     *
     * @param array $messages Array of message objects with 'role' and 'content'
     * @param array $options Additional options (temperature, max_tokens, top_p, etc.)
     * @return LlmResponse
     * @throws Exception on API error or network failure
     */
    public function chatCompletion(array $messages, array $options = []): LlmResponse;

    /**
     * Check if provider is available (API key valid, connectivity OK)
     *
     * @return bool
     */
    public function isAvailable(): bool;

    /**
     * List available models for this provider
     *
     * @return array List of model identifiers
     */
    public function listModels(): array;

    /**
     * Get human-readable provider name
     *
     * @return string (e.g., "OpenAI", "Claude", "Gemini")
     */
    public function getProviderName(): string;

    /**
     * Estimate cost in EUR for a request
     *
     * @param int $inputTokens
     * @param int $outputTokens
     * @return float Cost in EUR
     */
    public function getEstimatedCost(int $inputTokens, int $outputTokens): float;

    /**
     * Whether this provider supports vision/image input
     *
     * @return bool
     */
    public function supportsVision(): bool;

    /**
     * Whether this provider supports streaming responses
     *
     * @return bool
     */
    public function supportsStreaming(): bool;
}
