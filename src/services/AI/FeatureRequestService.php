<?php

/**
 * FeatureRequestService - Manage feature requests lifecycle
 *
 * Handles:
 * - Reading/writing FEATURE_REQUESTS.md markdown file
 * - Syncing feature requests between markdown file and database
 * - Feature request lifecycle: idea -> formulated -> approved -> in_progress -> done
 * - FR number generation (FR-001, FR-002, etc.)
 *
 * The markdown file is kept in sync with the database. Changes can happen in either place:
 * - User edits markdown file -> call syncFromMarkdown() to import to DB
 * - KI updates DB -> call syncToMarkdown() to export to file
 */
class FeatureRequestService
{
    private string $mdPath;

    public function __construct(private $db)
    {
        // Calculate path to project root: src/services/AI/ -> 3 levels up
        $projectRoot = dirname(__DIR__, 3);
        $this->mdPath = $projectRoot . '/FEATURE_REQUESTS.md';
    }

    /**
     * Get all pending requests (status: idea or formulated)
     *
     * @param int $instanceId
     * @return array Array of feature request arrays
     */
    public function getPendingRequests(int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('status', ['idea', 'formulated'], 'IN');
        $this->db->orderBy('created_at', 'DESC');
        return $this->db->get('ai_feature_requests');
    }

    /**
     * Get all requests regardless of status
     *
     * @param int $instanceId
     * @return array
     */
    public function getAllRequests(int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->orderBy('created_at', 'DESC');
        return $this->db->get('ai_feature_requests');
    }

