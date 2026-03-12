<?php
/**
 * KI-Chat Service
 *
 * Steuert das Verleih-System per natuerlicher Sprache.
 * Nutzt Claude Tool-Use um bestehende System-Funktionen aufzurufen:
 * - Projekte suchen/erstellen
 * - Equipment-Verfuegbarkeit pruefen
 * - Kunden suchen
 * - Rechnungen/Angebote erstellen
 * - Ueberfaellige Rueckgaben anzeigen
 * - etc.
 */
class ChatService
{
    private $db;
    private int $instanceId;
    private int $userId;
    private ClaudeService $claude;

    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';
    private const MAX_HISTORY = 20; // Max messages to send as context

    public function __construct($db, int $instanceId, int $userId, ClaudeService $claude)
    {
        $this->db = $db;
        $this->instanceId = $instanceId;
        $this->userId = $userId;
        $this->claude = $claude;
    }

    /**
     * Neue Konversation starten
     */
    public function createConversation(?string $title = null): int
    {
        $this->db->insert('ai_chat_conversations', [
            'instances_id' => $this->instanceId,
            'users_userid' => $this->userId,
            'title' => $title,
        ]);
        return $this->db->getInsertId();
    }

    /**
     * Nachricht senden und Antwort erhalten
     */
    public function sendMessage(int $conversationId, string $userMessage): array
    {
        // Validate conversation belongs to user/instance
        $conv = $this->getConversation($conversationId);
        if (!$conv) {
            return ['error' => 'Konversation nicht gefunden'];
        }

        // Save user message
        $this->saveMessage($conversationId, 'user', $userMessage);

        // Build message history
        $history = $this->getHistory($conversationId);
        $messages = $this->buildMessages($history);

        // Auto-generate title from first message
        if (empty($conv['title'])) {
            $title = mb_substr($userMessage, 0, 80);
            $this->db->where('id', $conversationId);
            $this->db->update('ai_chat_conversations', ['title' => $title]);
        }

        // Call Claude with tools
        $response = $this->callWithTools($messages);

        if (!$response) {
            $errorMsg = 'Fehler bei der KI-Anfrage. Bitte versuche es erneut.';
            $this->saveMessage($conversationId, 'assistant', $errorMsg);
            return ['error' => $errorMsg];
        }

        // Process tool calls if any
        $finalText = $this->processResponse($conversationId, $messages, $response);

        // Save assistant response
        $this->saveMessage($conversationId, 'assistant', $finalText, null, null, $response['usage']['output_tokens'] ?? 0);

        return [
            'message' => $finalText,
            'conversation_id' => $conversationId,
        ];
    }

    /**
     * Konversations-Liste fuer den User
     */
    public function getConversations(int $limit = 30): array
    {
        $this->db->where('instances_id', $this->instanceId);
        $this->db->where('users_userid', $this->userId);
        $this->db->where('archived', 0);
        $this->db->orderBy('updated_at', 'DESC');
        return $this->db->get('ai_chat_conversations', $limit) ?: [];
    }

    /**
     * Einzelne Konversation mit Nachrichten
     */
    public function getConversation(int $conversationId): ?array
    {
        $this->db->where('id', $conversationId);
        $this->db->where('instances_id', $this->instanceId);
        $this->db->where('users_userid', $this->userId);
        return $this->db->getOne('ai_chat_conversations') ?: null;
    }

    /**
     * Nachrichten-Historie einer Konversation
     */
    public function getHistory(int $conversationId): array
    {
        $this->db->where('conversation_id', $conversationId);
        $this->db->orderBy('created_at', 'ASC');
        return $this->db->get('ai_chat_messages') ?: [];
    }

    /**
     * Konversation archivieren
     */
    public function archiveConversation(int $conversationId): bool
    {
        $this->db->where('id', $conversationId);
        $this->db->where('instances_id', $this->instanceId);
        $this->db->where('users_userid', $this->userId);
        return (bool)$this->db->update('ai_chat_conversations', ['archived' => 1]);
    }

    // ── Private Methoden ──

