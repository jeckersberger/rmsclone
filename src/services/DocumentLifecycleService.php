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
            'valid_until'        => $data['valid_until'] ?? null,
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
                'sent'     => ['accepted', 'rejected', 'cancelled'],
                'accepted' => [],
                'rejected' => [],
                'cancelled' => [],
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
        ];

        return $transitions[$docType][$currentStatus] ?? [];
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
