<?php

/**
 * AiInitializer - Bootstrap helper for AI system initialization
 *
 * Include this in your application bootstrap to set up the AI provider system.
 * This ensures all required services are available to the application.
 */
class AiInitializer
{
    /**
     * Initialize AI services
     *
     * Should be called early in application bootstrap, after database connection is established.
     */
    public static function init($db): void
    {
        // Load all required classes
        self::loadClasses();
    }

    /**
     * Get a configured request handler for the current request
     *
     * @param $db Database connection
     * @param int $instanceId Instance ID for multi-tenancy
     * @param int $userId Current user ID (for logging)
     * @return AiRequestHandler
     */
    public static function getRequestHandler($db, int $instanceId = 1, int $userId = 0): AiRequestHandler
    {
        $registry = new AiProviderRegistry($db);
        $tracker = new AiUsageTracker($db);

        return new AiRequestHandler($db, $registry, $tracker);
    }

    /**
     * Load all AI provider classes
     */
    private static function loadClasses(): void
    {
        $basePath = __DIR__;

        // Interfaces and value objects
        require_once $basePath . '/LlmProviderInterface.php';
        require_once $basePath . '/LlmResponse.php';

        // Provider adapters
        require_once $basePath . '/Providers/ClaudeAdapter.php';
        require_once $basePath . '/Providers/OpenAiAdapter.php';
        require_once $basePath . '/Providers/GeminiAdapter.php';
        require_once $basePath . '/Providers/MistralAdapter.php';
        require_once $basePath . '/Providers/OllamaAdapter.php';
        require_once $basePath . '/Providers/OpenAiCompatibleAdapter.php';

        // Core services
        require_once $basePath . '/AiProviderRegistry.php';
        require_once $basePath . '/AiUsageTracker.php';
        require_once $basePath . '/AiRequestHandler.php';
    }

    /**
     * Get default provider for an instance
     *
     * @throws Exception if no default provider is configured
     */
    public static function getDefaultProvider($db, int $instanceId = 1): LlmProviderInterface
    {
        $registry = new AiProviderRegistry($db);
        return $registry->getDefault($instanceId);
    }

    /**
     * Get provider for a specific task type
     */
    public static function getTaskProvider($db, string $taskType, int $instanceId = 1): LlmProviderInterface
    {
        $registry = new AiProviderRegistry($db);
        return $registry->getForTask($taskType, $instanceId);
    }
}