    /**
     * Add a new feature request from user idea
     *
     * Creates both a DB record and updates the markdown file
     *
     * @param string $title Short title of the request
     * @param string $userIdea The user's original idea text
     * @param int $instanceId
     * @return string The generated FR number (e.g., "FR-001")
     */
    public function addRequest(string $title, string $userIdea, int $instanceId): string
    {
        // Generate next FR number
        $frNumber = $this->getNextFrNumber($instanceId);

        // Insert into database
        $this->db->insert('ai_feature_requests', [
            'fr_number' => $frNumber,
            'title' => $title,
            'status' => 'idea',
            'user_idea' => $userIdea,
            'instances_id' => $instanceId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Sync to markdown
        $this->syncToMarkdown($instanceId);

        return $frNumber;
    }

    /**
     * Update a feature request with KI formulation
     *
     * Fills in the detailed formulation (what/why/how/db/service/api/ui)
     * and sets status to 'formulated'
     *
     * @param string $frNumber The FR number (e.g., "FR-001")
     * @param string $aiFormulation The detailed formulation text
     * @param ?string $priority high|medium|low
     * @param ?string $size s|m|l|xl
     * @param int $instanceId
     * @return bool Success
     */
    public function formulateRequest(
        string $frNumber,
        string $aiFormulation,
        ?string $priority,
        ?string $size,
        int $instanceId,
    ): bool {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('fr_number', $frNumber);

        $updated = $this->db->update('ai_feature_requests', [
            'status' => 'formulated',
            'ai_formulation' => $aiFormulation,
            'priority' => $priority,
            'estimated_size' => $size,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if ($updated > 0) {
            $this->syncToMarkdown($instanceId);
            return true;
        }

        return false;
    }

    /**
     * Update the status of a feature request
     *
     * Allows status transitions: idea -> formulated -> approved -> in_progress -> done
     *
     * @param string $frNumber The FR number
     * @param string $status Target status: idea|formulated|approved|in_progress|done
     * @param ?string $commitHash Git commit SHA when marking as done
     * @param int $instanceId
     * @return bool Success
     */
    public function updateStatus(
        string $frNumber,
        string $status,
        ?string $commitHash,
        int $instanceId,
    ): bool {
        $data = [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($commitHash && $status === 'done') {
            $data['commit_hash'] = $commitHash;
        }

        $this->db->where('instances_id', $instanceId);
        $this->db->where('fr_number', $frNumber);

        $updated = $this->db->update('ai_feature_requests', $data);

        if ($updated > 0) {
            $this->syncToMarkdown($instanceId);
            return true;
        }

        return false;
    }

    /**
     * Get the next available FR number for this instance
     *
     * Reads existing FR numbers from DB and increments the highest
     *
     * @param int $instanceId
     * @return string Next FR number (e.g., "FR-001", "FR-042")
     */
    public function getNextFrNumber(int $instanceId): string
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->orderBy('id', 'DESC');
        $latest = $this->db->getOne('ai_feature_requests', ['fr_number']);

        if (!$latest) {
            return 'FR-001';
        }

        // Extract number from FR-XXX
        $match = [];
        if (preg_match('/FR-(\d+)/', $latest['fr_number'], $match)) {
            $nextNum = intval($match[1]) + 1;
        } else {
            $nextNum = 1;
        }

        return 'FR-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Write all feature requests from DB to FEATURE_REQUESTS.md
     *
     * This overwrites the "Offene" and "Abgeschlossene" sections of the markdown file
     * while preserving the header and documentation sections
     *
     * @param int $instanceId
     * @return bool Success
     */
    public function syncToMarkdown(int $instanceId): bool
    {
        try {
            $requests = $this->getAllRequests($instanceId);

            // Separate into open and completed
            $open = [];
            $completed = [];

            foreach ($requests as $req) {
                if ($req['status'] === 'done') {
                    $completed[] = $req;
                } else {
                    $open[] = $req;
                }
            }

            // Read existing markdown to preserve header
            $content = '';
            if (file_exists($this->mdPath)) {
                $existing = file_get_contents($this->mdPath);
                // Extract content up to "## Offene Feature Requests"
                $headerEnd = strpos($existing, '## Offene Feature Requests');
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

            $markdown .= "## Offene Feature Requests\n\n";

            foreach ($open as $req) {
                $markdown .= $this->formatRequestMarkdown($req);
            }

            $markdown .= "\n---\n\n";
            $markdown .= "## Abgeschlossene Feature Requests\n\n";

            foreach ($completed as $req) {
                $markdown .= $this->formatRequestMarkdown($req);
            }

            $markdown .= "\n";

            // Write to file
            if (!is_dir(dirname($this->mdPath))) {
                mkdir(dirname($this->mdPath), 0755, true);
            }

            file_put_contents($this->mdPath, $markdown);

            return true;
        } catch (Exception $e) {
            error_log("FeatureRequestService::syncToMarkdown failed: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Read FEATURE_REQUESTS.md and import any new entries not yet in DB
     *
     * Scans the markdown file for feature requests that don't exist in the DB
     * and imports them, assigning new FR numbers as needed
     *
     * @param int $instanceId
     * @return int Count of imported requests
     */
    public function syncFromMarkdown(int $instanceId): int
    {
        if (!file_exists($this->mdPath)) {
            return 0;
        }

        try {
            $content = file_get_contents($this->mdPath);
            $imported = 0;

            // Parse markdown format:
            // ### FR-XXX: [Title]
            // **Status:** IDEE | AUSFORMULIERT | ...
            // **Nutzer-Idee:** ...
            // **KI-Ausformulierung:** ...
            // **Commit:** ...

            $pattern = '/### FR-(\d{3}): (.+)\n\*\*Status:\*\* (.+)\n.*?\*\*Nutzer-Idee:\*\* (.+?)(?=\n\*\*|\n###|$)/s';

            if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $frNumber = 'FR-' . $match[1];
                    $title = trim($match[2]);
                    $mdStatus = strtolower(trim($match[3]));
                    $userIdea = trim($match[4]);

                    // Check if exists
                    $this->db->where('instances_id', $instanceId);
                    $this->db->where('fr_number', $frNumber);
                    $existing = $this->db->getOne('ai_feature_requests');

                    if (!$existing) {
                        // Map markdown status to DB status
                        $status = match ($mdStatus) {
                            'idee' => 'idea',
                            'ausformuliert' => 'formulated',
                            'freigegeben' => 'approved',
                            'in arbeit' => 'in_progress',
                            'erledigt' => 'done',
                            default => 'idea'
                        };

                        $this->db->insert('ai_feature_requests', [
                            'fr_number' => $frNumber,
                            'title' => $title,
                            'status' => $status,
                            'user_idea' => $userIdea,
                            'instances_id' => $instanceId,
                            'created_at' => date('Y-m-d H:i:s'),
                        ]);

                        $imported++;
                    }
                }
            }

            return $imported;
        } catch (Exception $e) {
            error_log("FeatureRequestService::syncFromMarkdown failed: {$e->getMessage()}");
            return 0;
        }
    }

    /**
     * Format a single request as markdown
     *
     * @param array $request
     * @return string Markdown text
     */
    private function formatRequestMarkdown(array $request): string
    {
        $status = match ($request['status']) {
            'idea' => 'IDEE',
            'formulated' => 'AUSFORMULIERT',
            'approved' => 'FREIGEGEBEN',
            'in_progress' => 'IN ARBEIT',
            'done' => 'ERLEDIGT',
            default => strtoupper($request['status'])
        };

        $markdown = "### {$request['fr_number']}: {$request['title']}\n";
        $markdown .= "**Status:** {$status}\n";
        $markdown .= "**Erstellt:** " . date('d. M Y', strtotime($request['created_at'])) . "\n\n";

        $markdown .= "**Nutzer-Idee:** " . $request['user_idea'] . "\n\n";

        if ($request['ai_formulation']) {
            $markdown .= "**KI-Ausformulierung:**\n";
            $markdown .= $request['ai_formulation'] . "\n\n";
        }

        if ($request['priority']) {
            $markdown .= "**Priorität:** " . ucfirst($request['priority']) . "\n";
        }

        if ($request['estimated_size']) {
            $sizeLabel = match ($request['estimated_size']) {
                's' => 'Klein (S)',
                'm' => 'Mittel (M)',
                'l' => 'Groß (L)',
                'xl' => 'Sehr groß (XL)',
                default => $request['estimated_size']
            };
            $markdown .= "**Größe:** {$sizeLabel}\n";
        }

        if ($request['commit_hash']) {
            $markdown .= "**Commit:** {$request['commit_hash']}\n";
        }

        $markdown .= "\n";

        return $markdown;
    }
}
