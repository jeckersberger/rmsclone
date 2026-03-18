<?php
/**
 * Recurring Invoices Service
 *
 * Automatische Erstellung von Rechnungen basierend auf Vorlagen:
 * - Monatlich, vierteljährlich, jährlich, custom
 * - Automatische Rechnungsnummern-Generierung
 * - E-Mail-Versand optional
 *
 * Tabellen:
 *   recurring_invoice_templates - Vorlagen für wiederkehrende Rechnungen
 */
class RecurringInvoiceService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Create a recurring invoice template
     */
    public function createTemplate(int $instanceId, array $data): ?int
    {
        if (empty($data['clients_id']) || empty($data['title'])) {
            return null;
        }

        // Parse items if provided as array
        $itemsJson = $data['items_json'];
        if (is_array($itemsJson)) {
            $itemsJson = json_encode($itemsJson);
        }

        // Calculate first next_due_date if not provided
        $nextDueDate = $data['next_due_date'] ?? $this->calculateNextDueDate(
            date('Y-m-d'),
            $data['interval_type'] ?? 'monthly',
            (int)($data['interval_days'] ?? 0)
        );

        $templateId = $this->db->insert('recurring_invoice_templates', [
            'instances_id' => $instanceId,
            'clients_id' => (int)$data['clients_id'],
            'project_template_id' => (int)($data['project_template_id'] ?? 0) ?: null,
            'title' => trim($data['title']),
            'description' => trim($data['description'] ?? ''),
            'items_json' => $itemsJson,
            'interval_type' => $data['interval_type'] ?? 'monthly',
            'interval_days' => (int)($data['interval_days'] ?? 0),
            'next_due_date' => $nextDueDate,
            'last_generated_date' => null,
            'payment_terms_days' => (int)($data['payment_terms_days'] ?? 14),
            'auto_send_email' => (int)($data['auto_send_email'] ?? 0),
            'is_active' => 1,
            'created_by' => (int)($data['created_by'] ?? 0),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $templateId ?: null;
    }

    /**
     * Update a recurring invoice template
     */
    public function updateTemplate(int $templateId, array $data): bool
    {
        $this->db->where('id', $templateId);
        $template = $this->db->getOne('recurring_invoice_templates', null);
        if (!$template) return false;

        $updateData = [];

        if (isset($data['title'])) {
            $updateData['title'] = trim($data['title']);
        }
        if (isset($data['description'])) {
            $updateData['description'] = trim($data['description']);
        }
        if (isset($data['items_json'])) {
            $itemsJson = $data['items_json'];
            if (is_array($itemsJson)) {
                $itemsJson = json_encode($itemsJson);
            }
            $updateData['items_json'] = $itemsJson;
        }
        if (isset($data['interval_type'])) {
            $updateData['interval_type'] = $data['interval_type'];
        }
        if (isset($data['interval_days'])) {
            $updateData['interval_days'] = (int)$data['interval_days'];
        }
        if (isset($data['payment_terms_days'])) {
            $updateData['payment_terms_days'] = (int)$data['payment_terms_days'];
        }
        if (isset($data['auto_send_email'])) {
            $updateData['auto_send_email'] = (int)$data['auto_send_email'];
        }
        if (isset($data['next_due_date'])) {
            $updateData['next_due_date'] = $data['next_due_date'];
        }

        if (empty($updateData)) return true;

        $updateData['updated_at'] = date('Y-m-d H:i:s');

        $this->db->where('id', $templateId);
        return (bool)$this->db->update('recurring_invoice_templates', $updateData);
    }

    /**
     * Soft delete a recurring invoice template
     */
    public function deleteTemplate(int $templateId): bool
    {
        $this->db->where('id', $templateId);
        return (bool)$this->db->update('recurring_invoice_templates', [
            'is_active' => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get all active templates for an instance
     */
    public function getTemplates(int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('is_active', 1);
        $this->db->join('clients', 'recurring_invoice_templates.clients_id = clients.clients_id', 'LEFT');
        $this->db->orderBy('recurring_invoice_templates.title', 'ASC');
        return $this->db->get('recurring_invoice_templates', null, [
            'recurring_invoice_templates.*',
            'clients.clients_name',
            'clients.clients_email',
        ]) ?: [];
    }

    /**
     * Get a single template by ID
     */
    public function getTemplate(int $templateId): ?array
    {
        $this->db->where('id', $templateId);
        $this->db->join('clients', 'recurring_invoice_templates.clients_id = clients.clients_id', 'LEFT');
        $template = $this->db->getOne('recurring_invoice_templates', null, [
            'recurring_invoice_templates.*',
            'clients.clients_name',
            'clients.clients_email',
        ]);

        if ($template && is_string($template['items_json'])) {
            $template['items'] = json_decode($template['items_json'], true) ?: [];
        }

        return $template ?: null;
    }

    /**
     * Check and generate all due invoices
     * Returns array of generated invoices with details
     */
    public function generateDueInvoices(int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('is_active', 1);
        $this->db->where('next_due_date', date('Y-m-d'), '<=');
        $templates = $this->db->get('recurring_invoice_templates') ?: [];

        $generated = [];
        foreach ($templates as $template) {
            $invoice = $this->generateFromTemplate($template['id']);
            if ($invoice) {
                $generated[] = $invoice;
            }
        }

        return $generated;
    }

    /**
     * Generate a single invoice from template
     * Creates a document_exports entry with all required fields
     */
    public function generateFromTemplate(int $templateId): ?array
    {
        $template = $this->getTemplate($templateId);
        if (!$template) return null;

        // Calculate totals from items
        $items = $template['items'] ?? json_decode($template['items_json'] ?? '[]', true);
        $totals = $this->calculateItemsTotals($items);

        // Generate invoice number
        $invoiceNumber = $this->generateInvoiceNumber($template['instances_id']);

        // Generate due date based on payment terms
        $dueDate = date('Y-m-d', strtotime('+ ' . $template['payment_terms_days'] . ' days'));

        // Create document_exports entry
        $docExportId = $this->db->insert('document_exports', [
            'instances_id' => $template['instances_id'],
            'projects_id' => $template['project_template_id'] ?: null,
            'clients_id' => $template['clients_id'],
            'document_exports_type' => 'invoice',
            'document_exports_number' => $invoiceNumber,
            'document_exports_date' => date('Y-m-d'),
            'document_exports_due_date' => $dueDate,
            'document_exports_net' => (int)$totals['net'],
            'document_exports_gross' => (int)$totals['gross'],
            'document_exports_tax' => (int)$totals['tax'],
            'document_exports_description' => $template['description'],
            'document_exports_items_json' => json_encode($items),
            'document_exports_deleted' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        if (!$docExportId) return null;

        // Update template with generation dates
        $nextDueDate = $this->calculateNextDueDate(
            date('Y-m-d'),
            $template['interval_type'],
            (int)$template['interval_days']
        );

        $this->db->where('id', $templateId);
        $this->db->update('recurring_invoice_templates', [
            'last_generated_date' => date('Y-m-d H:i:s'),
            'next_due_date' => $nextDueDate,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return [
            'document_exports_id' => $docExportId,
            'document_exports_number' => $invoiceNumber,
            'document_exports_date' => date('Y-m-d'),
            'document_exports_gross' => $totals['gross'],
            'clients_name' => $template['clients_name'],
            'template_id' => $templateId,
            'template_title' => $template['title'],
        ];
    }

    /**
     * Preview invoices that would be generated in the next 30 days
     */
    public function previewNextInvoices(int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('is_active', 1);
        $futureDate = date('Y-m-d', strtotime('+30 days'));
        $this->db->where('next_due_date', [$futureDate, date('Y-m-d')], 'BETWEEN');
        $templates = $this->db->get('recurring_invoice_templates') ?: [];

        $preview = [];
        foreach ($templates as $template) {
            $items = json_decode($template['items_json'] ?? '[]', true);
            $totals = $this->calculateItemsTotals($items);

            $preview[] = [
                'template_id' => $template['id'],
                'template_title' => $template['title'],
                'clients_name' => $template['clients_name'] ?? '',
                'clients_id' => $template['clients_id'],
                'next_due_date' => $template['next_due_date'],
                'net_amount' => $totals['net'],
                'gross_amount' => $totals['gross'],
                'tax_amount' => $totals['tax'],
                'item_count' => count($items),
            ];
        }

        return $preview;
    }

    /**
     * Calculate next due date based on interval
     */
    public function calculateNextDueDate(string $currentDate, string $intervalType, int $intervalDays = 0): string
    {
        $date = new DateTime($currentDate);

        switch ($intervalType) {
            case 'weekly':
                $date->modify('+1 week');
                break;
            case 'biweekly':
                $date->modify('+2 weeks');
                break;
            case 'quarterly':
                $date->modify('+3 months');
                break;
            case 'yearly':
                $date->modify('+1 year');
                break;
            case 'custom':
                if ($intervalDays > 0) {
                    $date->modify("+{$intervalDays} days");
                }
                break;
            case 'monthly':
            default:
                $date->modify('+1 month');
                break;
        }

        return $date->format('Y-m-d');
    }

    /**
     * Calculate totals from line items
     */
    private function calculateItemsTotals(array $items): array
    {
        $netTotal = 0;
        $taxTotal = 0;

        foreach ($items as $item) {
            $quantity = (float)($item['quantity'] ?? 1);
            $unitPrice = (int)($item['unit_price'] ?? 0);
            $taxRate = (float)($item['tax_rate'] ?? 0);

            $lineNet = $quantity * $unitPrice;
            $lineTax = (int)($lineNet * ($taxRate / 100));

            $netTotal += (int)$lineNet;
            $taxTotal += $lineTax;
        }

        return [
            'net' => $netTotal,
            'tax' => $taxTotal,
            'gross' => $netTotal + $taxTotal,
        ];
    }

    /**
     * Generate the next invoice number following system pattern
     * Typically: YYYY-MM-XXXXX or similar
     */
    private function generateInvoiceNumber(int $instanceId): string
    {
        // Get the highest existing invoice number for this instance/month
        $year = date('Y');
        $month = date('m');
        $prefix = "{$year}-{$month}-";

        $this->db->where('instances_id', $instanceId);
        $this->db->where('document_exports_number', "{$prefix}%", 'LIKE');
        $this->db->where('document_exports_type', 'invoice');
        $this->db->orderBy('document_exports_number', 'DESC');
        $lastDoc = $this->db->getOne('document_exports', null, ['document_exports_number']);

        if ($lastDoc && preg_match('/\d+-\d+-(\d+)$/', $lastDoc['document_exports_number'], $matches)) {
            $nextNum = (int)$matches[1] + 1;
        } else {
            $nextNum = 1;
        }

        return $prefix . str_pad($nextNum, 5, '0', STR_PAD_LEFT);
    }
}
