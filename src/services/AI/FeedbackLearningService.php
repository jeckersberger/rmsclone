<?php

/**
 * FeedbackLearningService - Learn and improve from user feedback on AI outputs
 *
 * Manages:
 * - User feedback collection (ratings, corrections, reasons)
 * - Few-shot example curation (quality examples for in-context learning)
 * - Prompt version management with A/B testing
 * - Learning profile generation (user preferences and patterns)
 * - Automatic optimization detection (when prompts need changes)
 */
class FeedbackLearningService
{
    public function __construct(
        private $db,
        private AiProviderRegistry $registry,
    ) {}

    /**
     * Record feedback on an AI output
     *
     * @param int $userId User who gave feedback
     * @param string $taskType Task type (e.g., 'email_draft', 'invoice_scan')
     * @param string $aiOutput The original AI-generated output
     * @param ?string $userEdited User's edited version (if they corrected it)
     * @param string $rating 'positive', 'negative', or 'neutral'
     * @param ?string $reason Why they rated it this way (e.g., 'inaccurate', 'good_start')
     * @param ?string $feedbackText Additional feedback text from user
     * @param string $provider Provider name (e.g., 'openai')
     * @param string $model Model name (e.g., 'gpt-4')
     * @param int $instanceId Instance ID
     * @param ?int $editTimeMs Time user spent editing (in milliseconds)
     * @param int $tokensInput Input tokens used
     * @param int $tokensOutput Output tokens used
     * @return int Feedback record ID
     */
    public function recordFeedback(
        int $userId,
        string $taskType,
        string $aiOutput,
        ?string $userEdited,
        string $rating,
        ?string $reason,
        ?string $feedbackText,
        string $provider,
        string $model,
        int $instanceId,
        ?int $editTimeMs = null,
        int $tokensInput = 0,
        int $tokensOutput = 0,
    ): int {
        // Get active prompt version
        $promptVersion = $this->getPromptVersion($taskType, $instanceId);
        $versionNumber = $promptVersion['version'] ?? 1;

        $data = [
            'user_id' => $userId,
            'instance_id' => $instanceId,
            'task_type' => $taskType,
            'ai_output' => $aiOutput,
            'user_edited' => $userEdited,
            'rating' => $rating,
            'feedback_reason' => $reason,
            'feedback_text' => $feedbackText,
            'accepted' => (int)($rating === 'positive' || ($userEdited === null && $rating === 'neutral')),
            'edit_time_ms' => $editTimeMs,
            'provider' => $provider,
            'model' => $model,
            'prompt_version' => $versionNumber,
            'tokens_input' => $tokensInput,
            'tokens_output' => $tokensOutput,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $feedbackId = $this->db->insert('ai_feedback', $data);

        // If user edited, consider as few-shot candidate
        if ($userEdited && $rating === 'positive') {
            $this->addFewShotCandidate($taskType, $aiOutput, $userEdited, $instanceId);
        }

        // Update learning profile
        $this->updateLearningProfile($taskType, $rating, $reason, $userEdited, $instanceId);

        // Check if optimization is needed
        $this->checkAutoOptimization($taskType, $instanceId);

        return $feedbackId;
    }

    /**
     * Calculate acceptance rate for a task type
     *
     * @param string $taskType Task type
     * @param int $instanceId Instance ID
     * @param ?int $days Look back N days (default 30)
     * @return float Acceptance rate 0-1
     */
    public function getAcceptanceRate(
        string $taskType,
        int $instanceId,
        ?int $days = 30,
    ): float {
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $this->db->where('task_type', $taskType);
        $this->db->where('instance_id', $instanceId);
        $this->db->where('created_at', $since, '>=');
        $feedbacks = $this->db->get('ai_feedback') ?: [];

        if (empty($feedbacks)) {
            return 0.0;
        }

        $accepted = array_filter($feedbacks, fn ($f) => $f['accepted'] == 1);

        return round(count($accepted) / count($feedbacks), 4);
    }

    /**
     * Get feedback statistics for dashboard
     *
     * Returns: total feedbacks, acceptance rate trend, top issues, recent feedback
     *
     * @param int $instanceId Instance ID
     * @return array Statistics array
     */
    public function getFeedbackStats(int $instanceId): array
    {
        // Total feedbacks last 30 days
        $since30 = date('Y-m-d H:i:s', strtotime('-30 days'));
        $this->db->where('instance_id', $instanceId);
        $this->db->where('created_at', $since30, '>=');
        $feedbacks30 = $this->db->get('ai_feedback') ?: [];

        // Total feedbacks last 90 days
        $since90 = date('Y-m-d H:i:s', strtotime('-90 days'));
        $this->db->where('instance_id', $instanceId);
        $this->db->where('created_at', $since90, '>=');
        $feedbacks90 = $this->db->get('ai_feedback') ?: [];

        // By rating
        $byRating = ['positive' => 0, 'negative' => 0, 'neutral' => 0];
        foreach ($feedbacks30 as $f) {
            $byRating[$f['rating']]++;
        }

        // Top task types by feedback
        $taskTypes = [];
        foreach ($feedbacks30 as $f) {
            if (!isset($taskTypes[$f['task_type']])) {
                $taskTypes[$f['task_type']] = ['count' => 0, 'positive' => 0];
            }
            $taskTypes[$f['task_type']]['count']++;
            if ($f['rating'] === 'positive') {
                $taskTypes[$f['task_type']]['positive']++;
            }
        }

        // Calculate trend (30 days vs 60-90 days)
        $since60 = date('Y-m-d H:i:s', strtotime('-60 days'));
        $this->db->where('instance_id', $instanceId);
        $this->db->where('created_at', $since60, '>=');
        $this->db->where('created_at', $since30, '<');
        $feedbacks60 = $this->db->get('ai_feedback') ?: [];

        $trend30Accepted = array_filter($feedbacks30, fn ($f) => $f['accepted'] == 1);
        $trend60Accepted = array_filter($feedbacks60, fn ($f) => $f['accepted'] == 1);

        $rate30 = !empty($feedbacks30) ? count($trend30Accepted) / count($feedbacks30) : 0;
        $rate60 = !empty($feedbacks60) ? count($trend60Accepted) / count($feedbacks60) : 0;
        $trendDirection = $rate30 > $rate60 ? 'up' : ($rate30 < $rate60 ? 'down' : 'flat');

        // Top negative reasons
        $reasons = [];
        foreach ($feedbacks30 as $f) {
            if ($f['rating'] === 'negative' && $f['feedback_reason']) {
                $reasons[$f['feedback_reason']] = ($reasons[$f['feedback_reason']] ?? 0) + 1;
            }
        }
        arsort($reasons);
        $topReasons = array_slice($reasons, 0, 5, true);

        return [
            'total_30_days' => count($feedbacks30),
            'total_90_days' => count($feedbacks90),
            'by_rating' => $byRating,
            'acceptance_rate_30' => round($rate30, 4),
            'acceptance_rate_60' => round($rate60, 4),
            'trend_direction' => $trendDirection,
            'trend_change_pct' => round(($rate30 - $rate60) * 100, 1),
            'task_types' => array_slice($taskTypes, 0, 10, true),
            'top_negative_reasons' => $topReasons,
        ];
    }

    /**
     * Add a new few-shot example candidate
     *
     * Few-shot examples are high-quality input/output pairs that guide the AI model.
     *
     * @param string $taskType Task type
     * @param string $inputContext The input/prompt
     * @param string $output The output example
     * @param int $instanceId Instance ID
     * @return int Example record ID
     */
    public function addFewShotCandidate(
        string $taskType,
        string $inputContext,
        string $output,
        int $instanceId,
    ): int {
        $data = [
            'instance_id' => $instanceId,
            'task_type' => $taskType,
            'input_context' => $inputContext,
            'output_example' => $output,
            'positive_votes' => 0,
            'negative_votes' => 0,
            'usage_count' => 0,
            'is_active' => false, // Needs manual review/approval
            'created_at' => date('Y-m-d H:i:s'),
        ];

        return $this->db->insert('ai_few_shot_examples', $data);
    }

    /**
     * Get best few-shot examples for a task type
     *
     * @param string $taskType Task type
     * @param int $limit Number of examples to return
     * @param int $instanceId Instance ID
     * @return array Array of examples
     */
    public function getBestFewShotExamples(
        string $taskType,
        int $limit = 3,
        int $instanceId = 0,
    ): array {
        $this->db->where('task_type', $taskType);
        $this->db->where('is_active', true);

        if ($instanceId > 0) {
            $this->db->where('instance_id', $instanceId);
        }

        // Order by positive votes (net positive)
        $this->db->orderBy('positive_votes', 'DESC');
        $this->db->orderBy('usage_count', 'DESC');

        $examples = $this->db->get('ai_few_shot_examples', $limit) ?: [];

        // Track usage
        foreach ($examples as $ex) {
            $this->db->where('id', $ex['id']);
            $this->db->update('ai_few_shot_examples', [
                'usage_count' => $ex['usage_count'] + 1,
            ]);
        }

        return $examples;
    }

    /**
     * Get the active prompt version for a task type
     *
     * @param string $taskType Task type
     * @param int $instanceId Instance ID
     * @return array Prompt version data
     */
    public function getPromptVersion(string $taskType, int $instanceId): array
    {
        // First try to get an active version for this instance
        $this->db->where('task_type', $taskType);
        $this->db->where('is_active', true);
        $this->db->orderBy('is_ab_test', 'DESC'); // Prefer non-AB tests
        $this->db->orderBy('version', 'DESC');
        $active = $this->db->getOne('ai_prompt_versions');

        if ($active) {
            return $active;
        }

        // Fallback to any version
        $this->db->where('task_type', $taskType);
        $this->db->orderBy('version', 'DESC');
        $latest = $this->db->getOne('ai_prompt_versions');

        return $latest ?: [
            'task_type' => $taskType,
            'version' => 1,
            'system_prompt' => 'You are a helpful assistant.',
        ];
    }

    /**
     * Create a new prompt version
     *
     * @param string $taskType Task type
     * @param string $systemPrompt The new system prompt
     * @param string $changeReason Why this version was created
     * @param string $source 'manual', 'automatic', or 'ab_test'
     * @param int $instanceId Instance ID (for tracking)
     * @return int Version ID
     */
    public function createPromptVersion(
        string $taskType,
        string $systemPrompt,
        string $changeReason,
        string $source,
        int $instanceId = 1,
    ): int {
        // Get next version number
        $this->db->where('task_type', $taskType);
        $this->db->orderBy('version', 'DESC');
        $latest = $this->db->getOne('ai_prompt_versions', null, ['version']);
        $nextVersion = ($latest['version'] ?? 0) + 1;

        $data = [
            'task_type' => $taskType,
            'version' => $nextVersion,
            'system_prompt' => $systemPrompt,
            'change_reason' => $changeReason,
            'change_source' => $source,
            'acceptance_rate' => null,
            'total_uses' => 0,
            'is_active' => false, // Must be explicitly activated
            'is_ab_test' => false,
            'ab_test_traffic_pct' => 50,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        return $this->db->insert('ai_prompt_versions', $data);
    }

    /**
     * Activate a prompt version
     *
     * @param int $versionId Version ID to activate
     * @return bool Success
     */
    public function activatePromptVersion(int $versionId): bool
    {
        // Get the version
        $this->db->where('id', $versionId);
        $version = $this->db->getOne('ai_prompt_versions');

        if (!$version) {
            return false;
        }

        // Deactivate all other versions for this task type
        $this->db->where('task_type', $version['task_type']);
        $this->db->where('id', $versionId, '!=');
        $this->db->update('ai_prompt_versions', ['is_active' => false]);

        // Activate this version
        $this->db->where('id', $versionId);
        $this->db->update('ai_prompt_versions', ['is_active' => true]);

        return true;
    }

    /**
     * Get all prompt versions for a task type
     *
     * @param string $taskType Task type
     * @param int $instanceId Instance ID
     * @return array Array of versions
     */
    public function getPromptVersionHistory(
        string $taskType,
        int $instanceId = 1,
    ): array {
        $this->db->where('task_type', $taskType);
        $this->db->orderBy('version', 'DESC');
        return $this->db->get('ai_prompt_versions') ?: [];
    }

    /**
     * Build an enriched prompt combining system prompt, few-shot examples, and learning profile
     *
     * This creates a complete prompt that includes:
     * - System prompt (active version)
     * - Few-shot examples (best examples for this task type)
     * - Learning profile preferences (what we learned about this user)
     *
     * @param int $userId User ID
     * @param string $taskType Task type
     * @param string $userQuery The user's actual query/request
     * @param int $instanceId Instance ID
     * @return array ['system' => system prompt, 'user' => full user message, 'few_shots' => examples]
     */
    public function buildEnrichedPrompt(
        int $userId,
        string $taskType,
        string $userQuery,
        int $instanceId,
    ): array {
        // Get active system prompt
        $promptVersion = $this->getPromptVersion($taskType, $instanceId);
        $systemPrompt = $promptVersion['system_prompt'];

        // Get few-shot examples
        $fewShots = $this->getBestFewShotExamples($taskType, 3, $instanceId);
        $fewShotText = '';
        if (!empty($fewShots)) {
            $fewShotText = "\n\nExamples of good outputs:\n";
            foreach ($fewShots as $ex) {
                $fewShotText .= "\nInput: " . substr($ex['input_context'], 0, 200) . "...\n";
                $fewShotText .= "Output: " . substr($ex['output_example'], 0, 200) . "...\n";
            }
        }

        // Get learning profile
        $profile = $this->getLearningProfile($instanceId);
        $profileText = '';
        if (!empty($profile)) {
            $profileText = "\n\nUser preferences learned from past interactions:\n";
            foreach ($profile as $p) {
                $confidence = round($p['confidence'] * 100);
                $profileText .= "- {$p['profile_key']}: {$p['profile_value']} (confidence: {$confidence}%)\n";
            }
        }

        return [
            'system' => $systemPrompt,
            'user' => $userQuery . $fewShotText . $profileText,
            'few_shots' => $fewShots,
            'profile' => $profile,
        ];
    }

    /**
     * Update learning profile based on feedback
     *
     * Analyzes patterns in user's feedback to build a profile of preferences.
     * Examples:
     * - "style_preference": "formal" (if user keeps editing casual output to formal)
     * - "length_preference": "concise" (if user rates short outputs higher)
     * - "detail_level": "high" (if user wants more detail)
     *
     * @param string $taskType Task type being learned from
     * @param string $rating User's rating
     * @param ?string $reason Feedback reason
     * @param ?string $userEdited User's edited version (if they corrected it)
     * @param int $instanceId Instance ID
     * @return void
     */
    public function updateLearningProfile(
        string $taskType,
        string $rating,
        ?string $reason,
        ?string $userEdited,
        int $instanceId,
    ): void {
        // Get recent feedbacks for pattern analysis
        $since = date('Y-m-d H:i:s', strtotime('-90 days'));
        $this->db->where('instance_id', $instanceId);
        $this->db->where('created_at', $since, '>=');
        $this->db->where('task_type', $taskType);
        $recent = $this->db->get('ai_feedback') ?: [];

        if (count($recent) < 3) {
            // Need more data to detect patterns
            return;
        }

        // Analyze for patterns
        $patterns = [];

        // Pattern: Length preference
        $short = array_filter($recent, fn ($f) => strlen($f['ai_output']) < 200);
        $long = array_filter($recent, fn ($f) => strlen($f['ai_output']) > 500);
        $shortPositive = array_filter($short, fn ($f) => $f['accepted']);
        $longPositive = array_filter($long, fn ($f) => $f['accepted']);

        if (count($short) > 0 && count($long) > 0) {
            $shortRate = count($shortPositive) / count($short);
            $longRate = count($longPositive) / count($long);
            if ($shortRate > $longRate + 0.2) {
                $patterns['length_preference'] = [
                    'value' => 'concise',
                    'confidence' => min(0.99, $shortRate),
                ];
            } elseif ($longRate > $shortRate + 0.2) {
                $patterns['length_preference'] = [
                    'value' => 'detailed',
                    'confidence' => min(0.99, $longRate),
                ];
            }
        }

        // Pattern: Acceptance rate
        $acceptanceRate = $this->getAcceptanceRate($taskType, $instanceId, 90);
        if ($acceptanceRate > 0.8) {
            $patterns['quality_satisfaction'] = [
                'value' => 'high',
                'confidence' => min(0.99, $acceptanceRate),
            ];
        } elseif ($acceptanceRate < 0.4) {
            $patterns['quality_satisfaction'] = [
                'value' => 'low',
                'confidence' => min(0.99, 1 - $acceptanceRate),
            ];
        }

        // Pattern: Editing frequency
        $edited = array_filter($recent, fn ($f) => $f['user_edited'] !== null);
        $editRate = count($edited) / count($recent);
        if ($editRate > 0.7) {
            $patterns['editing_tendency'] = [
                'value' => 'frequently_modifies',
                'confidence' => min(0.99, $editRate),
            ];
        }

        // Persist patterns
        foreach ($patterns as $key => $data) {
            $this->db->where('instance_id', $instanceId);
            $this->db->where('profile_key', $key);
            $existing = $this->db->getOne('ai_learning_profile');

            if ($existing) {
                $this->db->where('id', $existing['id']);
                $this->db->update('ai_learning_profile', [
                    'profile_value' => $data['value'],
                    'confidence' => $data['confidence'],
                    'data_points' => $existing['data_points'] + 1,
                ]);
            } else {
                $this->db->insert('ai_learning_profile', [
                    'instance_id' => $instanceId,
                    'profile_key' => $key,
                    'profile_value' => $data['value'],
                    'confidence' => $data['confidence'],
                    'data_points' => 1,
                    'last_updated' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    /**
     * Get the learning profile for an instance
     *
     * @param int $instanceId Instance ID
     * @return array Array of profile entries
     */
    public function getLearningProfile(int $instanceId): array
    {
        $this->db->where('instance_id', $instanceId);
        $this->db->where('confidence', 0.5, '>='); // Only confident profiles
        $this->db->orderBy('confidence', 'DESC');
        return $this->db->get('ai_learning_profile') ?: [];
    }

    /**
     * Get dashboard data for AI learning system
     *
     * @param int $instanceId Instance ID
     * @return array Dashboard statistics
     */
    public function getDashboardData(int $instanceId): array
    {
        $stats = $this->getFeedbackStats($instanceId);

        // Acceptance rate trend data (last 6 months by week)
        $trendData = [];
        for ($w = 0; $w < 26; $w++) {
            $start = date('Y-m-d H:i:s', strtotime("-" . (26 - $w) . " weeks"));
            $end = date('Y-m-d H:i:s', strtotime("-" . (25 - $w) . " weeks"));

            $this->db->where('instance_id', $instanceId);
            $this->db->where('created_at', $start, '>=');
            $this->db->where('created_at', $end, '<');
            $weekFeedbacks = $this->db->get('ai_feedback') ?: [];

            if (!empty($weekFeedbacks)) {
                $accepted = array_filter($weekFeedbacks, fn ($f) => $f['accepted'] == 1);
                $rate = count($accepted) / count($weekFeedbacks);
                $trendData[] = [
                    'week' => date('Y-W', strtotime($start)),
                    'rate' => round($rate, 4),
                    'count' => count($weekFeedbacks),
                ];
            }
        }

        // Top improvements (task types with highest improvement trend)
        $improvements = [];
        foreach ($stats['task_types'] as $taskType => $data) {
            $rate30 = $this->getAcceptanceRate($taskType, $instanceId, 30);
            $rate60 = $this->getAcceptanceRate($taskType, $instanceId, 60);
            $improvement = $rate30 - $rate60;

            if ($improvement > 0) {
                $improvements[] = [
                    'task_type' => $taskType,
                    'current_rate' => $rate30,
                    'previous_rate' => $rate60,
                    'improvement_pct' => round($improvement * 100, 1),
                ];
            }
        }
        usort($improvements, fn ($a, $b) => $b['improvement_pct'] <=> $a['improvement_pct']);

        // Areas needing attention (task types with low acceptance)
        $needsAttention = [];
        foreach ($stats['task_types'] as $taskType => $data) {
            $rate = $this->getAcceptanceRate($taskType, $instanceId, 30);
            if ($rate < 0.6 && $data['count'] >= 5) {
                $needsAttention[] = [
                    'task_type' => $taskType,
                    'acceptance_rate' => $rate,
                    'feedback_count' => $data['count'],
                    'top_issue' => array_key_first($stats['top_negative_reasons']) ?: 'unclear',
                ];
            }
        }
        usort($needsAttention, fn ($a, $b) => $a['acceptance_rate'] <=> $b['acceptance_rate']);

        return [
            'stats' => $stats,
            'trend_data' => $trendData,
            'improvements' => array_slice($improvements, 0, 10),
            'needs_attention' => array_slice($needsAttention, 0, 10),
            'learning_profile' => $this->getLearningProfile($instanceId),
        ];
    }

    /**
     * Check if automatic optimization is needed
     *
     * If a task type has:
     * - >70% negative feedback with same reason
     * - >100 feedbacks in last 30 days
     *
     * Then suggest a prompt change
     *
     * @param string $taskType Task type
     * @param int $instanceId Instance ID
     * @return bool Whether optimization is recommended
     */
    public function checkAutoOptimization(string $taskType, int $instanceId): bool
    {
        $since = date('Y-m-d H:i:s', strtotime('-30 days'));
        $this->db->where('task_type', $taskType);
        $this->db->where('instance_id', $instanceId);
        $this->db->where('created_at', $since, '>=');
        $feedbacks = $this->db->get('ai_feedback') ?: [];

        if (count($feedbacks) < 100) {
            return false; // Not enough data
        }

        // Find if >70% are negative with same reason
        $negative = array_filter($feedbacks, fn ($f) => $f['rating'] === 'negative');
        if (count($negative) / count($feedbacks) < 0.7) {
            return false;
        }

        // Find most common reason
        $reasons = [];
        foreach ($negative as $f) {
            if ($f['feedback_reason']) {
                $reasons[$f['feedback_reason']] = ($reasons[$f['feedback_reason']] ?? 0) + 1;
            }
        }

        if (empty($reasons)) {
            return false;
        }

        $topReason = max($reasons);
        $reasonPct = $topReason / count($negative);

        return $reasonPct > 0.7;
    }

    /**
     * Delete all user feedback (GDPR right to deletion)
     *
     * @param int $userId User ID
     * @return int Number of records deleted
     */
    public function deleteUserFeedback(int $userId): int
    {
        $this->db->where('user_id', $userId);
        $feedbacks = $this->db->get('ai_feedback') ?: [];

        $this->db->where('user_id', $userId);
        $this->db->delete('ai_feedback');

        return $this->db->affectedRows();
    }
}
