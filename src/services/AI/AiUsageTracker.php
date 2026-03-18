<?php

/**
 * AiUsageTracker - Track and analyze AI API usage
 *
 * Logs all AI requests for billing, cost tracking, and analytics.
 */
class AiUsageTracker
{
    public function __construct(private $db) {}

    /**
     * Log a single AI request
     */
    public function logUsage(
        int $providerId,
        string $model,
        string $taskType,
        int $inputTokens,
        int $outputTokens,
        int $latencyMs,
        float $costEur,
        ?int $userId,
        int $instanceId,
    ): bool {
        return (bool)$this->db->insert('ai_usage_log', [
            'instances_id' => $instanceId,
            'provider_id' => $providerId,
            'model' => $model,
            'task_type' => $taskType,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'latency_ms' => $latencyMs,
            'estimated_cost_eur' => $costEur,
            'users_userid' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get usage summary for an instance
     *
     * @param int $instanceId
     * @param string $period 'day', 'week', 'month', 'year', or null for all
     * @return array Summary statistics
     */
    public function getUsageSummary(int $instanceId, ?string $period = 'month'): array
    {
        $dateFilter = $this->getDateFilter($period);

        // Raw query for usage stats (MeekroDB has no groupBy, so we'll aggregate manually)
        $sql = "SELECT
                    provider_id,
                    model,
                    task_type,
                    COUNT(*) as calls,
                    SUM(input_tokens) as total_input_tokens,
                    SUM(output_tokens) as total_output_tokens,
                    SUM(estimated_cost_eur) as total_cost_eur,
                    AVG(latency_ms) as avg_latency_ms
                FROM ai_usage_log
                WHERE instances_id = ?";

        $params = [$instanceId];

        if ($dateFilter) {
            $sql .= " AND created_at >= ?";
            $params[] = $dateFilter;
        }

        $sql .= " GROUP BY provider_id, model, task_type
                  ORDER BY total_cost_eur DESC";

        $rows = $this->db->rawQuery($sql, $params) ?: [];

        // Calculate totals
        $totalCost = 0;
        $totalCalls = 0;
        $totalTokens = 0;

        foreach ($rows as &$row) {
            $totalCost += (float)($row['total_cost_eur'] ?? 0);
            $totalCalls += (int)($row['calls'] ?? 0);
            $totalTokens += ((int)($row['total_input_tokens'] ?? 0)) + ((int)($row['total_output_tokens'] ?? 0));
        }

        return [
            'period' => $period,
            'by_provider' => $rows,
            'total_cost_eur' => round($totalCost, 6),
            'total_calls' => $totalCalls,
            'total_tokens' => $totalTokens,
            'avg_cost_per_call' => $totalCalls > 0 ? round($totalCost / $totalCalls, 6) : 0,
        ];
    }

    /**
     * Get monthly budget status
     *
     * @return array Current month usage and budget
     */
    public function getMonthlyBudgetStatus(int $instanceId): array
    {
        $currentMonth = date('Y-m-01');

        // Get settings for budget limit (if any)
        $this->db->where('instances_id', $instanceId);
        $settings = $this->db->getOne('instances', null, [
            'instances_aiMonthlyBudgetEur',
        ]) ?: [];

        $budgetLimit = (float)($settings['instances_aiMonthlyBudgetEur'] ?? 0);

        // Get current month's usage
        $sql = "SELECT
                    SUM(estimated_cost_eur) as total_cost_eur,
                    COUNT(*) as total_calls
                FROM ai_usage_log
                WHERE instances_id = ?
                  AND created_at >= ?";

        $row = $this->db->rawQueryOne($sql, [$instanceId, $currentMonth]) ?: [];

        $currentCost = (float)($row['total_cost_eur'] ?? 0);
        $currentCalls = (int)($row['total_calls'] ?? 0);

        $percentageUsed = $budgetLimit > 0 ? round(($currentCost / $budgetLimit) * 100, 2) : 0;

        return [
            'budget_limit_eur' => $budgetLimit,
            'current_month_cost_eur' => round($currentCost, 6),
            'current_month_calls' => $currentCalls,
            'percentage_used' => $percentageUsed,
            'remaining_eur' => $budgetLimit > 0 ? round($budgetLimit - $currentCost, 6) : null,
            'current_month' => date('F Y', strtotime($currentMonth)),
        ];
    }

    /**
     * Get detailed usage for a specific provider
     */
    public function getProviderStats(int $providerId, int $instanceId, ?string $period = 'month'): array
    {
        $dateFilter = $this->getDateFilter($period);

        $sql = "SELECT
                    model,
                    task_type,
                    COUNT(*) as calls,
                    SUM(input_tokens) as total_input_tokens,
                    SUM(output_tokens) as total_output_tokens,
                    SUM(estimated_cost_eur) as total_cost_eur,
                    AVG(latency_ms) as avg_latency_ms,
                    MAX(latency_ms) as max_latency_ms,
                    MIN(latency_ms) as min_latency_ms
                FROM ai_usage_log
                WHERE provider_id = ?
                  AND instances_id = ?";

        $params = [$providerId, $instanceId];

        if ($dateFilter) {
            $sql .= " AND created_at >= ?";
            $params[] = $dateFilter;
        }

        $sql .= " GROUP BY model, task_type
                  ORDER BY total_cost_eur DESC";

        $rows = $this->db->rawQuery($sql, $params) ?: [];

        $totalCost = 0;
        $totalCalls = 0;

        foreach ($rows as &$row) {
            $totalCost += (float)($row['total_cost_eur'] ?? 0);
            $totalCalls += (int)($row['calls'] ?? 0);
        }

        return [
            'period' => $period,
            'by_model_and_task' => $rows,
            'total_cost_eur' => round($totalCost, 6),
            'total_calls' => $totalCalls,
            'avg_cost_per_call' => $totalCalls > 0 ? round($totalCost / $totalCalls, 6) : 0,
        ];
    }

    /**
     * Get usage breakdown by task type
     */
    public function getTaskTypeStats(int $instanceId, ?string $period = 'month'): array
    {
        $dateFilter = $this->getDateFilter($period);

        $sql = "SELECT
                    task_type,
                    COUNT(*) as calls,
                    SUM(input_tokens) as total_input_tokens,
                    SUM(output_tokens) as total_output_tokens,
                    SUM(estimated_cost_eur) as total_cost_eur,
                    AVG(latency_ms) as avg_latency_ms
                FROM ai_usage_log
                WHERE instances_id = ?";

        $params = [$instanceId];

        if ($dateFilter) {
            $sql .= " AND created_at >= ?";
            $params[] = $dateFilter;
        }

        $sql .= " GROUP BY task_type
                  ORDER BY total_cost_eur DESC";

        $rows = $this->db->rawQuery($sql, $params) ?: [];

        return [
            'period' => $period,
            'by_task' => $rows,
        ];
    }

    /**
     * Get usage for a specific date range
     */
    public function getUsageInDateRange(int $instanceId, string $startDate, string $endDate): array
    {
        $sql = "SELECT
                    provider_id,
                    model,
                    task_type,
                    COUNT(*) as calls,
                    SUM(estimated_cost_eur) as total_cost_eur
                FROM ai_usage_log
                WHERE instances_id = ?
                  AND created_at >= ?
                  AND created_at <= ?
                GROUP BY provider_id, model, task_type
                ORDER BY total_cost_eur DESC";

        return $this->db->rawQuery($sql, [$instanceId, $startDate, $endDate . ' 23:59:59']) ?: [];
    }

    // ── Private Methods ──

    private function getDateFilter(?string $period): ?string
    {
        return match ($period) {
            'day' => date('Y-m-d 00:00:00', strtotime('-1 day')),
            'week' => date('Y-m-d 00:00:00', strtotime('-7 days')),
            'month' => date('Y-m-01 00:00:00'),
            'year' => date('Y-01-01 00:00:00'),
            default => null,
        };
    }
}
