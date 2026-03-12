<?php
/**
 * AI-Aktions-Queue Service
 *
 * Zentrale Warteschlange fuer KI-generierte Aktionen.
 *
 * PRINZIP:
 *   - Eingehend (Mails einsortieren, Kategorisieren, etc.) = automatisch, kein Approval noetig
 *   - Ausgehend (E-Mails an Kunden, Mahnungen, etc.) = IMMER Bestaetigung noetig
 *
 * Tabelle: ai_action_queue
 *   id, instances_id, action_type, direction ('inbound'|'outbound'),
 *   status ('pending'|'approved'|'rejected'|'auto_executed'|'executed'),
 *   title, description, payload_json, result_json,
 *   related_type, related_id,
 *   created_at, reviewed_at, reviewed_by, executed_at
 */
class AiActionQueueService
{
    private $db;
    private int $instanceId;

    // Aktionen die IMMER Bestaetigung brauchen (= alles was zum Kunden geht)
    private const OUTBOUND_ACTIONS = [
        'send_email',           // E-Mail an Kunden senden
        'send_reminder',        // Zahlungserinnerung senden
        'send_dunning',         // Mahnung senden
        'send_quote',           // Angebot senden
        'send_invoice',         // Rechnung senden
        'send_delivery_note',   // Lieferschein senden
        'update_client_data',   // Kundendaten aendern (vorsichtshalber)
    ];

    // Aktionen die automatisch ausgefuehrt werden (= interne Verarbeitung)
    private const AUTO_ACTIONS = [
        'categorize_email',     // Eingehende Mail kategorisieren/zuordnen
        'assign_email_project', // Mail einem Projekt zuordnen
        'categorize_expense',   // Ausgabe kategorisieren
        'flag_maintenance',     // Wartung faellig markieren
        'summarize_project',    // Projekt-Zusammenfassung erstellen
        'scan_invoice',         // Eingehende Rechnung scannen
        'tag_suggestion',       // Tag-Vorschlag fuer Asset
    ];

    public function __construct($db, int $instanceId)
    {
        $this->db = $db;
        $this->instanceId = $instanceId;
    }

