<?php

/**
 * ImplementationTrackerService - Track implementation progress of features
 *
 * Manages:
 * - Module and baustein (task) status tracking
 * - Bidirectional sync with IMPLEMENTATION_CHECKLIST.md
 * - Progress metrics and dashboard data
 * - AI task audit logging
 *
 * Modules are categorized (L=Location/Logistics, J=Contract, K=Risk, I=AI)
 * Each module has multiple bausteine (components): migrations, services, API, UI, tests
 * Status: done (✅), review (🔧), open (⬜), needs_tests (🧪)
 */
class ImplementationTrackerService
{
    private string $mdPath;

    public function __construct(private $db)
    {
        // Calculate path to project root: src/services/AI/ -> 3 levels up
        $projectRoot = dirname(__DIR__, 3);
        $this->mdPath = $projectRoot . '/IMPLEMENTATION_CHECKLIST.md';
    }

    /**
     * Get complete checklist with all modules and their bausteine
     *
     * Returns nested structure: modules -> bausteine with status
     *
     * @param int $instanceId
     * @return array ['modules' => [module_code => ['name' => ..., 'bausteine' => [...]]]]
     */
    public function getChecklist(int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->orderBy('module_code, baustein_nr');
        $items = $this->db->get('ai_implementation_status');

        $modules = [];

        foreach ($items as $item) {
            $code = $item['module_code'];

            if (!isset($modules[$code])) {
                $modules[$code] = [
                    'name' => $item['module_name'],
                    'code' => $code,
                    'bausteine' => [],
                ];
            }

            $modules[$code]['bausteine'][] = [
                'nr' => $item['baustein_nr'],
                'name' => $item['baustein_name'],
                'status' => $item['status'],
                'file_path' => $item['file_path'],
            ];
        }

        ksort($modules);

        return ['modules' => $modules];
    }

    /**
     * Get status of all bausteine for a specific module
     *
     * @param string $moduleCode Module identifier (L1, L2, J1, K1, I1, etc.)
     * @param int $instanceId
     * @return array Array of bausteine with their status
     */
    public function getModuleStatus(string $moduleCode, int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('module_code', $moduleCode);
        $this->db->orderBy('baustein_nr');
        return $this->db->get('ai_implementation_status');
    }