    private function saveMessage(int $conversationId, string $role, string $content, ?string $toolCalls = null, ?string $toolResults = null, int $tokens = 0): void
    {
        $this->db->insert('ai_chat_messages', [
            'conversation_id' => $conversationId,
            'role' => $role,
            'content' => $content,
            'tool_calls_json' => $toolCalls,
            'tool_results_json' => $toolResults,
            'tokens_used' => $tokens,
        ]);
    }

    private function buildMessages(array $history): array
    {
        $messages = [];
        $recent = array_slice($history, -self::MAX_HISTORY);

        foreach ($recent as $msg) {
            if ($msg['role'] === 'system') continue;
            $messages[] = [
                'role' => $msg['role'],
                'content' => $msg['content'],
            ];
        }

        return $messages;
    }

    private function getSystemPrompt(): string
    {
        // Get instance info for context
        $this->db->where('instances_id', $this->instanceId);
        $inst = $this->db->getOne('instances', ['instances_name']) ?: [];
        $companyName = $inst['instances_name'] ?? 'Unbekannt';

        // Get current user info
        $this->db->where('users_userid', $this->userId);
        $user = $this->db->getOne('users', ['users_name1', 'users_name2']) ?: [];
        $userName = trim(($user['users_name1'] ?? '') . ' ' . ($user['users_name2'] ?? ''));

        $today = date('Y-m-d');

        return <<<PROMPT
Du bist der KI-Assistent fuer "{$companyName}", ein Verleih-Management-System (MyRMS).
Der aktuelle Benutzer heisst {$userName}. Heute ist {$today}.

Du kannst das System steuern, indem du die verfuegbaren Tools nutzt. Antworte immer auf Deutsch.
Sei freundlich, praezise und hilfreich. Wenn du Aktionen ausfuehrst, erklaere kurz was du gemacht hast.

Wichtig:
- Fuehre Aktionen die Daten aendern (Projekt erstellen, etc.) nur aus wenn der User es explizit verlangt
- Bei Suchen und Abfragen kannst du proaktiv helfen
- Formatiere Ergebnisse uebersichtlich mit Aufzaehlungen
- Bei mehreren Ergebnissen zeige die wichtigsten zuerst
- Nenne immer Projekt-IDs und Kunden-IDs damit der User direkt navigieren kann
PROMPT;
    }

