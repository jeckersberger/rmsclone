<?php
/**
 * Example: Using the Multi-KI-Provider System
 *
 * This example shows how to integrate the new AI provider system
 * into existing MyRMS services.
 */

// ============================================================
// EXAMPLE 1: Simple AI Request
// ============================================================

use MyRMS\Services\AI\AiInitializer;

require_once __DIR__ . '/../src/services/AI/AiInitializer.php';

// Initialize AI system (typically done once during app bootstrap)
AiInitializer::init($db);

// Get request handler for current user/instance
$handler = AiInitializer::getRequestHandler(
    db: $db,
    instanceId: (int)$_SESSION['instance_id'],
    userId: (int)$_SESSION['user_id'] ?? 0
);

// Example: Asset lookup request
$response = $handler->processRequest(
    taskType: 'asset_lookup',
    prompt: 'Find technical specifications and pricing for Canon EOS R5',
    options: [
        'max_tokens' => 1000,
    ]
);

echo "Asset Info:\n";
echo $response->content . "\n";
echo "\nMetrics:\n";
echo "Model: {$response->model}\n";
echo "Tokens: {$response->inputTokens} input, {$response->outputTokens} output\n";
echo "Latency: {$response->latencyMs}ms\n";
echo "Estimated Cost: €" . number_format(
    $handler->registry->loadProvider(...)->getEstimatedCost(
        $response->inputTokens,
        $response->outputTokens
    ),
    6
) . "\n";

// ============================================================
// EXAMPLE 2: Request with Retries
// ============================================================

$response = $handler->processRequestWithRetry(
    taskType: 'invoice_scan',
    prompt: base64_encode(file_get_contents('/path/to/invoice.pdf')),
    options: [
        'max_tokens' => 2048,
    ],
    maxRetries: 3 // Try up to 3 times
);

// ============================================================
// EXAMPLE 3: Task-Specific Provider
// ============================================================

$registry = new AiProviderRegistry($db);

// Get provider for specific task (with fallback)
$provider = $registry->getWithFallback('asset_lookup', $instanceId);

// Or task-based routing
$provider = $registry->getForTask('asset_lookup', $instanceId);

// Direct provider call (if you need more control)
$response = $provider->chatCompletion(
    messages: [
        ['role' => 'system', 'content' => 'You are an expert...'],
        ['role' => 'user', 'content' => 'Your question...'],
    ],
    options: [
        'temperature' => 0.7,
        'max_tokens' => 1000,
    ]
);

echo "Response: {$response->content}\n";
echo "Used model: {$response->model}\n";

// ============================================================
// EXAMPLE 4: List Available Providers
// ============================================================

$providers = $registry->listAvailable($instanceId);

foreach ($providers as $provider) {
    echo "Provider: {$provider['name']} ({$provider['provider_type']})\n";
    echo "  Status: " . ($provider['is_active'] ? 'Active' : 'Inactive') . "\n";
    echo "  Default: " . ($provider['is_default'] ? 'Yes' : 'No') . "\n";
    echo "  Model: {$provider['default_model']}\n";
}

// ============================================================
// EXAMPLE 5: Get Usage Statistics
// ============================================================

$tracker = new AiUsageTracker($db);

// Monthly summary
$summary = $tracker->getUsageSummary($instanceId, period: 'month');

echo "Monthly Usage Summary:\n";
echo "  Total Cost: €" . number_format($summary['total_cost_eur'], 2) . "\n";
echo "  Total Calls: {$summary['total_calls']}\n";
echo "  Avg Cost/Call: €" . number_format($summary['avg_cost_per_call'], 6) . "\n";

// Budget status
$budget = $tracker->getMonthlyBudgetStatus($instanceId);

echo "\nBudget Status:\n";
echo "  Monthly Limit: €" . number_format($budget['budget_limit_eur'], 2) . "\n";
echo "  Current Usage: €" . number_format($budget['current_month_cost_eur'], 2) . "\n";
echo "  Percentage Used: {$budget['percentage_used']}%\n";

if ($budget['remaining_eur']) {
    echo "  Remaining: €" . number_format($budget['remaining_eur'], 2) . "\n";
}

// Per-provider stats
$providerStats = $tracker->getProviderStats(providerId: 1, instanceId: $instanceId, period: 'month');

