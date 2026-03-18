<?php
/**
 * ContractService - Digitales Vertragsmanagement (J1)
 *
 * Manages contract lifecycle: creation, versioning, signing, expiration
 * Supports placeholder replacement, PDF generation, and AGB management
 */

require_once __DIR__ . '/DocumentRenderer.php';

class ContractService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get contracts by instance, optionally filtered by status or client
     */
    public function getContracts(int $instanceId, ?string $status = null, ?int $clientId = null): array
    {
        $this->db->where('instances_id', $instanceId);

        if ($status !== null) {
            $this->db->where('status', $status);
        }

        if ($clientId !== null) {
            $this->db->where('clients_id', $clientId);
        }

        $this->db->orderBy('created_at', 'DESC');
        return $this->db->get('contracts') ?: [];
    }

    /**
     * Get single contract with versions and audit log
     */
    public function getContract(int $id): ?array
    {
        $this->db->where('id', $id);
        $contract = $this->db->getOne('contracts');

        if (!$contract) {
            return null;
        }

        // Get versions
        $this->db->where('contracts_id', $id);
        $this->db->orderBy('version', 'DESC');
        $contract['versions'] = $this->db->get('contract_versions') ?: [];

        // Get audit log
        $this->db->where('contracts_id', $id);
        $this->db->orderBy('created_at', 'DESC');
        $contract['audit_log'] = $this->db->get('contract_audit_log') ?: [];

        return $contract;
    }

    /**
     * Create new contract
     */
    public function createContract(array $data): int
    {
        $this->db->insert('contracts', [
            'instances_id' => $data['instances_id'],
            'projects_id' => $data['projects_id'] ?? null,
            'clients_id' => $data['clients_id'],
            'template_id' => $data['template_id'] ?? null,
            'title' => $data['title'],
            'content_html' => $data['content_html'],
            'status' => $data['status'] ?? 'draft',
            'version' => 1,
            'valid_from' => $data['valid_from'] ?? null,
            'valid_until' => $data['valid_until'] ?? null,
            'created_by' => $data['created_by'],
        ]);

        $contractId = $this->db->getInsertId();

        // Log creation
        $this->logAction($contractId, 'created', $data['created_by']);

        return $contractId;
    }

    /**
     * Update contract (creates new version)
     */
    public function updateContract(int $id, array $data, int $userId): bool
    {
        $this->db->where('id', $id);
        $contract = $this->db->getOne('contracts');

        if (!$contract) {
            return false;
        }

        $newVersion = (int)$contract['version'] + 1;

        // Store old version
        $this->db->insert('contract_versions', [
            'contracts_id' => $id,
            'version' => $contract['version'],
            'content_html' => $contract['content_html'],
            'changed_by' => $userId,
            'change_notes' => $data['change_notes'] ?? null,
        ]);

        // Update contract
        $updateData = [
            'version' => $newVersion,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (isset($data['title'])) $updateData['title'] = $data['title'];
        if (isset($data['content_html'])) $updateData['content_html'] = $data['content_html'];
        if (isset($data['status'])) $updateData['status'] = $data['status'];
        if (isset($data['valid_from'])) $updateData['valid_from'] = $data['valid_from'];
        if (isset($data['valid_until'])) $updateData['valid_until'] = $data['valid_until'];

        $this->db->where('id', $id);
        $this->db->update('contracts', $updateData);

        // Log edit
        $this->logAction($id, 'edited', $userId, ['version' => $newVersion]);

        return true;
    }

    /**
     * Delete contract (soft delete via status)
     */
    public function deleteContract(int $id): bool
    {
        $this->db->where('id', $id);
        $this->db->update('contracts', ['status' => 'cancelled']);
        return true;
    }

    /**
     * Generate contract from project + template
     */
    public function generateFromProject(int $projectId, int $templateId, int $instanceId, int $userId): int
    {
        // Get project
        $this->db->where('id', $projectId);
        $this->db->where('instances_id', $instanceId);
        $project = $this->db->getOne('projects');

        if (!$project) {
            throw new \RuntimeException('Project not found');
        }

        // Get template
        $this->db->where('id', $templateId);
        $this->db->where('instances_id', $instanceId);
        $template = $this->db->getOne('contract_templates');

        if (!$template) {
            throw new \RuntimeException('Template not found');
        }

        // Get client
        $this->db->where('id', $project['clients_id']);
        $client = $this->db->getOne('clients');

        // Get business settings
        $this->db->where('instances_id', $instanceId);
        $business = $this->db->getOne('instances');

        // Build placeholder data
        $placeholderData = [
            'kunde.name' => $client['clients_name'] ?? '',
            'kunde.firma' => $client['clients_firma'] ?? '',
            'kunde.adresse' => $client['clients_adresse'] ?? '',
            'kunde.email' => $client['clients_email'] ?? '',
            'projekt.name' => $project['projects_name'] ?? '',
            'projekt.startdatum' => date('d.m.Y', strtotime($project['projects_dateStart'] ?? 'now')),
            'projekt.enddatum' => date('d.m.Y', strtotime($project['projects_dateEnd'] ?? 'now')),
            'equipment.liste' => $this->getEquipmentList($projectId),
            'equipment.gesamtpreis' => $this->getEquipmentTotal($projectId),
            'datum.heute' => date('d.m.Y'),
            'firma.name' => $business['instances_name'] ?? '',
            'firma.adresse' => $business['instances_adresse'] ?? '',
        ];

        // Replace placeholders
        $content = $this->replacePlaceholders($template['content_html'], $placeholderData);

        // Create contract
        return $this->createContract([
            'instances_id' => $instanceId,
            'projects_id' => $projectId,
            'clients_id' => $project['clients_id'],
            'template_id' => $templateId,
            'title' => str_replace(['{projekt.name}', '{{projekt.name}}'], $placeholderData['projekt.name'], $template['name']),
            'content_html' => $content,
            'status' => 'draft',
            'valid_from' => date('Y-m-d'),
            'valid_until' => date('Y-m-d', strtotime('+1 year')),
            'created_by' => $userId,
        ]);
    }

    /**
     * Get all templates for instance
     */
    public function getTemplates(int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('is_active', 1);
        $this->db->orderBy('sort_order', 'ASC');
        $this->db->orderBy('name', 'ASC');
        return $this->db->get('contract_templates') ?: [];
    }

    /**
     * Create template
     */
    public function createTemplate(array $data): int
    {
        // Extract placeholders from content
        $placeholders = [];
        preg_match_all('/\{([^}]+)\}/', $data['content_html'], $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $ph) {
                $placeholders[$ph] = '';
            }
        }

        $this->db->insert('contract_templates', [
            'instances_id' => $data['instances_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? 'general',
            'content_html' => $data['content_html'],
            'placeholders' => json_encode($placeholders),
            'agb_set_id' => $data['agb_set_id'] ?? null,
            'is_active' => 1,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return $this->db->getInsertId();
    }

    /**
     * Update template
     */
    public function updateTemplate(int $id, array $data): bool
    {
        $updateData = [];

        if (isset($data['name'])) $updateData['name'] = $data['name'];
        if (isset($data['description'])) $updateData['description'] = $data['description'];
        if (isset($data['category'])) $updateData['category'] = $data['category'];
        if (isset($data['content_html'])) {
            $updateData['content_html'] = $data['content_html'];
            // Extract placeholders
            $placeholders = [];
            preg_match_all('/\{([^}]+)\}/', $data['content_html'], $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $ph) {
                    $placeholders[$ph] = '';
                }
            }
            $updateData['placeholders'] = json_encode($placeholders);
        }
        if (isset($data['agb_set_id'])) $updateData['agb_set_id'] = $data['agb_set_id'];
        if (isset($data['sort_order'])) $updateData['sort_order'] = $data['sort_order'];

        $this->db->where('id', $id);
        return (bool)$this->db->update('contract_templates', $updateData);
    }

    /**
     * Get AGB sets for instance
     */
    public function getAgbSets(int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('is_active', 1);
        $this->db->orderBy('is_default', 'DESC');
        $this->db->orderBy('created_at', 'DESC');
        return $this->db->get('contract_agb_sets') ?: [];
    }

    /**
     * Create AGB set
     */
    public function createAgbSet(array $data): int
    {
        $this->db->insert('contract_agb_sets', [
            'instances_id' => $data['instances_id'],
            'name' => $data['name'],
            'content_html' => $data['content_html'],
            'version' => 1,
            'is_active' => 1,
            'is_default' => 0,
        ]);

        return $this->db->getInsertId();
    }

    /**
     * Set default AGB set
     */
    public function setDefaultAgb(int $id, int $instanceId): bool
    {
        // Clear other defaults
        $this->db->where('instances_id', $instanceId);
        $this->db->update('contract_agb_sets', ['is_default' => 0]);

        // Set new default
        $this->db->where('id', $id);
        $this->db->where('instances_id', $instanceId);
        return (bool)$this->db->update('contract_agb_sets', ['is_default' => 1]);
    }

    /**
     * Send contract for signing (via email)
     */
    public function sendContract(int $contractId, string $recipientEmail, int $userId): bool
    {
        $this->db->where('id', $contractId);
        $contract = $this->db->getOne('contracts');

        if (!$contract) {
            return false;
        }

        // Generate signing token
        $token = bin2hex(random_bytes(16));

        // Store token (temporary, can be in cache or separate table)
        // For now: encode in signing URL
        $signingUrl = BASE_URL . '/contracts/sign.php?token=' . $token . '&contract=' . $contractId;

        // Send email
        $subject = 'Vertrag zur Unterzeichnung: ' . $contract['title'];
        $body = "Sehr geehrte/r,\n\nbitte unterzeichnen Sie den folgenden Vertrag:\n\n";
        $body .= $contract['title'] . "\n\n";
        $body .= "Link zur Unterzeichnung: " . $signingUrl . "\n\n";
        $body .= "Der Link ist 30 Tage gültig.\n\n";
        $body .= "Mit freundlichen Grüßen";

        // TODO: Integrate with email sending service
        // For now, just log the action
        $this->logAction($contractId, 'sent', $userId, ['recipient' => $recipientEmail, 'token' => $token]);

        // Update status
        $this->db->where('id', $contractId);
        $this->db->update('contracts', ['status' => 'sent']);

        return true;
    }

    /**
     * Mark contract as viewed
     */
    public function markViewed(int $contractId, string $ip, string $userAgent): bool
    {
        $this->db->where('id', $contractId);
        $contract = $this->db->getOne('contracts');

        if (!$contract) {
            return false;
        }

        if ($contract['status'] === 'sent') {
            $this->db->where('id', $contractId);
            $this->db->update('contracts', ['status' => 'viewed']);
        }

        $this->logAction($contractId, 'viewed', null, ['ip' => $ip, 'user_agent' => $userAgent]);

        return true;
    }

    /**
     * Record signature on contract
     */
    public function signContract(int $contractId, string $signerName, string $signatureData, string $ip): bool
    {
        $this->db->where('id', $contractId);
        $contract = $this->db->getOne('contracts');

        if (!$contract) {
            return false;
        }

        $this->db->where('id', $contractId);
        $this->db->update('contracts', [
            'status' => 'signed',
            'signed_at' => date('Y-m-d H:i:s'),
            'signed_by_name' => $signerName,
            'signed_by_ip' => $ip,
            'signature_data' => $signatureData,
            'signature_method' => 'canvas',
        ]);

        $this->logAction($contractId, 'signed', null, ['signer' => $signerName, 'ip' => $ip]);

        return true;
    }

    /**
     * Render contract (replace placeholders with actual data)
     */
    public function renderContract(int $contractId): string
    {
        $this->db->where('id', $contractId);
        $contract = $this->db->getOne('contracts');

        if (!$contract) {
            return '';
        }

        // Get client and project data for placeholders
        $this->db->where('id', $contract['clients_id']);
        $client = $this->db->getOne('clients');

        $projectData = [];
        if ($contract['projects_id']) {
            $this->db->where('id', $contract['projects_id']);
            $projectData = $this->db->getOne('projects') ?: [];
        }

        // Get business settings
        $this->db->where('instances_id', $contract['instances_id']);
        $business = $this->db->getOne('instances');

        // Build placeholder data
        $data = [
            'kunde.name' => $client['clients_name'] ?? '',
            'kunde.firma' => $client['clients_firma'] ?? '',
            'kunde.adresse' => $client['clients_adresse'] ?? '',
            'kunde.email' => $client['clients_email'] ?? '',
            'projekt.name' => $projectData['projects_name'] ?? '',
            'projekt.startdatum' => $projectData ? date('d.m.Y', strtotime($projectData['projects_dateStart'] ?? 'now')) : '',
            'projekt.enddatum' => $projectData ? date('d.m.Y', strtotime($projectData['projects_dateEnd'] ?? 'now')) : '',
            'equipment.liste' => $projectData ? $this->getEquipmentList($projectData['id']) : '',
            'equipment.gesamtpreis' => $projectData ? $this->getEquipmentTotal($projectData['id']) : '',
            'datum.heute' => date('d.m.Y'),
            'firma.name' => $business['instances_name'] ?? '',
            'firma.adresse' => $business['instances_adresse'] ?? '',
        ];

        return $this->replacePlaceholders($contract['content_html'], $data);
    }

    /**
     * Replace placeholders in HTML
     */
    public function replacePlaceholders(string $html, array $data): string
    {
        foreach ($data as $placeholder => $value) {
            $patterns = [
                '{' . $placeholder . '}',
                '{{' . $placeholder . '}}',
            ];
            foreach ($patterns as $pattern) {
                $html = str_replace($pattern, (string)$value, $html);
            }
        }
        return $html;
    }

    /**
     * Generate PDF from contract
     */
    public function getContractPdf(int $contractId): string
    {
        require_once __DIR__ . '/../services/DocumentRenderer.php';

        $html = $this->renderContract($contractId);

        $dompdf = new \Dompdf\Dompdf(new \Dompdf\Options(['isRemoteEnabled' => true]));
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Get expiring contracts
     */
    public function getExpiringContracts(int $instanceId, int $daysAhead = 30): array
    {
        $futureDate = date('Y-m-d', strtotime("+{$daysAhead} days"));

        $this->db->where('instances_id', $instanceId);
        $this->db->where('valid_until', $futureDate, '<=');
        $this->db->where('valid_until', date('Y-m-d'), '>=');
        $this->db->where('status', ['signed', 'active'], 'IN');
        $this->db->orderBy('valid_until', 'ASC');

        return $this->db->get('contracts') ?: [];
    }

    /**
     * Get audit log for contract
     */
    public function getAuditLog(int $contractId): array
    {
        $this->db->where('contracts_id', $contractId);
        $this->db->orderBy('created_at', 'DESC');
        return $this->db->get('contract_audit_log') ?: [];
    }

    /**
     * Get available placeholders with descriptions
     */
    public function getAvailablePlaceholders(): array
    {
        return [
            'kunde.name' => 'Name des Kunden',
            'kunde.firma' => 'Firmenname des Kunden',
            'kunde.adresse' => 'Adresse des Kunden',
            'kunde.email' => 'E-Mail des Kunden',
            'projekt.name' => 'Projektname',
            'projekt.startdatum' => 'Projekt-Startdatum (d.m.Y)',
            'projekt.enddatum' => 'Projekt-Enddatum (d.m.Y)',
            'equipment.liste' => 'Liste der Ausrüstungen',
            'equipment.gesamtpreis' => 'Gesamtpreis der Ausrüstung',
            'datum.heute' => 'Heutiges Datum (d.m.Y)',
            'firma.name' => 'Name des Unternehmens',
            'firma.adresse' => 'Adresse des Unternehmens',
        ];
    }

    // ─────────────────────────────────────────────────────────
    // Helper Methods
    // ─────────────────────────────────────────────────────────

    private function getEquipmentList(int $projectId): string
    {
        $this->db->where('projects_id', $projectId);
        $assets = $this->db->get('assets') ?: [];

        $list = [];
        foreach ($assets as $asset) {
            $qty = isset($asset['qty']) ? (int)$asset['qty'] : 1;
            $list[] = $qty . ' x ' . ($asset['name'] ?? 'Unbekannt');
        }

        return implode(', ', $list) ?: 'Keine';
    }

    private function getEquipmentTotal(int $projectId): string
    {
        $this->db->where('projects_id', $projectId);
        $assets = $this->db->get('assets') ?: [];

        $total = 0.0;
        foreach ($assets as $asset) {
            $total += (float)($asset['total_net'] ?? 0);
        }

        return number_format($total, 2, ',', '.') . ' EUR';
    }

    private function logAction(int $contractId, string $action, ?int $userId = null, ?array $details = null): void
    {
        $this->db->insert('contract_audit_log', [
            'contracts_id' => $contractId,
            'action' => $action,
            'users_id' => $userId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'details' => $details ? json_encode($details) : null,
        ]);
    }
}
