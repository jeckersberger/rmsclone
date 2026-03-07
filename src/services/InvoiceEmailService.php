<?php
/**
 * InvoiceEmailService - Automatischer Dokumentversand per E-Mail
 *
 * Verwaltet den automatischen und manuellen E-Mail-Versand von Rechnungen,
 * Angeboten und anderen Dokumenten. Nutzt den vorhandenen InvoiceMailService
 * fuer den eigentlichen Versand und erweitert ihn um:
 *   - Automatischen Versand fuer Dokumente mit auto_send_email Flag
 *   - E-Mail-Protokollierung in document_email_log
 *   - Status-Aktualisierung in document_lifecycle
 */
class InvoiceEmailService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Send invoice/document to client via email
     */
    public function sendDocument(int $instanceId, int $docLifecycleId, ?string $customSubject = null, ?string $customBody = null): array
    {
        // Get document
        $this->db->where('id', $docLifecycleId);
        $this->db->where('instances_id', $instanceId);
        $doc = $this->db->getOne('document_lifecycle');
        if (!$doc) {
            throw new \RuntimeException('Dokument nicht gefunden.');
        }

        // Get project and client
        $this->db->where('projects_id', $doc['projects_id']);
        $this->db->where('projects_deleted', 0);
        $project = $this->db->getOne('projects');
        if (!$project) {
            throw new \RuntimeException('Projekt nicht gefunden.');
        }

        $this->db->where('clients_id', $project['clients_id']);
        $client = $this->db->getOne('clients');
        if (!$client) {
            throw new \RuntimeException('Kunde nicht gefunden.');
        }

        // Get business settings
        $this->db->where('instances_id', $instanceId);
        $business = $this->db->getOne('instances');

        // Get client email
        $email = $client['clients_email'] ?? null;
        if (!$email) {
            throw new \RuntimeException('Keine E-Mail-Adresse fuer Kunden hinterlegt.');
        }

        // Build subject and body
        $typeLabels = [
            'invoice' => 'Rechnung', 'quote' => 'Angebot',
            'partial_invoice' => 'Abschlagsrechnung', 'credit_note' => 'Gutschrift',
            'order_confirmation' => 'Auftragsbestaetigung', 'cancellation' => 'Storno',
            'delivery_note' => 'Lieferschein',
        ];
        $typeLabel = $typeLabels[$doc['doc_type']] ?? 'Dokument';

        $subject = $customSubject ?: sprintf('%s %s von %s',
            $typeLabel, $doc['doc_number'], $business['instances_name'] ?? 'Unser Unternehmen');

        $body = $customBody ?: sprintf(
            "Sehr geehrte Damen und Herren,\n\nanbei erhalten Sie %s Nr. %s.\n\n" .
            "Projekt: %s\nBetrag: %s EUR\n\n" .
            "Bei Fragen stehen wir Ihnen gerne zur Verfuegung.\n\nMit freundlichen Gruessen\n%s",
            $typeLabel, $doc['doc_number'],
            $project['projects_name'] ?? '',
            number_format((float)$doc['gross_amount'], 2, ',', '.'),
            $business['instances_name'] ?? ''
        );

        // Get PDF file
        if (empty($doc['s3files_id'])) {
            throw new \RuntimeException('Keine PDF-Datei vorhanden.');
        }

        // Use the existing InvoiceMailService for actual sending
        $mailSvc = new InvoiceMailService($this->db);
        $result = $mailSvc->sendDocument(
            $instanceId,
            (int)$doc['projects_id'],
            (int)$doc['s3files_id'],
            $doc['doc_number'],
            $doc['doc_type'],
            $email,
            $client['clients_name'] ?? 'Kunde',
            0 // System user for auto-send
        );

        $sent = $result['success'] ?? false;

        // Log the email
        $this->db->insert('document_email_log', [
            'instances_id' => $instanceId,
            'document_lifecycle_id' => $docLifecycleId,
            'recipient_email' => $email,
            'subject' => $subject,
            'sent_at' => date('Y-m-d H:i:s'),
            'status' => $sent ? 'sent' : 'failed',
            'error_message' => $sent ? null : ($result['message'] ?? 'Unbekannter Fehler'),
        ]);

        // Update document status to 'sent' if currently draft and email was sent
        if ($sent && $doc['status'] === 'draft') {
            $lifecycle = new DocumentLifecycleService($this->db);
            $lifecycle->changeStatus(
                $docLifecycleId,
                'sent',
                0, // System user
                'Automatisch per E-Mail versendet an ' . $email,
                $instanceId
            );
        }

        return [
            'sent' => $sent,
            'email' => $email,
            'subject' => $subject,
            'message' => $result['message'] ?? '',
        ];
    }

    /**
     * Get pending auto-send documents
     */
    public function getPendingAutoSend(int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('auto_send_email', 1);
        $this->db->where('status', 'draft');
        $this->db->where('s3files_id IS NOT NULL');
        return $this->db->get('document_lifecycle') ?: [];
    }

    /**
     * Process all pending auto-send documents for an instance
     */
    public function processAutoSend(int $instanceId): array
    {
        $pending = $this->getPendingAutoSend($instanceId);
        $results = ['sent' => 0, 'failed' => 0, 'details' => []];

        foreach ($pending as $doc) {
            try {
                $result = $this->sendDocument($instanceId, (int)$doc['id']);
                if ($result['sent']) {
                    $results['sent']++;
                    // Reset auto_send_email flag after successful send
                    $this->db->where('id', $doc['id']);
                    $this->db->update('document_lifecycle', ['auto_send_email' => 0]);
                } else {
                    $results['failed']++;
                }
                $results['details'][] = [
                    'doc_id' => $doc['id'],
                    'doc_number' => $doc['doc_number'],
                    'sent' => $result['sent'],
                    'email' => $result['email'] ?? null,
                    'message' => $result['message'] ?? '',
                ];
            } catch (\Exception $e) {
                $results['failed']++;
                $results['details'][] = [
                    'doc_id' => $doc['id'],
                    'doc_number' => $doc['doc_number'],
                    'sent' => false,
                    'error' => $e->getMessage(),
                ];

                // Log failure
                $this->db->insert('document_email_log', [
                    'instances_id' => $instanceId,
                    'document_lifecycle_id' => (int)$doc['id'],
                    'recipient_email' => '',
                    'subject' => '',
                    'sent_at' => date('Y-m-d H:i:s'),
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }
}