    /**
     * Tool-Definitionen fuer Claude
     */
    private function getTools(): array
    {
        return [
            [
                'name' => 'search_projects',
                'description' => 'Suche nach Projekten/Jobs. Kann nach Name, Kunde, Datum, Status filtern.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'keyword' => ['type' => 'string', 'description' => 'Suchbegriff fuer Projektname'],
                        'client_name' => ['type' => 'string', 'description' => 'Kundenname'],
                        'date_from' => ['type' => 'string', 'description' => 'Von-Datum (YYYY-MM-DD)'],
                        'date_to' => ['type' => 'string', 'description' => 'Bis-Datum (YYYY-MM-DD)'],
                        'status' => ['type' => 'string', 'description' => 'Projektstatus'],
                    ],
                    'required' => [],
                ],
            ],
            [
                'name' => 'check_availability',
                'description' => 'Pruefe die Verfuegbarkeit von Equipment fuer einen Zeitraum.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'asset_type_name' => ['type' => 'string', 'description' => 'Name des Equipment-Typs (z.B. "Beamer", "Kamera")'],
                        'date_from' => ['type' => 'string', 'description' => 'Von-Datum (YYYY-MM-DD)'],
                        'date_to' => ['type' => 'string', 'description' => 'Bis-Datum (YYYY-MM-DD)'],
                    ],
                    'required' => ['date_from', 'date_to'],
                ],
            ],
            [
                'name' => 'search_clients',
                'description' => 'Suche nach Kunden/Firmen.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'keyword' => ['type' => 'string', 'description' => 'Suchbegriff (Name, Firma, E-Mail)'],
                    ],
                    'required' => ['keyword'],
                ],
            ],
            [
                'name' => 'get_overdue_returns',
                'description' => 'Zeige ueberfaellige Equipment-Rueckgaben an.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'days_overdue' => ['type' => 'integer', 'description' => 'Mindestanzahl Tage ueberfaellig (Standard: 0)'],
                    ],
                    'required' => [],
                ],
            ],
            [
                'name' => 'get_project_details',
                'description' => 'Hole Details zu einem bestimmten Projekt (Equipment, Finanzen, Status).',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'project_id' => ['type' => 'integer', 'description' => 'Projekt-ID'],
                    ],
                    'required' => ['project_id'],
                ],
            ],
            [
                'name' => 'create_project',
                'description' => 'Erstelle ein neues Projekt/einen neuen Auftrag.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'Projektname'],
                        'client_id' => ['type' => 'integer', 'description' => 'Kunden-ID'],
                        'date_start' => ['type' => 'string', 'description' => 'Startdatum (YYYY-MM-DD)'],
                        'date_end' => ['type' => 'string', 'description' => 'Enddatum (YYYY-MM-DD)'],
                        'description' => ['type' => 'string', 'description' => 'Projektbeschreibung'],
                    ],
                    'required' => ['name', 'date_start', 'date_end'],
                ],
            ],
            [
                'name' => 'search_assets',
                'description' => 'Suche nach Equipment/Assets im Inventar.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'keyword' => ['type' => 'string', 'description' => 'Suchbegriff (Name, Tag, Kategorie)'],
                        'category' => ['type' => 'string', 'description' => 'Kategoriename'],
                    ],
                    'required' => [],
                ],
            ],
            [
                'name' => 'get_dashboard_stats',
                'description' => 'Hole aktuelle Dashboard-Statistiken (aktive Projekte, ueberfaellige Rueckgaben, offene Rechnungen, etc.).',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [],
                    'required' => [],
                ],
            ],
            [
                'name' => 'get_upcoming_projects',
                'description' => 'Zeige kommende Projekte in den naechsten Tagen.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'days' => ['type' => 'integer', 'description' => 'Anzahl Tage voraus (Standard: 7)'],
                    ],
                    'required' => [],
                ],
            ],
        ];
    }

    /**
     * Claude API mit Tools aufrufen
     */
    private function callWithTools(array $messages): ?array
    {
        $this->db->where('instances_id', $this->instanceId);
        $settings = $this->db->getOne('instances', ['instances_aiApiKey', 'instances_aiModel']) ?: [];
        $apiKey = $settings['instances_aiApiKey'] ?? '';
        $model = $settings['instances_aiModel'] ?? 'claude-haiku-4-5-20251001';

        if (empty($apiKey)) return null;

        $body = [
            'model' => $model,
            'max_tokens' => 4096,
            'system' => $this->getSystemPrompt(),
            'tools' => $this->getTools(),
            'messages' => $messages,
        ];

        return $this->callApi($apiKey, $body);
    }

    /**
     * Response verarbeiten, ggf. Tools ausfuehren und Folgeanfrage machen
     */
    private function processResponse(int $conversationId, array $messages, array $response, int $depth = 0): string
    {
        if ($depth > 5) return 'Maximale Verarbeitungstiefe erreicht.';

        $stopReason = $response['stop_reason'] ?? 'end_turn';

        // If no tool use, extract text
        if ($stopReason !== 'tool_use') {
            return ClaudeService::extractText($response);
        }

        // Process tool calls
        $assistantContent = $response['content'] ?? [];
        $toolResults = [];

        foreach ($assistantContent as $block) {
            if (($block['type'] ?? '') !== 'tool_use') continue;

            $toolName = $block['name'];
            $toolInput = $block['input'] ?? [];
            $toolId = $block['id'];

            // Execute tool
            $result = $this->executeTool($toolName, $toolInput);

            $toolResults[] = [
                'type' => 'tool_result',
                'tool_use_id' => $toolId,
                'content' => json_encode($result, JSON_UNESCAPED_UNICODE),
            ];
        }

        // Send tool results back to Claude
        $messages[] = ['role' => 'assistant', 'content' => $assistantContent];
        $messages[] = ['role' => 'user', 'content' => $toolResults];

        $this->db->where('instances_id', $this->instanceId);
        $settings = $this->db->getOne('instances', ['instances_aiApiKey', 'instances_aiModel']) ?: [];
        $apiKey = $settings['instances_aiApiKey'] ?? '';

        $body = [
            'model' => $settings['instances_aiModel'] ?? 'claude-haiku-4-5-20251001',
            'max_tokens' => 4096,
            'system' => $this->getSystemPrompt(),
            'tools' => $this->getTools(),
            'messages' => $messages,
        ];

        $followUp = $this->callApi($apiKey, $body);
        if (!$followUp) return 'Fehler bei der Verarbeitung.';

        // Log usage
        $this->logChatUsage($followUp);

        return $this->processResponse($conversationId, $messages, $followUp, $depth + 1);
    }

    /**
     * Tool ausfuehren und Ergebnis zurueckgeben
     */
    private function executeTool(string $name, array $input): array
    {
        switch ($name) {
            case 'search_projects':
                return $this->toolSearchProjects($input);
            case 'check_availability':
                return $this->toolCheckAvailability($input);
            case 'search_clients':
                return $this->toolSearchClients($input);
            case 'get_overdue_returns':
                return $this->toolGetOverdueReturns($input);
            case 'get_project_details':
                return $this->toolGetProjectDetails($input);
            case 'create_project':
                return $this->toolCreateProject($input);
            case 'search_assets':
                return $this->toolSearchAssets($input);
            case 'get_dashboard_stats':
                return $this->toolGetDashboardStats();
            case 'get_upcoming_projects':
                return $this->toolGetUpcomingProjects($input);
            default:
                return ['error' => 'Unbekanntes Tool: ' . $name];
        }
    }

    // ── Tool Implementations ──

    private function toolSearchProjects(array $input): array
    {
        $this->db->where('p.instances_id', $this->instanceId);
        $this->db->where('p.projects_deleted', 0);

        if (!empty($input['keyword'])) {
            $kw = '%' . $input['keyword'] . '%';
            $this->db->where("(p.projects_name LIKE ? OR p.projects_description LIKE ?)", [$kw, $kw]);
        }
        if (!empty($input['client_name'])) {
            $this->db->where('c.clients_name LIKE ?', ['%' . $input['client_name'] . '%']);
        }
        if (!empty($input['date_from'])) {
            $this->db->where('p.projects_dates_use_end', $input['date_from'], '>=');
        }
        if (!empty($input['date_to'])) {
            $this->db->where('p.projects_dates_use_start', $input['date_to'], '<=');
        }

        $this->db->join('clients c', 'p.clients_id=c.clients_id', 'LEFT');
        $this->db->join('projectsStatuses ps', 'p.projectsStatuses_id=ps.projectsStatuses_id', 'LEFT');
        $this->db->orderBy('p.projects_dates_use_start', 'DESC');

        $projects = $this->db->get('projects p', 20, [
            'p.projects_id', 'p.projects_name', 'p.projects_dates_use_start', 'p.projects_dates_use_end',
            'c.clients_name', 'c.clients_id', 'ps.projectsStatuses_name'
        ]) ?: [];

        return ['projects' => $projects, 'count' => count($projects)];
    }

    private function toolCheckAvailability(array $input): array
    {
        require_once __DIR__ . '/AvailabilityService.php';
        $avail = new AvailabilityService($this->db);

        $dateFrom = $input['date_from'];
        $dateTo = $input['date_to'];

        // Find asset type by name if provided
        $assetTypeId = null;
        if (!empty($input['asset_type_name'])) {
            $this->db->where('instances_id', $this->instanceId);
            $this->db->where('assetTypes_name LIKE ?', ['%' . $input['asset_type_name'] . '%']);
            $at = $this->db->getOne('assetTypes', ['assetTypes_id', 'assetTypes_name']);
            if ($at) $assetTypeId = (int)$at['assetTypes_id'];
        }

        if ($assetTypeId) {
            $result = $avail->getAssetTypeAvailability($assetTypeId, $this->instanceId, $dateFrom, $dateTo);
            return [
                'asset_type' => $input['asset_type_name'],
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'total' => $result['total'],
                'available' => $result['available'],
                'unavailable' => $result['unavailable'],
                'details' => array_map(fn($a) => [
                    'tag' => $a['assets_tag'],
                    'available' => $a['available'],
                    'conflicts' => array_map(fn($c) => $c['project_name'] ?? $c['reason'] ?? $c['type'], $a['conflicts']),
                ], $result['assets']),
            ];
        }

        // No specific type - show general overview
        $this->db->where('at.instances_id', $this->instanceId);
        $this->db->join('assetCategories ac', 'at.assetCategories_id=ac.assetCategories_id', 'LEFT');
        $this->db->orderBy('at.assetTypes_name', 'ASC');
        $types = $this->db->get('assetTypes at', 30, ['at.assetTypes_id', 'at.assetTypes_name', 'ac.assetCategories_name']) ?: [];

        $overview = [];
        foreach ($types as $t) {
            $r = $avail->getAssetTypeAvailability((int)$t['assetTypes_id'], $this->instanceId, $dateFrom, $dateTo);
            if ($r['total'] > 0) {
                $overview[] = [
                    'type' => $t['assetTypes_name'],
                    'category' => $t['assetCategories_name'],
                    'total' => $r['total'],
                    'available' => $r['available'],
                ];
            }
        }

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'types' => $overview,
        ];
    }

    private function toolSearchClients(array $input): array
    {
        $kw = '%' . ($input['keyword'] ?? '') . '%';
        $this->db->where('instances_id', $this->instanceId);
        $this->db->where('clients_deleted', 0);
        $this->db->where("(clients_name LIKE ? OR clients_email LIKE ? OR clients_address1 LIKE ?)", [$kw, $kw, $kw]);
        $this->db->orderBy('clients_name', 'ASC');
        $clients = $this->db->get('clients', 20, [
            'clients_id', 'clients_name', 'clients_email', 'clients_phone', 'clients_address1', 'clients_address2'
        ]) ?: [];

        return ['clients' => $clients, 'count' => count($clients)];
    }

    private function toolGetOverdueReturns(array $input): array
    {
        $daysOverdue = $input['days_overdue'] ?? 0;
        $cutoffDate = date('Y-m-d', strtotime("-{$daysOverdue} days"));

        $sql = "SELECT p.projects_id, p.projects_name,
                       COALESCE(p.projects_dates_deliver_end, p.projects_dates_use_end) as due_date,
                       c.clients_name, c.clients_id,
                       COUNT(aa.assetsAssignments_id) as asset_count,
                       DATEDIFF(CURDATE(), COALESCE(p.projects_dates_deliver_end, p.projects_dates_use_end)) as days_overdue
                FROM projects p
                JOIN assetsAssignments aa ON p.projects_id = aa.projects_id AND aa.assetsAssignments_deleted = 0
                LEFT JOIN clients c ON p.clients_id = c.clients_id
                LEFT JOIN projectsStatuses ps ON p.projectsStatuses_id = ps.projectsStatuses_id
                WHERE p.instances_id = ?
                AND p.projects_deleted = 0
                AND p.projects_archived = 0
                AND COALESCE(p.projects_dates_deliver_end, p.projects_dates_use_end) < ?
                AND (ps.projectsStatuses_assetsReleased = 0 OR ps.projectsStatuses_assetsReleased IS NULL)
                GROUP BY p.projects_id
                ORDER BY due_date ASC";

        $results = $this->db->rawQuery($sql, [$this->instanceId, $cutoffDate]) ?: [];

        return ['overdue_projects' => $results, 'count' => count($results)];
    }

    private function toolGetProjectDetails(array $input): array
    {
        $projectId = $input['project_id'];

        $this->db->where('p.projects_id', $projectId);
        $this->db->where('p.instances_id', $this->instanceId);
        $this->db->join('clients c', 'p.clients_id=c.clients_id', 'LEFT');
        $this->db->join('projectsStatuses ps', 'p.projectsStatuses_id=ps.projectsStatuses_id', 'LEFT');
        $project = $this->db->getOne('projects p', [
            'p.*', 'c.clients_name', 'c.clients_email', 'ps.projectsStatuses_name'
        ]);

        if (!$project) return ['error' => 'Projekt nicht gefunden'];

        // Get assigned assets
        $this->db->where('aa.projects_id', $projectId);
        $this->db->where('aa.assetsAssignments_deleted', 0);
        $this->db->join('assets a', 'aa.assets_id=a.assets_id', 'LEFT');
        $this->db->join('assetTypes at', 'a.assetTypes_id=at.assetTypes_id', 'LEFT');
        $assets = $this->db->get('assetsAssignments aa', null, [
            'at.assetTypes_name', 'a.assets_tag', 'aa.assetsAssignments_customPrice'
        ]) ?: [];

        // Get payments
        $this->db->where('projects_id', $projectId);
        $payments = $this->db->get('payments', null, ['payments_amount', 'payments_date', 'payments_note']) ?: [];

        return [
            'project' => [
                'id' => $project['projects_id'],
                'name' => $project['projects_name'],
                'description' => $project['projects_description'] ?? '',
                'client' => $project['clients_name'],
                'status' => $project['projectsStatuses_name'],
                'use_start' => $project['projects_dates_use_start'],
                'use_end' => $project['projects_dates_use_end'],
                'deliver_start' => $project['projects_dates_deliver_start'],
                'deliver_end' => $project['projects_dates_deliver_end'],
            ],
            'assets' => $assets,
            'asset_count' => count($assets),
            'payments' => $payments,
        ];
    }

    private function toolCreateProject(array $input): array
    {
        $data = [
            'instances_id' => $this->instanceId,
            'projects_name' => $input['name'],
            'projects_dates_use_start' => $input['date_start'],
            'projects_dates_use_end' => $input['date_end'],
            'projects_description' => $input['description'] ?? null,
            'clients_id' => $input['client_id'] ?? null,
            'projects_manager' => $this->userId,
            'projects_deleted' => 0,
            'projects_archived' => 0,
        ];

        $id = $this->db->insert('projects', $data);
        if (!$id) return ['error' => 'Projekt konnte nicht erstellt werden'];

        return [
            'success' => true,
            'project_id' => $id,
            'message' => "Projekt '{$input['name']}' wurde erstellt (ID: {$id})",
        ];
    }

    private function toolSearchAssets(array $input): array
    {
        $this->db->where('a.instances_id', $this->instanceId);
        $this->db->where('a.assets_deleted', 0);

        if (!empty($input['keyword'])) {
            $kw = '%' . $input['keyword'] . '%';
            $this->db->where("(at.assetTypes_name LIKE ? OR a.assets_tag LIKE ? OR ac.assetCategories_name LIKE ?)", [$kw, $kw, $kw]);
        }
        if (!empty($input['category'])) {
            $this->db->where('ac.assetCategories_name LIKE ?', ['%' . $input['category'] . '%']);
        }

        $this->db->join('assetTypes at', 'a.assetTypes_id=at.assetTypes_id', 'LEFT');
        $this->db->join('assetCategories ac', 'at.assetCategories_id=ac.assetCategories_id', 'LEFT');
        $this->db->orderBy('at.assetTypes_name', 'ASC');

        $assets = $this->db->get('assets a', 30, [
            'a.assets_id', 'a.assets_tag', 'at.assetTypes_name', 'ac.assetCategories_name',
            'at.assetTypes_value', 'at.assetTypes_mass'
        ]) ?: [];

        return ['assets' => $assets, 'count' => count($assets)];
    }

    private function toolGetDashboardStats(): array
    {
        $today = date('Y-m-d');
        $weekFromNow = date('Y-m-d', strtotime('+7 days'));

        // Active projects
        $this->db->where('instances_id', $this->instanceId);
        $this->db->where('projects_deleted', 0);
        $this->db->where('projects_archived', 0);
        $this->db->where('projects_dates_use_start', $today, '<=');
        $this->db->where('projects_dates_use_end', $today, '>=');
        $activeProjects = (int)($this->db->getValue('projects', 'COUNT(*)') ?: 0);

        // Upcoming projects (next 7 days)
        $this->db->where('instances_id', $this->instanceId);
        $this->db->where('projects_deleted', 0);
        $this->db->where('projects_archived', 0);
        $this->db->where('projects_dates_use_start', $today, '>');
        $this->db->where('projects_dates_use_start', $weekFromNow, '<=');
        $upcomingProjects = (int)($this->db->getValue('projects', 'COUNT(*)') ?: 0);

        // Total assets
        $this->db->where('instances_id', $this->instanceId);
        $this->db->where('assets_deleted', 0);
        $totalAssets = (int)($this->db->getValue('assets', 'COUNT(*)') ?: 0);

        // Total clients
        $this->db->where('instances_id', $this->instanceId);
        $this->db->where('clients_deleted', 0);
        $totalClients = (int)($this->db->getValue('clients', 'COUNT(*)') ?: 0);

        // Overdue returns
        $overdue = $this->toolGetOverdueReturns([]);

        return [
            'active_projects' => $activeProjects,
            'upcoming_projects_7d' => $upcomingProjects,
            'total_assets' => $totalAssets,
            'total_clients' => $totalClients,
            'overdue_returns' => $overdue['count'],
        ];
    }

    private function toolGetUpcomingProjects(array $input): array
    {
        $days = $input['days'] ?? 7;
        $today = date('Y-m-d');
        $endDate = date('Y-m-d', strtotime("+{$days} days"));

        $this->db->where('p.instances_id', $this->instanceId);
        $this->db->where('p.projects_deleted', 0);
        $this->db->where('p.projects_archived', 0);
        $this->db->where('p.projects_dates_use_start', $today, '>=');
        $this->db->where('p.projects_dates_use_start', $endDate, '<=');
        $this->db->join('clients c', 'p.clients_id=c.clients_id', 'LEFT');
        $this->db->join('projectsStatuses ps', 'p.projectsStatuses_id=ps.projectsStatuses_id', 'LEFT');
        $this->db->orderBy('p.projects_dates_use_start', 'ASC');

        $projects = $this->db->get('projects p', 20, [
            'p.projects_id', 'p.projects_name', 'p.projects_dates_use_start', 'p.projects_dates_use_end',
            'c.clients_name', 'ps.projectsStatuses_name'
        ]) ?: [];

        return ['upcoming_projects' => $projects, 'count' => count($projects), 'days' => $days];
    }

    // ── API Call ──

    private function callApi(string $apiKey, array $body): ?array
    {
        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $apiKey,
                'anthropic-version: ' . self::API_VERSION,
            ],
            CURLOPT_POSTFIELDS => json_encode($body),
        ]);

        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$raw) return null;
        $data = json_decode($raw, true);

        // Log usage
        if ($data) $this->logChatUsage($data);

        return $data ?: null;
    }

    private function logChatUsage(array $response): void
    {
        $inputTokens = $response['usage']['input_tokens'] ?? 0;
        $outputTokens = $response['usage']['output_tokens'] ?? 0;
        $model = $response['model'] ?? 'claude-haiku-4-5-20251001';

        $costMap = [
            'claude-haiku-4-5-20251001' => ['input' => 1.0, 'output' => 5.0],
            'claude-sonnet-4-6' => ['input' => 3.0, 'output' => 15.0],
            'claude-opus-4-6' => ['input' => 15.0, 'output' => 75.0],
        ];
        $costs = $costMap[$model] ?? ['input' => 1.0, 'output' => 5.0];
        $cost = ($inputTokens * $costs['input'] + $outputTokens * $costs['output']) / 1_000_000;

        $this->db->insert('ai_usage_log', [
            'instances_id' => $this->instanceId,
            'feature' => 'chat',
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost_estimate_usd' => round($cost, 6),
            'users_userid' => $this->userId,
        ]);
    }
}