    /**
     * Update the status of a specific baustein
     *
     * Statuses: done (✅), review (🔧), open (⬜), needs_tests (🧪)
     *
     * @param string $moduleCode
     * @param int $bausteinNr
     * @param string $status
     * @param int $instanceId
     * @return bool Success
     */
    public function updateStatus(
        string $moduleCode,
        int $bausteinNr,
        string $status,
        int $instanceId,
    ): bool {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('module_code', $moduleCode);
        $this->db->where('baustein_nr', $bausteinNr);

        $updated = $this->db->update('ai_implementation_status', [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if ($updated > 0) {
            // Sync back to markdown
            $this->syncToMarkdown($instanceId);
            return true;
        }

        return false;
    }

    /**
     * Get overall progress summary
     *
     * Returns counts by status and total percentage
     *
     * @param int $instanceId
     * @return array ['done' => int, 'review' => int, 'open' => int, 'needs_tests' => int, 'total' => int, 'percent' => float]
     */
    public function getProgressSummary(int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        $items = $this->db->get('ai_implementation_status');

        $counts = [
            'done' => 0,
            'review' => 0,
            'open' => 0,
            'needs_tests' => 0,
            'total' => count($items),
        ];

        foreach ($items as $item) {
            $counts[$item['status']]++;
        }

        // Calculate percentage done (done + review count as complete)
        $complete = $counts['done'] + $counts['review'];
        $percent = $counts['total'] > 0 ? round(($complete / $counts['total']) * 100, 1) : 0;

        $counts['percent'] = $percent;

        return $counts;
    }

    /**
     * Add a new module with its initial bausteine
     *
     * Called when a new feature request is approved
     * Creates module record and all its component items
     *
     * @param string $moduleCode Code identifier (L1, K2, I10, etc.)
     * @param string $moduleName Human-readable name
     * @param array $bausteine Array of ['name' => string, 'file_path' => string|null]
     * @param int $instanceId
     * @return bool Success
     */
    public function addModule(
        string $moduleCode,
        string $moduleName,
        array $bausteine,
        int $instanceId,
    ): bool {
        try {
            foreach ($bausteine as $idx => $baustein) {
                $this->db->insert('ai_implementation_status', [
                    'module_code' => $moduleCode,
                    'module_name' => $moduleName,
                    'baustein_nr' => $idx + 1,
                    'baustein_name' => $baustein['name'],
                    'file_path' => $baustein['file_path'] ?? null,
                    'status' => 'open',
                    'instances_id' => $instanceId,
                ]);
            }

            $this->syncToMarkdown($instanceId);

            return true;
        } catch (Exception $e) {
            error_log("ImplementationTrackerService::addModule failed: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Write all implementation status to IMPLEMENTATION_CHECKLIST.md
     *
     * Overwrites module sections while preserving header
     *
     * @param int $instanceId
     * @return bool Success
     */
    public function syncToMarkdown(int $instanceId): bool
    {
        try {
            $checklist = $this->getChecklist($instanceId);
            $summary = $this->getProgressSummary($instanceId);

            // Read header from existing file if it exists
            $content = '';
            if (file_exists($this->mdPath)) {
                $existing = file_get_contents($this->mdPath);
                $headerEnd = strpos($existing, '## L1 – ');
                if ($headerEnd !== false) {
                    $content = substr($existing, 0, $headerEnd);
                } else {
                    $content = $existing;
                }
            }

            // Build markdown
            $markdown = $content;

            if (!str_ends_with($markdown, "\n")) {
                $markdown .= "\n";
            }

            // Add each module section
            foreach ($checklist['modules'] as $code => $module) {
                $markdown .= "## {$code} – {$module['name']}\n\n";

                // Add description placeholder (would be filled by sync from MD if it exists)
                $markdown .= "Module description here.\n\n";

                // Add table
                $markdown .= "| # | Baustein | Status | Datei |\n";
                $markdown .= "|---|----------|--------|-------|\n";

                foreach ($module['bausteine'] as $baustein) {
                    $statusEmoji = $this->getStatusEmoji($baustein['status']);
                    $file = $baustein['file_path'] ?? '';
                    $markdown .= "| {$baustein['nr']} | {$baustein['name']} | {$statusEmoji} | `{$file}` |\n";
                }

                $markdown .= "\n---\n\n";
            }

            // Add summary section
            $markdown .= "## Zusammenfassung\n\n";
            $markdown .= "| Status | Anzahl |\n";
            $markdown .= "|--------|--------|\n";
            $markdown .= "| ✅ Implementiert | {$summary['done']} |\n";
            $markdown .= "| 🔧 Braucht Integration/Review | {$summary['review']} |\n";
            $markdown .= "| ⬜ Noch nicht implementiert | {$summary['open']} |\n";
            $markdown .= "| 🧪 Braucht Tests | {$summary['needs_tests']} |\n";
            $markdown .= "\n";

            // Write file
            if (!is_dir(dirname($this->mdPath))) {
                mkdir(dirname($this->mdPath), 0755, true);
            }

            file_put_contents($this->mdPath, $markdown);

            return true;
        } catch (Exception $e) {
            error_log("ImplementationTrackerService::syncToMarkdown failed: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Read IMPLEMENTATION_CHECKLIST.md and import status changes
     *
     * @param int $instanceId
     * @return int Count of imported/updated items
     */
    public function syncFromMarkdown(int $instanceId): int
    {
        if (!file_exists($this->mdPath)) {
            return 0;
        }

        try {
            $content = file_get_contents($this->mdPath);
            $imported = 0;

            // Parse module sections: ## L1 – Name
            $modulePattern = '/## ([A-Z]\d+) – (.+?)\n/';
            $moduleMatches = [];

            if (preg_match_all($modulePattern, $content, $moduleMatches, PREG_SET_ORDER)) {
                foreach ($moduleMatches as $moduleMatch) {
                    $moduleCode = $moduleMatch[1];
                    $moduleName = trim($moduleMatch[2]);

                    // Parse table rows for this module
                    // Pattern: | number | name | status | file |
                    $tablePattern = '/\| (\d+) \| (.+?) \| (.+?) \| `?([^`|\n]*)`? \|/';

                    // Find content between this module and next "## " or end
                    $moduleStart = strpos($content, $moduleMatch[0]);
                    $nextModule = strpos($content, "\n## ", $moduleStart + 1);
                    $nextModule = $nextModule !== false ? $nextModule : strlen($content);
                    $moduleContent = substr($content, $moduleStart, $nextModule - $moduleStart);

                    if (preg_match_all($tablePattern, $moduleContent, $tableMatches, PREG_SET_ORDER)) {
                        foreach ($tableMatches as $tableMatch) {
                            $bausteinNr = intval($tableMatch[1]);
                            $bausteinName = trim($tableMatch[2]);
                            $statusEmoji = trim($tableMatch[3]);
                            $filePath = trim($tableMatch[4]);

                            // Map emoji to status
                            $status = match ($statusEmoji) {
                                '✅' => 'done',
                                '🔧' => 'review',
                                '⬜' => 'open',
                                '🧪' => 'needs_tests',
                                default => 'open'
                            };

                            // Check if exists
                            $this->db->where('instances_id', $instanceId);
                            $this->db->where('module_code', $moduleCode);
                            $this->db->where('baustein_nr', $bausteinNr);
                            $existing = $this->db->getOne('ai_implementation_status');

                            if ($existing) {
                                // Update if status changed
                                if ($existing['status'] !== $status) {
                                    $this->db->where('instances_id', $instanceId);
                                    $this->db->where('module_code', $moduleCode);
                                    $this->db->where('baustein_nr', $bausteinNr);
                                    $this->db->update('ai_implementation_status', [
                                        'status' => $status,
                                        'updated_at' => date('Y-m-d H:i:s'),
                                    ]);
                                    $imported++;
                                }
                            } else {
                                // Create new
                                $this->db->insert('ai_implementation_status', [
                                    'module_code' => $moduleCode,
                                    'module_name' => $moduleName,
                                    'baustein_nr' => $bausteinNr,
                                    'baustein_name' => $bausteinName,
                                    'status' => $status,
                                    'file_path' => $filePath ?: null,
                                    'instances_id' => $instanceId,
                                ]);
                                $imported++;
                            }
                        }
                    }
                }
            }

            return $imported;
        } catch (Exception $e) {
            error_log("ImplementationTrackerService::syncFromMarkdown failed: {$e->getMessage()}");
            return 0;
        }
    }

    /**
     * Log what the KI did (for audit trail)
     *
     * Examples:
     * - taskType: 'formulate_request', action: 'formulated FR-005', details: 'Generated formulation for feature'
     * - taskType: 'update_status', action: 'updated L1 baustein 12 to done', details: 'Marked feature complete'
     * - taskType: 'sync_markdown', action: 'synced DB to FEATURE_REQUESTS.md', details: 'Exported 3 requests'
     *
     * @param string $taskType Type of task performed
     * @param ?string $moduleCode Related module code if applicable
     * @param string $action Description of action
     * @param ?string $details Additional context/results
     * @param int $userId User ID (0 for automated)
     * @param int $instanceId
     * @return void
     */
    public function logTask(
        string $taskType,
        ?string $moduleCode,
        string $action,
        ?string $details,
        int $userId,
        int $instanceId,
    ): void {
        try {
            $this->db->insert('ai_task_log', [
                'task_type' => $taskType,
                'module_code' => $moduleCode,
                'action' => $action,
                'details' => $details,
                'user_id' => $userId,
                'instances_id' => $instanceId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Exception $e) {
            error_log("ImplementationTrackerService::logTask failed: {$e->getMessage()}");
        }
    }

    /**
     * Get recent AI task log entries
     *
     * Useful for activity feed / audit trail
     *
     * @param int $instanceId
     * @param int $limit Maximum number of entries to return
     * @return array Task log entries
     */
    public function getRecentTaskLog(int $instanceId, int $limit = 50): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->orderBy('created_at', 'DESC');
        $this->db->limit($limit);
        return $this->db->get('ai_task_log');
    }

    /**
     * Get dashboard widget data
     *
     * Provides high-level overview:
     * - Progress per module (pie chart data)
     * - Recent activity (last 5 changes)
     * - Next pending tasks
     *
     * @param int $instanceId
     * @return array Dashboard data structure
     */
    public function getDashboardWidget(int $instanceId): array
    {
        // Progress summary
        $summary = $this->getProgressSummary($instanceId);

        // Per-module progress
        $checklist = $this->getChecklist($instanceId);
        $moduleProgress = [];

        foreach ($checklist['modules'] as $code => $module) {
            $done = 0;
            $review = 0;
            $total = count($module['bausteine']);

            foreach ($module['bausteine'] as $b) {
                if ($b['status'] === 'done') $done++;
                if ($b['status'] === 'review') $review++;
            }

            $progress = $total > 0 ? round((($done + $review) / $total) * 100) : 0;

            $moduleProgress[$code] = [
                'name' => $module['name'],
                'done' => $done,
                'review' => $review,
                'total' => $total,
                'percent' => $progress,
            ];
        }

        // Recent activity
        $recentLog = $this->getRecentTaskLog($instanceId, 5);

        return [
            'summary' => $summary,
            'modules' => $moduleProgress,
            'recent_activity' => $recentLog,
        ];
    }

    /**
     * Convert status to emoji for markdown display
     *
     * @param string $status
     * @return string
     */
    private function getStatusEmoji(string $status): string
    {
        return match ($status) {
            'done' => '✅',
            'review' => '🔧',
            'open' => '⬜',
            'needs_tests' => '🧪',
            default => '⬜'
        };
    }
}
