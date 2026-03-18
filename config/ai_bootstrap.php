<?php
/**
 * AI System Bootstrap
 *
 * Include this in your application bootstrap to initialize the AI provider system.
 * Add to your main app initialization file (e.g., index.php, app.php):
 *
 *   require_once __DIR__ . '/config/ai_bootstrap.php';
 *   setupAiSystem($db);
 */

function setupAiSystem($db)
{
    // Load all AI service classes
    $basePath = __DIR__ . '/../src/services/AI';

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
    require_once $basePath . '/AiInitializer.php';

    // Initialize
    AiInitializer::init($db);

    // Create global helper functions for convenience
    if (!function_exists('getAiHandler')) {
        function getAiHandler($db, ?int $instanceId = null, ?int $userId = null)
        {
            $instanceId = $instanceId ?? (int)($_SESSION['instance_id'] ?? 1);
            $userId = $userId ?? (int)($_SESSION['user_id'] ?? 0);
            return AiInitializer::getRequestHandler($db, $instanceId, $userId);
        }
    }

    if (!function_exists('getAiProvider')) {
        function getAiProvider($db, string $taskType, ?int $instanceId = null)
        {
            $instanceId = $instanceId ?? (int)($_SESSION['instance_id'] ?? 1);
            $registry = new AiProviderRegistry($db);
            return $registry->getForTask($taskType, $instanceId);
        }
    }

    if (!function_exists('getAiUsageTracker')) {
        function getAiUsageTracker($db)
        {
            return new AiUsageTracker($db);
        }
    }
}

/**
 * Example of what your app bootstrap should include:
 *
 *   // Database connection (already exists)
 *   $db = ... your MeekroDB instance ...
 *
 *   // Load AI system
 *   require_once __DIR__ . '/config/ai_bootstrap.php';
 *   setupAiSystem($db);
 *
 *   // Now you can use helper functions anywhere:
 *   $handler = getAiHandler($db);
 *   $response = $handler->processRequest(
 *       'asset_lookup',
 *       'Find specs for...'
 *   );
 */