echo "\nProvider Stats:\n";
echo "  Total Cost: €" . number_format($providerStats['total_cost_eur'], 6) . "\n";
echo "  Total Calls: {$providerStats['total_calls']}\n";

// By task type
$taskStats = $tracker->getTaskTypeStats($instanceId, period: 'month');

echo "\nUsage by Task Type:\n";
foreach ($taskStats['by_task'] as $task) {
    echo "  {$task['task_type']}: {$task['calls']} calls, €" .
        number_format($task['total_cost_eur'], 6) . "\n";
}

// ============================================================
// EXAMPLE 6: Test Provider Connection
// ============================================================

$handler = new AiRequestHandler($db, $registry, $tracker);
$testResult = $handler->testProvider(providerId: 1, instanceId: $instanceId);

if ($testResult['success']) {
    echo "✓ Provider connected successfully!\n";
    echo "  Model: {$testResult['model']}\n";
    echo "  Response: {$testResult['response']}\n";
} else {
    echo "✗ Provider connection failed: {$testResult['message']}\n";
}

// ============================================================
// EXAMPLE 7: Integrating with Existing Service
// ============================================================

/**
 * Example refactor of AiAssetLookupService to use new system
 */
class AiAssetLookupService
{
    private AiRequestHandler $handler;

    public function __construct($db, int $instanceId, int $userId = 0)
    {
        $this->handler = AiInitializer::getRequestHandler($db, $instanceId, $userId);
    }

    public function lookup(string $productName, string $manufacturer = ''): array
    {
        $systemPrompt = "You are an expert in event tech equipment...";
        $userMessage = "Research specifications for: {$productName}...";

        try {
            $response = $this->handler->processRequest(
                taskType: 'asset_lookup',
                prompt: $userMessage,
                options: [
                    'system' => $systemPrompt,
                    'max_tokens' => 1000,
                ]
            );

            $parsed = json_decode($response->content, true);
            return [
                'success' => true,
                'data' => $parsed,
                'model' => $response->model,
                'latency_ms' => $response->latencyMs,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}

// ============================================================
// EXAMPLE 8: Encryption/Decryption (for storing API keys)
// ============================================================

$registry = new AiProviderRegistry($db);

// Encrypt API key before storing
$plaintext = 'sk-1234567890abcdef';
$encrypted = $registry->encryptApiKey($plaintext);

echo "Original: {$plaintext}\n";
echo "Encrypted: {$encrypted}\n";

// Keys are decrypted automatically when loading from database
// You rarely need to call decryptApiKey() directly

// ============================================================
// EXAMPLE 9: Message Format Flexibility
// ============================================================

// These are all equivalent:

// Simple string prompt (system prompt goes in options)
$response1 = $handler->processRequest(
    taskType: 'chat',
    prompt: 'Hello, how are you?',
    options: ['system' => 'Be helpful and concise']
);

// Array of messages (standard OpenAI format)
$response2 = $handler->processRequest(
    taskType: 'chat',
    prompt: [
        ['role' => 'system', 'content' => 'Be helpful'],
        ['role' => 'user', 'content' => 'Hello, how are you?'],
    ]
);

// Both produce the same result
assert($response1->content === $response2->content);

// ============================================================
// EXAMPLE 10: Error Handling
// ============================================================

try {
    $response = $handler->processRequestWithRetry(
        taskType: 'asset_lookup',
        prompt: 'Search for...',
        maxRetries: 3
    );
} catch (Exception $e) {
    // Log the error
    error_log("AI request failed: {$e->getMessage()}");

    // Fallback to default behavior
    return [
        'success' => false,
        'error' => 'AI service unavailable',
        'fallback_data' => null,
    ];
}

// ============================================================
// Environment Configuration
// ============================================================

/**
 * Add these to your .env file:
 *
 * # AI Provider Encryption Key (or uses APP_SECRET as fallback)
 * AI_ENCRYPTION_KEY=your-very-secret-encryption-key-here
 *
 * # Optional: Monthly budget limit per instance (0 = unlimited)
 * instances_aiMonthlyBudgetEur=100.00
 */

echo "\nEnvironment Variables Needed:\n";
echo "  - AI_ENCRYPTION_KEY (optional, uses APP_SECRET if not set)\n";
echo "  - instances_aiMonthlyBudgetEur (optional, default unlimited)\n";
