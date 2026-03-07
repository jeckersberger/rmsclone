<?php
/**
 * Angebot -> Auftragsbestaetigung -> Rechnung -> Gutschrift Workflow
 *
 * Manages the full document lifecycle chain:
 * quote (Angebot) -> order_confirmation (Auftragsbestaetigung) -> invoice (Rechnung)
 * Optional: credit_note (Gutschrift), cancellation (Storno)
 */
class DocumentLifecycleService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Create a new document in the lifecycle
     */
    public function create(int $instanceId, int $projectId, string $docType, array $data): int
    {
        // Default valid_until to +30 days for quotes if not provided
        $validUntil = $data['valid_until'] ?? null;
        if ($docType === 'quote' && $validUntil === null) {
            // Check instance setting for default days, fallback to 30
            $this->db->where('instances_id', $instanceId);
            $inst = $this->db->getOne('instances', ['valid_until_default_days']);
            $defaultDays = (int)($inst['valid_until_default_days'] ?? 30) ?: 30;
            $validUntil = date('Y-m-d', strtotime("+{$defaultDays} days"));
        }

        $this->db->insert('document_lifecycle', [
            'instances_id'       => $instanceId,
            'projects_id'        => $projectId,
            'doc_type'           => $docType,
            'doc_number'         => $data['doc_number'] ?? '',
            'status'             => 'draft',
            'parent_doc_id'      => $data['parent_doc_id'] ?? null,
            's3files_id'         => $data['s3files_id'] ?? null,
            'document_exports_id'=> $data['document_exports_id'] ?? null,
            'net_amount'         => $data['net_amount'] ?? 0,
            'gross_amount'       => $data['gross_amount'] ?? 0,
            'currency'           => $data['currency'] ?? 'EUR',
            'valid_until'        => $validUntil,
            'due_date'           => $data['due_date'] ?? null,
            'notes'              => $data['notes'] ?? null,
            'created_by'         => $data['created_by'],
        ]);

        $docId = $this->db->getInsertId();

        $this->logStatusChange($docId, null, 'draft', $data['created_by'], 'Dokument erstellt');

        return $docId;
    }

    /**
     * Change document status with validation
     */
    public function changeStatus(int $docId, string $newStatus, int $userId, ?string $comment = null, ?int $instanceId = null): bool
    {
        $this->db->where('id', $docId);
        if ($instanceId !== null) {
            $this->db->where('instances_id', $instanceId);
        }
        $doc = $this->db->getOne('document_lifecycle');
        if (!$doc) return false;

        $allowed = $this->getAllowedTransitions($doc['doc_type'], $doc['status']);
        if (!in_array($newStatus, $allowed)) return false;

        $oldStatus = $doc['status'];

        $updateData = ['status' => $newStatus];
        if ($newStatus === 'sent') {
            $updateData['sent_at'] = date('Y-m-d H:i:s');
        }
        if ($newStatus === 'paid') {
            $updateData['paid_date'] = date('Y-m-d');
        }

        $this->db->where('id', $docId);
        $this->db->update('document_lifecycle', $updateData);

        $this->logStatusChange($docId, $oldStatus, $newStatus, $userId, $comment);

        return true;
    }

    /**
     * Convert a quote into an order confirmation or invoice
     */
    public function convertDocument(int $sourceDocId, string $targetType, int $userId, array $extraOpts = [], ?int $instanceId = null): ?int
    {
        $this->db->where('id', $sourceDocId);
        if ($instanceId !== null) {
            $this->db->where('instances_id', $instanceId);
        }
        $sourceDoc = $this->db->getOne('document_lifecycle');
        if (!$sourceDoc) return null;

        // Validate conversion path
        $validConversions = [
            'quote' => ['order_confirmation', 'invoice'],
            'order_confirmation' => ['invoice'],
            'invoice' => ['credit_note', 'cancellation'],
        ];

        if (!isset($validConversions[$sourceDoc['doc_type']]) ||
            !in_array($targetType, $validConversions[$sourceDoc['doc_type']])) {
            return null;
        }

        // Mark source as accepted if it's a quote
        if ($sourceDoc['doc_type'] === 'quote' && $sourceDoc['status'] !== 'accepted') {
            $this->changeStatus($sourceDocId, 'accepted', $userId, 'Automatisch akzeptiert bei Umwandlung');
        }

        // Generate document via DocumentRenderer
        $typeForRenderer = $targetType === 'order_confirmation' ? 'invoice' : $targetType;
        if ($targetType === 'credit_note') $typeForRenderer = 'invoice';

        $renderOpts = array_merge(['discount_pct' => 0], $extraOpts);
        $result = DocumentRenderer::renderAndStore(
            $this->db,
            (int)$sourceDoc['instances_id'],
            (int)$sourceDoc['projects_id'],
            $typeForRenderer,
            'default',
            $renderOpts,
            $userId
        );

        // Create lifecycle entry for the new document
        $newDocId = $this->create(
            (int)$sourceDoc['instances_id'],
            (int)$sourceDoc['projects_id'],
            $targetType,
            [
                'doc_number'    => $result['doc_number'],
                's3files_id'    => $result['s3files_id'],
                'parent_doc_id' => $sourceDocId,
                'net_amount'    => $sourceDoc['net_amount'],
                'gross_amount'  => $sourceDoc['gross_amount'],
                'currency'      => $sourceDoc['currency'],
                'due_date'      => $targetType === 'invoice' ? date('Y-m-d', strtotime('+14 days')) : null,
                'created_by'    => $userId,
            ]
        );

        return $newDocId;
    }

    /**
     * Mark invoice as paid
     */
    public function markPaid(int $docId, float $amount, int $userId, ?string $reference = null): bool
    {
        $this->db->where('id', $docId);
        $doc = $this->db->getOne('document_lifecycle');
        if (!$doc || $doc['doc_type'] !== 'invoice') return false;

        $this->db->where('id', $docId);
        $this->db->update('document_lifecycle', [
            'status' => 'paid',
            'paid_date' => date('Y-m-d'),
            'paid_amount' => $amount,
        ]);

        $this->logStatusChange($docId, $doc['status'], 'paid', $userId,
            $reference ? "Zahlung: $reference" : 'Als bezahlt markiert');

        return true;
    }

    /**
     * Get full document chain (quote -> order -> invoice -> credit notes)
     */
    public function getDocumentChain(int $docId): array
    {
        $chain = [];

        // Find the root document
        $this->db->where('id', $docId);
        $doc = $this->db->getOne('document_lifecycle');
        if (!$doc) return $chain;

        // Walk up to root
        while ($doc['parent_doc_id']) {
            $this->db->where('id', $doc['parent_doc_id']);
            $doc = $this->db->getOne('document_lifecycle');
            if (!$doc) break;
        }

        // Walk down from root
        $rootId = $doc['id'];
        $chain[] = $doc;
        $this->addChildren($rootId, $chain);

        return $chain;
    }

    private function addChildren(int $parentId, array &$chain): void
    {
        $this->db->where('parent_doc_id', $parentId);
        $this->db->orderBy('created_at', 'ASC');
        $children = $this->db->get('document_lifecycle');
        foreach ($children as $child) {
            $chain[] = $child;
            $this->addChildren($child['id'], $chain);
        }
    }

    /**
     * Get all documents for a project
     */
    public function getProjectDocuments(int $instanceId, int $projectId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('projects_id', $projectId);
        $this->db->orderBy('created_at', 'DESC');
        return $this->db->get('document_lifecycle') ?: [];
    }

    /**
     * Get overdue invoices for dunning
     */
    public function getOverdueInvoices(int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('doc_type', 'invoice');
        $this->db->where('status', ['sent', 'overdue', 'reminded'], 'IN');
        $this->db->where('due_date', date('Y-m-d'), '<');
        $this->db->orderBy('due_date', 'ASC');
        return $this->db->get('document_lifecycle') ?: [];
    }

    /**
     * Get status history for a document
     */
    public function getStatusHistory(int $docId): array
    {
        $this->db->where('document_lifecycle_id', $docId);
        $this->db->orderBy('created_at', 'ASC');
        return $this->db->get('document_status_history') ?: [];
    }

    /**
     * Get allowed status transitions
     */
    private function getAllowedTransitions(string $docType, string $currentStatus): array
    {
        $transitions = [
            'quote' => [
                'draft'    => ['sent'],
                'sent'     => ['accepted', 'rejected', 'cancelled', 'expired'],
                'accepted' => [],
                'rejected' => [],
                'cancelled' => [],
                'expired'  => [],
            ],
            'order_confirmation' => [
                'draft' => ['sent'],
                'sent'  => ['cancelled'],
            ],
            'invoice' => [
                'draft'    => ['sent'],
                'sent'     => ['paid', 'overdue', 'reminded', 'cancelled'],
                'overdue'  => ['paid', 'reminded', 'cancelled'],
                'reminded' => ['paid', 'overdue', 'cancelled'],
                'paid'     => [],
                'cancelled' => [],
            ],
            'credit_note' => [
                'draft' => ['sent'],
                'sent'  => [],
            ],
            'cancellation' => [
                'draft' => ['sent'],
                'sent'  => [],
            ],
            'partial_invoice' => [
                'draft'    => ['sent'],
                'sent'     => ['paid', 'overdue', 'reminded', 'cancelled'],
                'overdue'  => ['paid', 'reminded', 'cancelled'],
                'reminded' => ['paid', 'overdue', 'cancelled'],
                'paid'     => [],
                'cancelled' => [],
            ],
        ];

        return $transitions[$docType][$currentStatus] ?? [];
    }

    // ═══════════════════════════════════════════════
    //  ABSCHLAGSRECHNUNGEN (Teilrechnungen)
    // ═══════════════════════════════════════════════

    /**
     * Abschlagsrechnung erstellen
     *
     * @param int    $instanceId
     * @param int    $projectId
     * @param float  $percentage   Prozentsatz der Gesamtsumme (z.B. 30.0)
     * @param int    $userId
     * @param array  $opts         Zusaetzliche Optionen (skonto_enabled, etc.)
     * @return array ['doc_id' => int, 'doc_number' => string, ...]
     */
    public function createPartialInvoice(int $instanceId, int $projectId, float $percentage, int $userId, array $opts = []): array
    {
        if ($percentage <= 0 || $percentage > 100) {
            throw new \InvalidArgumentException("Prozentsatz muss zwischen 0 und 100 liegen.");
        }

        // Bisherige Abschlagsrechnungen fuer dieses Projekt pruefen
        $this->db->where('instances_id', $instanceId);
        $this->db->where('projects_id', $projectId);
        $this->db->where('doc_type', 'partial_invoice');
        $this->db->where('status', 'cancelled', '!=');
        $this->db->orderBy('partial_invoice_number', 'DESC');
        $existingPartials = $this->db->get('document_lifecycle') ?: [];

        // Pruefen ob bereits eine Schlussrechnung existiert
        $this->db->where('instances_id', $instanceId);
        $this->db->where('projects_id', $projectId);
        $this->db->where('doc_type', 'invoice');
        $this->db->where('is_final_invoice', 1);
        $this->db->where('status', 'cancelled', '!=');
        if ($this->db->getOne('document_lifecycle')) {
            throw new \RuntimeException("Es existiert bereits eine Schlussrechnung fuer dieses Projekt.");
        }

        // Gesamtprozentsatz pruefen
        $totalPct = 0;
        foreach ($existingPartials as $p) {
            $totalPct += (float)$p['partial_invoice_pct'];
        }

        if ($totalPct + $percentage > 100) {
            $remaining = round(100 - $totalPct, 2);
            throw new \RuntimeException(
                "Gesamtprozentsatz wuerde 100% ueberschreiten. "
                . "Bereits abgerechnet: {$totalPct}%. Verbleibend: {$remaining}%."
            );
        }

        $partialNumber = count($existingPartials) + 1;
        $totalPlanned = $opts['total_planned'] ?? null;

        // Dokument rendern
        $renderOpts = array_merge($opts, [
            'partial_invoice'     => true,
            'partial_pct'         => $percentage,
            'partial_number'      => $partialNumber,
            'partial_total'       => $totalPlanned,
            'force_regenerate'    => true,
        ]);

        $result = DocumentRenderer::renderAndStore(
            $this->db, $instanceId, $projectId, 'invoice', 'default', $renderOpts, $userId
        );

        // Projekt-Gesamtbetrag ermitteln
        $this->db->where('doc_number', $result['doc_number']);
        $this->db->where('instances_id', $instanceId);
        $export = $this->db->getOne('document_exports', ['totals_json']);
        $totals = $export ? json_decode($export['totals_json'], true) : [];
        $fullGross = (float)($totals['gross'] ?? 0);
        $fullNet = (float)($totals['net'] ?? 0);

        // Abschlagsbetrag berechnen
        $partialNet = round($fullNet * $percentage / 100, 2);
        $partialGross = round($fullGross * $percentage / 100, 2);

        // Lifecycle-Eintrag als Abschlagsrechnung
        $paymentTermDays = (int)($opts['payment_term_days'] ?? 14);
        $docId = $this->create($instanceId, $projectId, 'partial_invoice', [
            'doc_number'    => $result['doc_number'],
            's3files_id'    => $result['s3files_id'],
            'net_amount'    => $partialNet,
            'gross_amount'  => $partialGross,
            'due_date'      => date('Y-m-d', strtotime("+{$paymentTermDays} days")),
            'created_by'    => $userId,
        ]);

        // Abschlagsrechnungs-Felder setzen
        $this->db->where('id', $docId);
        $this->db->update('document_lifecycle', [
            'partial_invoice_number' => $partialNumber,
            'partial_invoice_total'  => $totalPlanned,
            'partial_invoice_pct'    => $percentage,
            'parent_project_amount'  => $fullGross,
        ]);

        return [
            'doc_id'         => $docId,
            'doc_number'     => $result['doc_number'],
            's3files_id'     => $result['s3files_id'],
            'partial_number' => $partialNumber,
            'partial_pct'    => $percentage,
            'net_amount'     => $partialNet,
            'gross_amount'   => $partialGross,
            'total_pct_used' => $totalPct + $percentage,
        ];
    }

    /**
     * Schlussrechnung erstellen (verrechnet alle Abschlagsrechnungen)
     */
    public function createFinalInvoice(int $instanceId, int $projectId, int $userId, array $opts = []): array
    {
        // Alle nicht-stornierten Abschlagsrechnungen holen
        $this->db->where('instances_id', $instanceId);
        $this->db->where('projects_id', $projectId);
        $this->db->where('doc_type', 'partial_invoice');
        $this->db->where('status', 'cancelled', '!=');
        $this->db->orderBy('partial_invoice_number', 'ASC');
        $partials = $this->db->get('document_lifecycle') ?: [];

        if (empty($partials)) {
            throw new \RuntimeException("Keine Abschlagsrechnungen vorhanden.");
        }

        $totalPartialGross = 0;
        $totalPartialNet = 0;
        $partialSummary = [];
        foreach ($partials as $p) {
            $totalPartialGross += (float)$p['gross_amount'];
            $totalPartialNet += (float)$p['net_amount'];
            $partialSummary[] = [
                'doc_number' => $p['doc_number'],
                'gross'      => (float)$p['gross_amount'],
                'net'        => (float)$p['net_amount'],
                'pct'        => (float)$p['partial_invoice_pct'],
                'paid'       => $p['status'] === 'paid',
            ];
        }

        $renderOpts = array_merge($opts, [
            'is_final_invoice'      => true,
            'partial_deductions'    => $partialSummary,
            'total_deduction_gross' => $totalPartialGross,
            'total_deduction_net'   => $totalPartialNet,
            'force_regenerate'      => true,
        ]);

        $result = DocumentRenderer::renderAndStore(
            $this->db, $instanceId, $projectId, 'invoice', 'default', $renderOpts, $userId
        );

        // Gesamtbetrag aus Export
        $this->db->where('doc_number', $result['doc_number']);
        $this->db->where('instances_id', $instanceId);
        $export = $this->db->getOne('document_exports', ['totals_json']);
        $totals = $export ? json_decode($export['totals_json'], true) : [];
        $fullGross = (float)($totals['gross'] ?? 0);
        $fullNet = (float)($totals['net'] ?? 0);

        $remainingGross = round($fullGross - $totalPartialGross, 2);
        $remainingNet = round($fullNet - $totalPartialNet, 2);

        $paymentTermDays = (int)($opts['payment_term_days'] ?? 14);
        $docId = $this->create($instanceId, $projectId, 'invoice', [
            'doc_number'    => $result['doc_number'],
            's3files_id'    => $result['s3files_id'],
            'net_amount'    => $remainingNet,
            'gross_amount'  => $remainingGross,
            'due_date'      => date('Y-m-d', strtotime("+{$paymentTermDays} days")),
            'created_by'    => $userId,
        ]);

        $this->db->where('id', $docId);
        $this->db->update('document_lifecycle', [
            'is_final_invoice'      => 1,
            'parent_project_amount' => $fullGross,
        ]);

        return [
            'doc_id'              => $docId,
            'doc_number'          => $result['doc_number'],
            's3files_id'          => $result['s3files_id'],
            'full_gross'          => $fullGross,
            'deducted_gross'      => $totalPartialGross,
            'remaining_gross'     => $remainingGross,
            'partial_invoices'    => $partialSummary,
        ];
    }

    /**
     * Uebersicht der Abschlagsrechnungen fuer ein Projekt
     */
    public function getPartialInvoiceSummary(int $instanceId, int $projectId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('projects_id', $projectId);
        $this->db->where('doc_type', ['partial_invoice', 'invoice'], 'IN');
        $this->db->where('status', 'cancelled', '!=');
        $this->db->orderBy('created_at', 'ASC');
        $docs = $this->db->get('document_lifecycle') ?: [];

        $partials = [];
        $finalInvoice = null;
        $totalPct = 0;
        $totalGross = 0;
        $totalPaid = 0;

        foreach ($docs as $doc) {
            if ($doc['doc_type'] === 'partial_invoice') {
                $partials[] = $doc;
                $totalPct += (float)($doc['partial_invoice_pct'] ?? 0);
                $totalGross += (float)$doc['gross_amount'];
                if ($doc['status'] === 'paid') {
                    $totalPaid += (float)($doc['paid_amount'] ?? $doc['gross_amount']);
                }
            } elseif (!empty($doc['is_final_invoice'])) {
                $finalInvoice = $doc;
            }
        }

        $projectAmount = !empty($partials) ? (float)$partials[0]['parent_project_amount'] : 0;

        return [
            'partials'         => $partials,
            'final_invoice'    => $finalInvoice,
            'total_pct'        => $totalPct,
            'remaining_pct'    => round(100 - $totalPct, 2),
            'total_gross'      => $totalGross,
            'total_paid'       => $totalPaid,
            'project_amount'   => $projectAmount,
            'has_final'        => $finalInvoice !== null,
        ];
    }

    private function logStatusChange(int $docId, ?string $oldStatus, string $newStatus, int $userId, ?string $comment): void
    {
        $this->db->insert('document_status_history', [
            'document_lifecycle_id' => $docId,
            'old_status'            => $oldStatus,
            'new_status'            => $newStatus,
            'comment'               => $comment,
            'changed_by'            => $userId,
        ]);
    }
}
