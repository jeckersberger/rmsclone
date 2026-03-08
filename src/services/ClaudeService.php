<?php
/**
 * Zentraler Service fuer Claude API Aufrufe.
 * Nutzt die Anthropic Messages API direkt per cURL (kein SDK noetig).
 *
 * Alle Features pruefen:
 *   1. Ob KI global aktiviert ist
 *   2. Ob das jeweilige Feature aktiviert ist
 *   3. API-Key vorhanden ist
 *
 * Jeder Aufruf wird in ai_usage_log protokolliert.
 */
class ClaudeService
{
    private $db;
    private int $instanceId;
    private string $apiKey;
    private string $model;
    private array $settings;
    private int $userId = 0;

    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    private const FEATURE_MAP = [
        'invoice_scan'      => 'instances_aiFeatureInvoiceScan',
        'search'            => 'instances_aiFeatureSearch',
        'email_draft'       => 'instances_aiFeatureEmailDraft',
        'quote_assist'      => 'instances_aiFeatureQuoteAssist',
        'project_summary'   => 'instances_aiFeatureProjectSummary',
        'expense_category'  => 'instances_aiFeatureExpenseCategory',
        'contract_analysis' => 'instances_aiFeatureContractAnalysis',
        'price_suggestion'  => 'instances_aiFeaturePriceSuggestion',
        'damage_report'     => 'instances_aiFeatureDamageReport',
        'asset_allocation'  => 'instances_aiFeatureAssetAllocation',
        'client_risk'       => 'instances_aiFeatureClientRisk',
        'email_reply'       => 'instances_aiFeatureEmailReply',
        'finance_forecast'  => 'instances_aiFeatureFinanceForecast',
        'document_check'    => 'instances_aiFeatureDocumentCheck',
        'predict_maintenance' => 'instances_aiFeaturePredictMaintenance',
        'crew_optimize'     => 'instances_aiFeatureCrewOptimize',
        'duplicate_detect'  => 'instances_aiFeatureDuplicateDetect',
        'chat'              => 'instances_aiFeatureChat',
    ];

    // Cost per 1M tokens (USD) - Haiku 4.5
    private const COST_MAP = [
        'claude-haiku-4-5-20251001' => ['input' => 1.0, 'output' => 5.0],
        'claude-sonnet-4-6'         => ['input' => 3.0, 'output' => 15.0],
        'claude-opus-4-6'           => ['input' => 15.0, 'output' => 75.0],
    ];

    public function __construct($db, int $instanceId)
    {
        $this->db = $db;
        $this->instanceId = $instanceId;
        $this->loadSettings();
    }

    private function loadSettings(): void
    {
        $this->db->where('instances_id', $this->instanceId);
        $this->settings = $this->db->getOne('instances') ?: [];
        $this->apiKey = $this->settings['instances_aiApiKey'] ?? '';
        $this->model = $this->settings['instances_aiModel'] ?? 'claude-haiku-4-5-20251001';
    }

    /**
     * Check if AI is available (global + API key)
     */
    public function isAvailable(): bool
    {
        return !empty($this->settings['instances_aiEnabled']) && !empty($this->apiKey);
    }

    /**
     * Check if a specific feature is enabled
     */
    public function isFeatureEnabled(string $feature): bool
    {
        if (!$this->isAvailable()) return false;
        $col = self::FEATURE_MAP[$feature] ?? null;
        if (!$col) return false;
        return !empty($this->settings[$col]);
    }

    /**
     * Get all feature states for UI rendering
     */
    public function getFeatureStates(): array
    {
        $states = ['ai_enabled' => $this->isAvailable()];
        foreach (self::FEATURE_MAP as $key => $col) {
            $states[$key] = $this->isFeatureEnabled($key);
        }
        return $states;
    }

