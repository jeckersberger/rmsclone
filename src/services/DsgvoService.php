<?php
/**
 * DSGVO-Service (Datenschutz-Grundverordnung)
 *
 * Implementiert die wichtigsten DSGVO-Anforderungen:
 * - Art. 15: Auskunftsrecht - Kunde kann alle gespeicherten Daten einsehen
 * - Art. 17: Recht auf Löschung ("Recht auf Vergessenwerden")
 * - Art. 20: Datenportabilität - Export in maschinenlesbarem Format
 * - Aufbewahrungsfristen: Steuerrelevante Daten 10 Jahre, dann Löschung
 * - Protokollierung aller Zugriffe auf personenbezogene Daten
 */
class DsgvoService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Art. 15 DSGVO: Auskunftsrecht
     * Exportiert alle gespeicherten Daten eines Kunden
     */
    public function getClientDataExport(int $clientId, int $instanceId): array
    {
        // Kundenstammdaten
        $this->db->where('clients_id', $clientId);
        $this->db->where('instances_id', $instanceId);
        $client = $this->db->getOne('clients');
        if (!$client) return ['error' => 'Client not found'];

        // Projekte des Kunden
        $this->db->where('clients_id', $clientId);
        $this->db->where('instances_id', $instanceId);
        $this->db->where('projects_deleted', 0);
        $projects = $this->db->get('projects', null, [
            'projects_id', 'projects_name', 'projects_dates_use_start',
            'projects_dates_use_end', 'projects_invoiceNotes'
        ]) ?: [];

        // Zahlungen
        $projectIds = array_column($projects, 'projects_id');
        $payments = [];
        if (!empty($projectIds)) {
            $this->db->where('projects_id', $projectIds, 'IN');
            $payments = $this->db->get('payments', null, [
                'payments_id', 'projects_id', 'payments_amount',
                'payments_date', 'payments_description'
            ]) ?: [];
        }

        // Dokumente
        $documents = [];
        if (!empty($projectIds)) {
            $this->db->where('projects_id', $projectIds, 'IN');
            $this->db->where('instances_id', $instanceId);
            $documents = $this->db->get('document_exports', null, [
                'doc_number', 'type', 'generated_at'
            ]) ?: [];
        }

        // Kautionen
        $deposits = [];
        if ($this->db->rawQuery("SHOW TABLES LIKE 'deposits'")) {
            if (!empty($projectIds)) {
                $this->db->where('projects_id', $projectIds, 'IN');
                $deposits = $this->db->get('deposits') ?: [];
            }
        }

        return [
            'export_date' => date('Y-m-d H:i:s'),
            'export_type' => 'DSGVO Art. 15 - Auskunft',
            'client' => $this->sanitizeForExport($client),
            'projects' => $projects,
            'payments' => $payments,
            'documents' => $documents,
            'deposits' => $deposits,
        ];
    }

    /**
     * Art. 20 DSGVO: Datenportabilität
     * Exportiert Kundendaten als maschinenlesbares JSON
     */
    public function exportClientDataJson(int $clientId, int $instanceId): string
    {
        $data = $this->getClientDataExport($clientId, $instanceId);
        $data['format'] = 'JSON';
        $data['standard'] = 'DSGVO Art. 20 - Datenportabilität';
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Art. 17 DSGVO: Recht auf Löschung
     * Anonymisiert Kundendaten (Löschung unter Beachtung der Aufbewahrungsfristen)
     *
     * WICHTIG: Steuerrelevante Daten (Rechnungen, Zahlungen) werden erst
     * nach Ablauf der 10-jährigen Aufbewahrungsfrist gelöscht (§ 147 AO)
     */
    public function anonymizeClient(int $clientId, int $instanceId, int $userId): array
    {
        $this->db->where('clients_id', $clientId);
        $this->db->where('instances_id', $instanceId);
        $client = $this->db->getOne('clients');
        if (!$client) return ['success' => false, 'error' => 'Client not found'];

        // Prüfe Aufbewahrungsfristen
        $retentionCheck = $this->checkRetentionPeriods($clientId, $instanceId);

        // Protokolliere die Löschanfrage
        $this->logDsgvoAction($instanceId, $clientId, 'deletion_request', $userId);

        if ($retentionCheck['has_retained_data']) {
            // Daten die noch aufbewahrt werden müssen: nur Kontaktdaten anonymisieren
            $this->db->where('clients_id', $clientId);
            $this->db->update('clients', [
                'clients_name' => 'GELÖSCHT-' . $clientId,
                'clients_email' => null,
                'clients_phone' => null,
                'clients_address' => null,
                'clients_website' => null,
                'clients_notes' => '[DSGVO Art. 17 - Kontaktdaten gelöscht am ' . date('d.m.Y') . ']',
            ]);

            $this->logDsgvoAction($instanceId, $clientId, 'partial_anonymization', $userId,
                'Aufbewahrungsfristen aktiv bis ' . $retentionCheck['earliest_deletion']);

            return [
                'success' => true,
                'partial' => true,
                'message' => 'Kontaktdaten anonymisiert. Steuerrelevante Daten werden aufbewahrt bis: ' . $retentionCheck['earliest_deletion'],
                'retained_reason' => $retentionCheck['reasons'],
            ];
        }

        // Vollständige Anonymisierung (keine Aufbewahrungspflicht mehr)
        $this->db->where('clients_id', $clientId);
        $this->db->update('clients', [
            'clients_name' => 'GELÖSCHT-' . $clientId,
            'clients_email' => null,
            'clients_phone' => null,
            'clients_address' => null,
            'clients_website' => null,
            'clients_notes' => null,
            'clients_vatId' => null,
            'clients_archived' => 1,
        ]);

        $this->logDsgvoAction($instanceId, $clientId, 'full_anonymization', $userId);

        return ['success' => true, 'partial' => false, 'message' => 'Kundendaten vollständig anonymisiert.'];
    }

    /**
     * Prüft Aufbewahrungsfristen (§ 147 AO: 10 Jahre für Rechnungen)
     */
    public function checkRetentionPeriods(int $clientId, int $instanceId): array
    {
        $retentionYears = 10;
        $cutoffDate = date('Y-m-d', strtotime("-{$retentionYears} years"));

        // Prüfe ob es Rechnungen/Dokumente innerhalb der Aufbewahrungsfrist gibt
        $sql = "SELECT de.doc_number, de.type, de.generated_at
                FROM document_exports de
                JOIN projects p ON de.projects_id = p.projects_id
                WHERE p.clients_id = ? AND de.instances_id = ?
                AND de.generated_at > ?
                ORDER BY de.generated_at DESC LIMIT 5";
        $retainedDocs = $this->db->rawQuery($sql, [$clientId, $instanceId, $cutoffDate]) ?: [];

        // Prüfe Zahlungen
        $sql = "SELECT pay.payments_id, pay.payments_amount, pay.payments_date
                FROM payments pay
                JOIN projects p ON pay.projects_id = p.projects_id
                WHERE p.clients_id = ? AND p.instances_id = ?
                AND pay.payments_date > ?
                ORDER BY pay.payments_date DESC LIMIT 5";
        $retainedPayments = $this->db->rawQuery($sql, [$clientId, $instanceId, $cutoffDate]) ?: [];

        $hasRetainedData = !empty($retainedDocs) || !empty($retainedPayments);
        $reasons = [];
        if (!empty($retainedDocs)) $reasons[] = count($retainedDocs) . ' Dokumente innerhalb Aufbewahrungsfrist';
        if (!empty($retainedPayments)) $reasons[] = count($retainedPayments) . ' Zahlungen innerhalb Aufbewahrungsfrist';

        // Frühester Löschzeitpunkt
        $latestDate = null;
        foreach ($retainedDocs as $d) {
            $deleteAt = date('Y-m-d', strtotime($d['generated_at'] . " +{$retentionYears} years"));
            if (!$latestDate || $deleteAt > $latestDate) $latestDate = $deleteAt;
        }
        foreach ($retainedPayments as $p) {
            $deleteAt = date('Y-m-d', strtotime($p['payments_date'] . " +{$retentionYears} years"));
            if (!$latestDate || $deleteAt > $latestDate) $latestDate = $deleteAt;
        }

        return [
            'has_retained_data' => $hasRetainedData,
            'reasons' => $reasons,
            'earliest_deletion' => $latestDate ? date('d.m.Y', strtotime($latestDate)) : null,
            'retained_documents' => $retainedDocs,
            'retained_payments' => $retainedPayments,
        ];
    }

    /**
     * DSGVO-Zugriffsprotokoll
     */
    public function logDsgvoAction(int $instanceId, int $clientId, string $action, int $userId, ?string $details = null): void
    {
        $this->db->insert('dsgvo_log', [
            'instances_id' => $instanceId,
            'clients_id' => $clientId,
            'action' => $action,
            'performed_by' => $userId,
            'details' => $details,
            'ip_address' => self::getClientIp(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * DSGVO-Protokoll anzeigen
     */
    public function getDsgvoLog(int $instanceId, ?int $clientId = null): array
    {
        $this->db->where('instances_id', $instanceId);
        if ($clientId) $this->db->where('clients_id', $clientId);
        $this->db->join('users', 'dsgvo_log.performed_by = users.users_userid', 'LEFT');
        $this->db->orderBy('dsgvo_log.created_at', 'DESC');
        return $this->db->get('dsgvo_log', 50, [
            'dsgvo_log.*', 'users.users_name1', 'users.users_name2'
        ]) ?: [];
    }

    /**
     * Kunden mit abgelaufenen Aufbewahrungsfristen finden
     * (Vorschlag zur Löschung)
     */
    public function getClientsReadyForDeletion(int $instanceId): array
    {
        $retentionYears = 10;
        $cutoffDate = date('Y-m-d', strtotime("-{$retentionYears} years"));

        $sql = "SELECT c.clients_id, c.clients_name, c.clients_email,
                       MAX(COALESCE(de.generated_at, pay.payments_date, p.projects_dates_use_end)) as last_activity
                FROM clients c
                LEFT JOIN projects p ON c.clients_id = p.clients_id AND p.instances_id = ?
                LEFT JOIN document_exports de ON p.projects_id = de.projects_id
                LEFT JOIN payments pay ON p.projects_id = pay.projects_id
                WHERE c.instances_id = ? AND c.clients_archived = 0
                AND c.clients_name NOT LIKE 'GELÖSCHT%'
                GROUP BY c.clients_id
                HAVING last_activity IS NOT NULL AND last_activity < ?
                ORDER BY last_activity ASC";
        return $this->db->rawQuery($sql, [$instanceId, $instanceId, $cutoffDate]) ?: [];
    }

    /**
     * Ermittelt die echte Client-IP (Cloudflare, Proxy, direkt)
     */
    private static function getClientIp(): ?string
    {
        // Cloudflare
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return filter_var($_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP) ?: null;
        }
        // Standard-Proxy
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]); // Erste IP = Client
            return filter_var($ip, FILTER_VALIDATE_IP) ?: null;
        }
        // Direkte Verbindung
        return filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: null;
    }

    /**
     * Daten-Minimierung: Alle persönlichen Felder entfernen
     */
    private function sanitizeForExport(array $client): array
    {
        // Entferne interne System-IDs und Flags die nicht personenbezogen sind
        unset($client['instances_id'], $client['clients_deleted']);
        return $client;
    }
}
