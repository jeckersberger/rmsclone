<?php
/**
 * WebhookService - Webhook-System fuer externe Integrationen
 *
 * Ermoeglicht das Registrieren von Webhook-URLs, die bei
 * bestimmten Ereignissen automatisch benachrichtigt werden.
 *
 * Events: project.created, project.updated, project.deleted,
 *         invoice.created, invoice.paid, asset.checked_out, asset.returned,
 *         client.created, document.created
 */
class WebhookService
{
    private $db;

    const VALID_EVENTS = [
        'project.created', 'project.updated', 'project.deleted', 'project.completed',
        'invoice.created', 'invoice.paid', 'invoice.overdue',
        'asset.checked_out', 'asset.returned', 'asset.damaged',
        'client.created', 'client.updated',
        'document.created', 'document.sent',
        'quote.accepted', 'quote.rejected',
    ];

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Webhook registrieren
     */
    public function register(int $instanceId, string $url, array $events, string $name = '', string $secret = ''): array
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'error' => 'Ungueltige URL'];
        }

        // Nur HTTPS in Produktion
        if (parse_url($url, PHP_URL_SCHEME) !== 'https') {
            return ['success' => false, 'error' => 'Nur HTTPS-URLs erlaubt'];
        }

        $validEvents = array_intersect($events, self::VALID_EVENTS);
        if (empty($validEvents)) {
            return ['success' => false, 'error' => 'Keine gueltigen Events angegeben'];
        }

        if (empty($secret)) {
            $secret = bin2hex(random_bytes(32));
        }

        $id = $this->db->insert('webhooks', [
            'instances_id' => $instanceId,
            'name' => $name ?: parse_url($url, PHP_URL_HOST),
            'url' => $url,
            'secret' => $secret,
            'events' => json_encode(array_values($validEvents)),
            'active' => 1,
            'deleted' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'last_triggered_at' => null,
            'failure_count' => 0,
        ]);

        return $id
            ? ['success' => true, 'id' => $id, 'secret' => $secret]
            : ['success' => false, 'error' => 'Fehler beim Speichern'];
    }

    /**
     * Event ausloesen und alle registrierten Webhooks benachrichtigen
     */
    public function trigger(string $event, int $instanceId, array $payload = []): array
    {
        if (!in_array($event, self::VALID_EVENTS)) return ['sent' => 0, 'errors' => []];

        $this->db->where('instances_id', $instanceId);
        $this->db->where('active', 1);
        $this->db->where('deleted', 0);
        $webhooks = $this->db->get('webhooks') ?: [];

        $sent = 0;
        $errors = [];

        foreach ($webhooks as $webhook) {
            $events = json_decode($webhook['events'], true) ?: [];
            if (!in_array($event, $events)) continue;

            $result = $this->send($webhook, $event, $payload);
            if ($result['success']) {
                $sent++;
            } else {
                $errors[] = ['webhook_id' => $webhook['id'], 'error' => $result['error']];
            }
        }

        return ['sent' => $sent, 'errors' => $errors];
    }

    /**
     * Webhook-Payload senden
     */
    private function send(array $webhook, string $event, array $payload): array
    {
        $body = json_encode([
            'event' => $event,
            'timestamp' => date('c'),
            'data' => $payload,
        ]);

        $signature = hash_hmac('sha256', $body, $webhook['secret']);

        $ch = curl_init($webhook['url']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Webhook-Signature: sha256=' . $signature,
                'X-Webhook-Event: ' . $event,
                'User-Agent: AdamRMS-Webhook/1.0',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Status aktualisieren
        $this->db->where('id', $webhook['id']);
        if ($httpCode >= 200 && $httpCode < 300) {
            $this->db->update('webhooks', [
                'last_triggered_at' => date('Y-m-d H:i:s'),
                'failure_count' => 0,
            ]);
            $this->logDelivery($webhook['id'], $event, $httpCode, true);
            return ['success' => true, 'http_code' => $httpCode];
        }

        // Fehler
        $failureCount = intval($webhook['failure_count']) + 1;
        $updateData = [
            'failure_count' => $failureCount,
            'last_error' => substr($error ?: "HTTP $httpCode", 0, 255),
        ];
        // Nach 10 Fehlern deaktivieren
        if ($failureCount >= 10) {
            $updateData['active'] = 0;
        }
        $this->db->update('webhooks', $updateData);

        $this->logDelivery($webhook['id'], $event, $httpCode, false, $error);
        return ['success' => false, 'error' => $error ?: "HTTP $httpCode"];
    }

    /**
     * Webhooks einer Instanz auflisten
     */
    public function list(int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('deleted', 0);
        $this->db->orderBy('created_at', 'DESC');
        return $this->db->get('webhooks', null, [
            'id', 'name', 'url', 'events', 'active',
            'last_triggered_at', 'failure_count', 'created_at',
        ]) ?: [];
    }

    /**
     * Webhook loeschen
     */
    public function delete(int $id, int $instanceId): bool
    {
        $this->db->where('id', $id);
        $this->db->where('instances_id', $instanceId);
        return (bool) $this->db->update('webhooks', ['deleted' => 1]);
    }

    /**
     * Webhook-Log abrufen
     */
    public function getDeliveryLog(int $webhookId, int $limit = 20): array
    {
        $this->db->where('webhook_id', $webhookId);
        $this->db->orderBy('created_at', 'DESC');
        return $this->db->get('webhook_deliveries', $limit) ?: [];
    }

    private function logDelivery(int $webhookId, string $event, int $httpCode, bool $success, string $error = ''): void
    {
        $this->db->insert('webhook_deliveries', [
            'webhook_id' => $webhookId,
            'event' => $event,
            'http_code' => $httpCode,
            'success' => $success ? 1 : 0,
            'error' => $error,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