    /**
     * Neue Aktion in die Queue einreihen
     *
     * @return int|null Die Queue-ID oder null bei Fehler
     */
    public function enqueue(
        string $actionType,
        string $title,
        string $description,
        array $payload,
        ?string $relatedType = null,
        ?int $relatedId = null
    ): ?int {
        $direction = in_array($actionType, self::OUTBOUND_ACTIONS) ? 'outbound' : 'inbound';
        $autoExecute = in_array($actionType, self::AUTO_ACTIONS);

        $data = [
            'instances_id' => $this->instanceId,
            'action_type' => $actionType,
            'direction' => $direction,
            'status' => $autoExecute ? 'auto_executed' : 'pending',
            'title' => $title,
            'description' => $description,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        if ($autoExecute) {
            $data['executed_at'] = date('Y-m-d H:i:s');
        }

        $id = $this->db->insert('ai_action_queue', $data);
        return $id ?: null;
    }

    /**
     * Aktion genehmigen und ausfuehren
     */
    public function approve(int $queueId, int $userId, ?array $modifications = null): bool
    {
        $this->db->where('id', $queueId);
        $this->db->where('instances_id', $this->instanceId);
        $this->db->where('status', 'pending');
        $item = $this->db->getOne('ai_action_queue');

        if (!$item) return false;

        $payload = json_decode($item['payload_json'], true) ?: [];
        if ($modifications) {
            $payload = array_merge($payload, $modifications);
        }

        // Status auf approved setzen
        $this->db->where('id', $queueId);
        $this->db->update('ai_action_queue', [
            'status' => 'approved',
            'reviewed_at' => date('Y-m-d H:i:s'),
            'reviewed_by' => $userId,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        // Aktion ausfuehren
        $result = $this->executeAction($item['action_type'], $payload);

        $this->db->where('id', $queueId);
        $this->db->update('ai_action_queue', [
            'status' => $result['success'] ? 'executed' : 'error',
            'result_json' => json_encode($result, JSON_UNESCAPED_UNICODE),
            'executed_at' => date('Y-m-d H:i:s'),
        ]);

        return $result['success'];
    }

    /**
     * Aktion ablehnen
     */
    public function reject(int $queueId, int $userId, ?string $reason = null): bool
    {
        $this->db->where('id', $queueId);
        $this->db->where('instances_id', $this->instanceId);
        $this->db->where('status', 'pending');

        return (bool)$this->db->update('ai_action_queue', [
            'status' => 'rejected',
            'reviewed_at' => date('Y-m-d H:i:s'),
            'reviewed_by' => $userId,
            'result_json' => json_encode(['reason' => $reason], JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * Pendende Aktionen abrufen (fuer Dashboard)
     */
    public function getPending(int $limit = 20): array
    {
        $this->db->where('instances_id', $this->instanceId);
        $this->db->where('status', 'pending');
        $this->db->orderBy('created_at', 'DESC');
        return $this->db->get('ai_action_queue', $limit) ?: [];
    }

    /**
     * Anzahl pendender Aktionen
     */
    public function getPendingCount(): int
    {
        $this->db->where('instances_id', $this->instanceId);
        $this->db->where('status', 'pending');
        return (int)($this->db->getValue('ai_action_queue', 'COUNT(*)') ?: 0);
    }

    /**
     * Letzte Aktivitaeten (fuer Feed)
     */
    public function getRecentActivity(int $limit = 50): array
    {
        $this->db->where('instances_id', $this->instanceId);
        $this->db->orderBy('created_at', 'DESC');
        return $this->db->get('ai_action_queue', $limit) ?: [];
    }

    /**
     * Stats fuer Dashboard
     */
    public function getStats(): array
    {
        $this->db->where('instances_id', $this->instanceId);
        $this->db->where('created_at', date('Y-m-d 00:00:00'), '>=');
        $today = $this->db->getValue('ai_action_queue', 'COUNT(*)') ?: 0;

        $this->db->where('instances_id', $this->instanceId);
        $this->db->where('status', 'auto_executed');
        $this->db->where('created_at', date('Y-m-d 00:00:00'), '>=');
        $autoToday = $this->db->getValue('ai_action_queue', 'COUNT(*)') ?: 0;

        return [
            'pending' => $this->getPendingCount(),
            'today_total' => (int)$today,
            'today_auto' => (int)$autoToday,
        ];
    }

    // ── Private: Aktion ausfuehren ──

    private function executeAction(string $actionType, array $payload): array
    {
        switch ($actionType) {
            case 'send_email':
                return $this->executeSendEmail($payload);
            case 'send_reminder':
            case 'send_dunning':
                return $this->executeSendDunning($payload);
            case 'send_quote':
            case 'send_invoice':
            case 'send_delivery_note':
                return $this->executeSendDocument($payload);
            case 'assign_email_project':
                return $this->executeAssignEmail($payload);
            case 'categorize_email':
                return $this->executeCategorizeEmail($payload);
            default:
                return ['success' => true, 'message' => 'Logged as ' . $actionType];
        }
    }

    private function executeSendEmail(array $payload): array
    {
        if (empty($payload['to']) || empty($payload['subject']) || empty($payload['body'])) {
            return ['success' => false, 'message' => 'Fehlende Felder: to, subject, body'];
        }

        require_once __DIR__ . '/../api/notifications/email/email.php';
        $sent = @sendEmail(
            ['userData' => ['users_email' => $payload['to'], 'users_name1' => $payload['to_name'] ?? '', 'users_name2' => '']],
            $this->instanceId,
            $payload['subject'],
            $payload['body']
        );

        return ['success' => (bool)$sent, 'message' => $sent ? 'E-Mail gesendet' : 'Versand fehlgeschlagen'];
    }

    private function executeSendDunning(array $payload): array
    {
        if (empty($payload['invoice_id']) || !isset($payload['dunning_level'])) {
            return ['success' => false, 'message' => 'Fehlende Felder'];
        }

        require_once __DIR__ . '/DunningService.php';
        $dunning = new DunningService($this->db);
        $result = $dunning->createDunning(
            $this->instanceId,
            (int)$payload['invoice_id'],
            (int)$payload['dunning_level']
        );

        return ['success' => (bool)$result, 'message' => $result ? 'Mahnung erstellt' : 'Fehler beim Erstellen'];
    }

    private function executeSendDocument(array $payload): array
    {
        // Dokument per E-Mail versenden
        if (empty($payload['document_id']) || empty($payload['to'])) {
            return ['success' => false, 'message' => 'Fehlende Felder'];
        }

        require_once __DIR__ . '/InvoiceMailService.php';
        $mailService = new InvoiceMailService($this->db, $this->instanceId);
        $sent = $mailService->sendDocument(
            (int)$payload['document_id'],
            $payload['to'],
            $payload['subject'] ?? null,
            $payload['body'] ?? null
        );

        return ['success' => (bool)$sent, 'message' => $sent ? 'Dokument gesendet' : 'Versand fehlgeschlagen'];
    }

    private function executeAssignEmail(array $payload): array
    {
        if (empty($payload['email_id']) || empty($payload['project_id'])) {
            return ['success' => false, 'message' => 'Fehlende Felder'];
        }

        $this->db->where('emailReceived_id', (int)$payload['email_id']);
        $this->db->where('instances_id', $this->instanceId);
        $result = $this->db->update('emailReceived', [
            'projects_id' => (int)$payload['project_id'],
        ]);

        return ['success' => (bool)$result, 'message' => $result ? 'E-Mail zugeordnet' : 'Fehler'];
    }

    private function executeCategorizeEmail(array $payload): array
    {
        // Speichere KI-Kategorisierung als Tag/Notiz
        if (empty($payload['email_id'])) {
            return ['success' => false, 'message' => 'Fehlende email_id'];
        }

        $updates = [];
        if (!empty($payload['category'])) $updates['ai_category'] = $payload['category'];
        if (!empty($payload['priority'])) $updates['ai_priority'] = $payload['priority'];
        if (!empty($payload['summary'])) $updates['ai_summary'] = $payload['summary'];
        if (!empty($payload['client_id'])) $updates['clients_id'] = (int)$payload['client_id'];
        if (!empty($payload['project_id'])) $updates['projects_id'] = (int)$payload['project_id'];

        if (empty($updates)) return ['success' => true, 'message' => 'Nichts zu aktualisieren'];

        $this->db->where('emailReceived_id', (int)$payload['email_id']);
        $this->db->where('instances_id', $this->instanceId);
        $result = $this->db->update('emailReceived', $updates);

        return ['success' => (bool)$result, 'message' => 'E-Mail kategorisiert'];
    }
}