    /**
     * Send a text message to Claude
     */
    public function ask(string $feature, string $systemPrompt, string $userMessage, ?string $modelOverride = null, int $maxTokens = 2048): ?array
    {
        if (!$this->isFeatureEnabled($feature)) return null;

        $model = $modelOverride ?: $this->model;
        $body = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $userMessage]
            ],
        ];

        $response = $this->callApi($body);
        $this->logUsage($feature, $model, $response);
        return $response;
    }

    /**
     * Send a message with an image (base64) - for damage reports / PDF scans
     */
    public function askWithImage(string $feature, string $systemPrompt, string $userMessage, string $base64Data, string $mediaType = 'image/jpeg', int $maxTokens = 2048): ?array
    {
        if (!$this->isFeatureEnabled($feature)) return null;

        $model = $this->model;
        $body = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => [
                    ['type' => 'image', 'source' => [
                        'type' => 'base64',
                        'media_type' => $mediaType,
                        'data' => $base64Data,
                    ]],
                    ['type' => 'text', 'text' => $userMessage],
                ]],
            ],
        ];

        $response = $this->callApi($body);
        $this->logUsage($feature, $model, $response);
        return $response;
    }

    /**
     * Send a message with a PDF document
     */
    public function askWithPdf(string $feature, string $systemPrompt, string $userMessage, string $base64Pdf, int $maxTokens = 4096): ?array
    {
        if (!$this->isFeatureEnabled($feature)) return null;

        $model = $this->model;
        $body = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => [
                    ['type' => 'document', 'source' => [
                        'type' => 'base64',
                        'media_type' => 'application/pdf',
                        'data' => $base64Pdf,
                    ]],
                    ['type' => 'text', 'text' => $userMessage],
                ]],
            ],
        ];

        $response = $this->callApi($body);
        $this->logUsage($feature, $model, $response);
        return $response;
    }

    /**
     * Get the text content from an API response
     */
    public static function extractText(?array $response): string
    {
        if (!$response || !isset($response['content'])) return '';
        foreach ($response['content'] as $block) {
            if (($block['type'] ?? '') === 'text') return $block['text'];
        }
        return '';
    }

    /**
     * Get usage statistics for this instance
     */
    public function getUsageStats(int $year, int $month): array
    {
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = date('Y-m-t', strtotime($start));

        $this->db->where('instances_id', $this->instanceId);
        $this->db->where('created_at', $start, '>=');
        $this->db->where('created_at', $end . ' 23:59:59', '<=');
        $this->db->groupBy('feature');
        $rows = $this->db->get('ai_usage_log', null, [
            'feature',
            'COUNT(*) as calls',
            'SUM(input_tokens) as total_input',
            'SUM(output_tokens) as total_output',
            'SUM(cost_estimate_usd) as total_cost',
        ]) ?: [];

        $totalCost = 0;
        $totalCalls = 0;
        foreach ($rows as &$r) {
            $totalCost += (float)$r['total_cost'];
            $totalCalls += (int)$r['calls'];
        }

        return [
            'by_feature' => $rows,
            'total_cost_usd' => round($totalCost, 4),
            'total_calls' => $totalCalls,
            'month' => $month,
            'year' => $year,
        ];
    }

    // ── Private ──

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

        if ($httpCode !== 200 || !$raw) return null;
        $data = json_decode($raw, true);
        return $data ?: null;
    }

    private function logUsage(string $feature, string $model, ?array $response): void
    {
        $inputTokens = $response['usage']['input_tokens'] ?? 0;
        $outputTokens = $response['usage']['output_tokens'] ?? 0;

        $costs = self::COST_MAP[$model] ?? ['input' => 1.0, 'output' => 5.0];
        $cost = ($inputTokens * $costs['input'] + $outputTokens * $costs['output']) / 1_000_000;

        $this->db->insert('ai_usage_log', [
            'instances_id' => $this->instanceId,
            'feature' => $feature,
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost_estimate_usd' => round($cost, 6),
            'users_userid' => $this->userId,
        ]);
    }

    public function setUserId(int $userId): void
    {
        $this->userId = $userId;
    }
}
