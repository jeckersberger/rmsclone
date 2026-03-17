<?php
/**
 * CustomerPortalService - Kunden-Self-Service-Portal
 *
 * Ermoeglicht Kunden:
 * - Eigene Projekte einsehen
 * - Rechnungen herunterladen
 * - Angebote bestaetigen/ablehnen
 * - Kontaktdaten aktualisieren
 * - Feedback geben
 */
class CustomerPortalService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Portal-Zugangstoken generieren
     */
    public function generateAccessToken(int $clientId): array
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

        // Altes Token deaktivieren
        $this->db->where('client_id', $clientId);
        $this->db->where('active', 1);
        $this->db->update('customer_portal_tokens', ['active' => 0]);

        $id = $this->db->insert('customer_portal_tokens', [
            'client_id' => $clientId,
            'token' => hash('sha256', $token),
            'expires_at' => $expiresAt,
            'active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $id
            ? ['success' => true, 'token' => $token, 'expires_at' => $expiresAt]
            : ['success' => false, 'error' => 'Token-Generierung fehlgeschlagen'];
    }

    /**
     * Token validieren und Client-ID zurueckgeben
     */
    public function validateToken(string $token): ?int
    {
        $hashedToken = hash('sha256', $token);
        $this->db->where('token', $hashedToken);
        $this->db->where('active', 1);
        $this->db->where('expires_at', date('Y-m-d H:i:s'), '>=');
        $result = $this->db->getOne('customer_portal_tokens', null, ['client_id']);
        return $result ? (int) $result['client_id'] : null;
    }

    /**
     * Projekte eines Kunden abrufen
     */
    public function getClientProjects(int $clientId, int $limit = 20): array
    {
        $this->db->where('clients_id', $clientId);
        $this->db->where('projects_deleted', 0);
        $this->db->orderBy('projects_dates_use_start', 'DESC');
        return $this->db->get('projects', $limit, [
            'projects_id', 'projects_name',
            'projects_dates_use_start', 'projects_dates_use_end',
            'projects_status', 'projects_value_total',
        ]) ?: [];
    }

    /**
     * Rechnungen eines Kunden abrufen
     */
    public function getClientInvoices(int $clientId, int $limit = 20): array
    {
        $this->db->where('p.clients_id', $clientId);
        $this->db->where('de.type', ['invoice', 'credit_note'], 'IN');
        $this->db->join('projects p', 'de.projects_id = p.projects_id', 'INNER');
        $this->db->orderBy('de.generated_at', 'DESC');
        $this->db->limit($limit);
        return $this->db->get('document_exports de', null, [
            'de.id', 'de.doc_number', 'de.type',
            'de.totals_json', 'de.generated_at', 'de.status',
            'p.projects_name'
        ]) ?: [];
    }

    /**
     * Angebote eines Kunden (zur Bestaetigung)
     */
    public function getClientQuotes(int $clientId): array
    {
        $sql = "SELECT de.document_exports_id, de.document_number,
                       de.total_gross, de.created_at, de.status, de.valid_until,
                       p.projects_name
                FROM document_exports de
                JOIN projects p ON de.projects_id = p.projects_id
                WHERE p.clients_id = ?
                AND de.document_type = 'quote'
                AND de.status IN ('created', 'sent')
                AND de.deleted = 0
                ORDER BY de.created_at DESC";
        return $this->db->rawQuery($sql, [$clientId]) ?: [];
    }

    /**
     * Angebot bestaetigen
     */
    public function acceptQuote(int $documentId, int $clientId): array
    {
        // Sicherstellen, dass das Dokument dem Kunden gehoert
        $sql = "SELECT de.document_exports_id, de.status
                FROM document_exports de
                JOIN projects p ON de.projects_id = p.projects_id
                WHERE de.document_exports_id = ? AND p.clients_id = ?
                AND de.document_type = 'quote' AND de.deleted = 0";
        $doc = $this->db->rawQuery($sql, [$documentId, $clientId]);

        if (!$doc) return ['success' => false, 'error' => 'Angebot nicht gefunden'];
        if ($doc[0]['status'] === 'accepted') return ['success' => false, 'error' => 'Bereits angenommen'];

        $this->db->where('document_exports_id', $documentId);
        $this->db->update('document_exports', [
            'status' => 'accepted',
            'status_changed_at' => date('Y-m-d H:i:s'),
        ]);

        return ['success' => true];
    }

    /**
     * Feedback zu einem Projekt abgeben
     */
    public function submitFeedback(int $projectId, int $clientId, int $rating, string $comment = ''): array
    {
        // Pruefen, ob Projekt dem Kunden gehoert
        $this->db->where('projects_id', $projectId);
        $this->db->where('clients_id', $clientId);
        $this->db->where('projects_deleted', 0);
        if (!$this->db->getOne('projects', null, ['projects_id'])) {
            return ['success' => false, 'error' => 'Projekt nicht gefunden'];
        }

        $rating = max(1, min(5, intval($rating)));

        $id = $this->db->insert('project_feedback', [
            'projects_id' => $projectId,
            'clients_id' => $clientId,
            'rating' => $rating,
            'comment' => $comment,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $id
            ? ['success' => true, 'id' => $id]
            : ['success' => false, 'error' => 'Fehler beim Speichern'];
    }

    /**
     * Kontaktdaten aktualisieren (Self-Service)
     */
    public function updateContactInfo(int $clientId, array $data): array
    {
        $allowedFields = ['clients_phone', 'clients_address', 'clients_deliveryAddress'];
        $updateData = [];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = trim($data[$field]);
            }
        }

        if (empty($updateData)) return ['success' => false, 'error' => 'Keine aenderbaren Felder'];

        $updateData['clients_updated'] = date('Y-m-d H:i:s');
        $this->db->where('clients_id', $clientId);
        $result = $this->db->update('clients', $updateData);

        return ['success' => (bool) $result];
    }
}
