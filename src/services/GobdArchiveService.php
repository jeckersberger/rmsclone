<?php

namespace App\Services;

class GobdArchiveService
{
    private $db;
    private $logger;

    public function __construct($database, $logger = null)
    {
        $this->db = $database;
        $this->logger = $logger;
    }

    /**
     * Archive a single document with hash generation and audit logging
     *
     * @param int $instanceId
     * @param int $documentId
     * @param int $userId
     * @return array ['success' => bool, 'hash' => string, 'archived_at' => string, 'retention_expires_at' => string]
     */
    public function archiveDocument($instanceId, $documentId, $userId)
    {
        // Fetch the document
        $this->db->where('document_exports_id', $documentId);
        $this->db->where('instances_id', $instanceId);
        $this->db->where('document_exports_deleted', 0);
        $document = $this->db->getOne('document_exports', null, [
            'document_exports_id',
            'instances_id',
            'document_exports_number',
            'document_exports_date',
            'document_exports_gross',
            'document_exports_net',
            'document_exports_tax',
            'document_exports_type',
            'document_exports_status',
            'archive_status',
            'archived_at'
        ]);

        if (!$document) {
            return ['success' => false, 'error' => 'Document not found'];
        }

        if ($document['archive_status'] === 'archived') {
            return ['success' => false, 'error' => 'Document already archived'];
        }

        // Generate hash
        $hash = $this->generateDocumentHash($document);

        // Calculate retention expiry: 10 years from document date
        $documentDate = new \DateTime($document['document_exports_date']);
        $retentionExpiry = $documentDate->modify('+10 years')->format('Y-m-d H:i:s');
        $archivedAt = (new \DateTime())->format('Y-m-d H:i:s');

        // Update document
        $this->db->where('document_exports_id', $documentId);
        $updateData = [
            'archive_status' => 'archived',
            'archive_hash' => $hash,
            'retention_expires_at' => $retentionExpiry,
            'archived_at' => $archivedAt
        ];
        $this->db->update('document_exports', $updateData);

        // Log the action
        $this->logAction(
            $instanceId,
            $documentId,
            'ARCHIVE',
            $userId,
            'Document archived with hash: ' . $hash
        );

        return [
            'success' => true,
            'hash' => $hash,
            'archived_at' => $archivedAt,
            'retention_expires_at' => $retentionExpiry,
            'document_id' => $documentId
        ];
    }

    /**
     * Bulk archive all unarchived, finalized documents
     *
     * @param int $instanceId
     * @param int $userId
     * @return array ['success' => bool, 'count' => int, 'archived_documents' => array]
     */
    public function bulkArchive($instanceId, $userId)
    {
        // Fetch all unarchived documents that are finalized
        $this->db->where('instances_id', $instanceId);
        $this->db->where('document_exports_deleted', 0);
        $this->db->where('archive_status', 'active');
        $this->db->where('document_exports_status', 'finalized');
        $documents = $this->db->get('document_exports', null, [
            'document_exports_id',
            'instances_id',
            'document_exports_number',
            'document_exports_date',
            'document_exports_gross',
            'document_exports_net',
            'document_exports_tax',
            'document_exports_type',
            'document_exports_status'
        ]);

        $archivedDocuments = [];
        $count = 0;

        if ($documents) {
            foreach ($documents as $document) {
                $result = $this->archiveDocument($instanceId, $document['document_exports_id'], $userId);
                if ($result['success']) {
                    $count++;
                    $archivedDocuments[] = [
                        'document_id' => $document['document_exports_id'],
                        'document_number' => $document['document_exports_number'],
                        'hash' => $result['hash']
                    ];
                }
            }
        }

        $this->logAction(
            $instanceId,
            null,
            'BULK_ARCHIVE',
            $userId,
            'Bulk archived ' . $count . ' documents'
        );

        return [
            'success' => true,
            'count' => $count,
            'archived_documents' => $archivedDocuments
        ];
    }

    /**
     * Verify integrity of a single archived document
     *
     * @param int $instanceId
     * @param int $documentId
     * @return array ['valid' => bool, 'stored_hash' => string, 'current_hash' => string, 'document_id' => int]
     */
    public function verifyIntegrity($instanceId, $documentId)
    {
        // Fetch the document
        $this->db->where('document_exports_id', $documentId);
        $this->db->where('instances_id', $instanceId);
        $this->db->where('document_exports_deleted', 0);
        $document = $this->db->getOne('document_exports', null, [
            'document_exports_id',
            'instances_id',
            'document_exports_number',
            'document_exports_date',
            'document_exports_gross',
            'document_exports_net',
            'document_exports_tax',
            'document_exports_type',
            'archive_hash'
        ]);

        if (!$document) {
            return ['valid' => false, 'error' => 'Document not found'];
        }

        if (!$document['archive_hash']) {
            return ['valid' => false, 'error' => 'Document not archived'];
        }

        $storedHash = $document['archive_hash'];
        $currentHash = $this->generateDocumentHash($document);
        $valid = ($storedHash === $currentHash);

        return [
            'valid' => $valid,
            'stored_hash' => $storedHash,
            'current_hash' => $currentHash,
            'document_id' => $documentId,
            'matches' => $valid
        ];
    }

    /**
     * Verify integrity of all archived documents
     *
     * @param int $instanceId
     * @return array ['success' => bool, 'total_checked' => int, 'valid_count' => int, 'corrupted' => array]
     */
    public function bulkVerifyIntegrity($instanceId)
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('document_exports_deleted', 0);
        $this->db->where('archive_status', 'archived');
        $documents = $this->db->get('document_exports', null, [
            'document_exports_id',
            'instances_id',
            'document_exports_number',
            'document_exports_date',
            'document_exports_gross',
            'document_exports_net',
            'document_exports_tax',
            'document_exports_type',
            'archive_hash'
        ]);

        $totalChecked = 0;
        $validCount = 0;
        $corrupted = [];

        if ($documents) {
            foreach ($documents as $document) {
                $totalChecked++;
                $storedHash = $document['archive_hash'];
                $currentHash = $this->generateDocumentHash($document);

                if ($storedHash === $currentHash) {
                    $validCount++;
                } else {
                    $corrupted[] = [
                        'document_id' => $document['document_exports_id'],
                        'document_number' => $document['document_exports_number'],
                        'stored_hash' => $storedHash,
                        'current_hash' => $currentHash
                    ];
                }
            }
        }

        return [
            'success' => true,
            'total_checked' => $totalChecked,
            'valid_count' => $validCount,
            'corrupted_count' => count($corrupted),
            'corrupted' => $corrupted
        ];
    }

    /**
     * Get retention status overview
     *
     * @param int $instanceId
     * @return array ['archived' => int, 'active' => int, 'retention_expired' => int, 'expiring_soon' => int]
     */
    public function getRetentionStatus($instanceId)
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('document_exports_deleted', 0);

        // Archived documents
        $this->db->where('archive_status', 'archived');
        $archivedCount = $this->db->getValue('document_exports', 'count(*)');

        // Active documents
        $this->db->where('instances_id', $instanceId);
        $this->db->where('document_exports_deleted', 0);
        $this->db->where('archive_status', 'active');
        $activeCount = $this->db->getValue('document_exports', 'count(*)');

        // Retention expired
        $this->db->where('instances_id', $instanceId);
        $this->db->where('document_exports_deleted', 0);
        $this->db->where('archive_status', 'retention_expired');
        $expiredCount = $this->db->getValue('document_exports', 'count(*)');

        // Expiring soon (within 6 months)
        $this->db->where('instances_id', $instanceId);
        $this->db->where('document_exports_deleted', 0);
        $this->db->where('archive_status', 'archived');
        $this->db->where('retention_expires_at', ['<='], (new \DateTime('+6 months'))->format('Y-m-d H:i:s'));
        $this->db->where('retention_expires_at', ['>='], (new \DateTime())->format('Y-m-d H:i:s'));
        $expiringCount = $this->db->getValue('document_exports', 'count(*)');

        return [
            'archived' => (int)$archivedCount,
            'active' => (int)$activeCount,
            'retention_expired' => (int)$expiredCount,
            'expiring_soon' => (int)$expiringCount
        ];
    }

    /**
     * Get documents whose retention period expires soon
     *
     * @param int $instanceId
     * @param int $monthsAhead
     * @return array Documents expiring within specified months
     */
    public function getExpiringDocuments($instanceId, $monthsAhead = 6)
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('document_exports_deleted', 0);
        $this->db->where('archive_status', 'archived');
        $this->db->where('retention_expires_at', ['<='], (new \DateTime('+' . $monthsAhead . ' months'))->format('Y-m-d H:i:s'));
        $this->db->where('retention_expires_at', ['>='], (new \DateTime())->format('Y-m-d H:i:s'));
        $this->db->orderBy('retention_expires_at', 'ASC');

        return $this->db->get('document_exports', null, [
            'document_exports_id',
            'document_exports_number',
            'document_exports_date',
            'retention_expires_at',
            'document_exports_type',
            'document_exports_gross'
        ]) ?: [];
    }

    /**
     * Mark documents with expired retention periods (does not delete)
     *
     * @param int $instanceId
     * @param int $userId
     * @return array ['success' => bool, 'count' => int, 'expired_documents' => array]
     */
    public function processExpired($instanceId, $userId)
    {
        $now = (new \DateTime())->format('Y-m-d H:i:s');

        // Find archived documents whose retention has expired
        $this->db->where('instances_id', $instanceId);
        $this->db->where('document_exports_deleted', 0);
        $this->db->where('archive_status', 'archived');
        $this->db->where('retention_expires_at', ['<'], $now);
        $documents = $this->db->get('document_exports', null, [
            'document_exports_id',
            'document_exports_number',
            'retention_expires_at'
        ]);

        $count = 0;
        $expiredDocuments = [];

        if ($documents) {
            foreach ($documents as $document) {
                $this->db->where('document_exports_id', $document['document_exports_id']);
                $this->db->update('document_exports', ['archive_status' => 'retention_expired']);

                $count++;
                $expiredDocuments[] = [
                    'document_id' => $document['document_exports_id'],
                    'document_number' => $document['document_exports_number'],
                    'expired_at' => $document['retention_expires_at']
                ];

                $this->logAction(
                    $instanceId,
                    $document['document_exports_id'],
                    'RETENTION_EXPIRED',
                    $userId,
                    'Retention period expired'
                );
            }
        }

        return [
            'success' => true,
            'count' => $count,
            'expired_documents' => $expiredDocuments
        ];
    }

    /**
     * Log an action to the GoBD audit trail
     *
     * @param int $instanceId
     * @param int|null $documentId
     * @param string $action
     * @param int $userId
     * @param string|null $details
     * @return void
     */
    public function logAction($instanceId, $documentId, $action, $userId, $details = null)
    {
        $logData = [
            'instances_id' => $instanceId,
            'document_exports_id' => $documentId,
            'action' => $action,
            'user_id' => $userId,
            'details' => $details,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s')
        ];

        $this->db->insert('gobd_audit_log', $logData);
    }

    /**
     * Retrieve audit trail with optional filters
     *
     * @param int $instanceId
     * @param int|null $documentId
     * @param string|null $from (Y-m-d H:i:s format)
     * @param string|null $to (Y-m-d H:i:s format)
     * @return array Audit log entries
     */
    public function getAuditTrail($instanceId, $documentId = null, $from = null, $to = null)
    {
        $this->db->where('instances_id', $instanceId);

        if ($documentId) {
            $this->db->where('document_exports_id', $documentId);
        }

        if ($from) {
            $this->db->where('created_at', ['>='], $from);
        }

        if ($to) {
            $this->db->where('created_at', ['<='], $to);
        }

        $this->db->orderBy('created_at', 'DESC');

        return $this->db->get('gobd_audit_log', null, [
            'id',
            'instances_id',
            'document_exports_id',
            'action',
            'user_id',
            'details',
            'ip_address',
            'created_at'
        ]) ?: [];
    }

    /**
     * Export GoBD-compliant archive index (Verfahrensdokumentation)
     *
     * @param int $instanceId
     * @param string $format (csv)
     * @return string CSV content with UTF-8 BOM
     */
    public function exportArchiveIndex($instanceId, $format = 'csv')
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('document_exports_deleted', 0);
        $this->db->where('archive_status', 'archived');
        $this->db->orderBy('document_exports_date', 'ASC');

        $documents = $this->db->get('document_exports', null, [
            'document_exports_id',
            'document_exports_number',
            'document_exports_date',
            'document_exports_type',
            'document_exports_gross',
            'document_exports_net',
            'document_exports_tax',
            'archive_hash',
            'archived_at',
            'retention_expires_at'
        ]) ?: [];

        if ($format === 'csv') {
            return $this->generateCsvIndex($documents);
        }

        return '';
    }

    /**
     * Generate CSV with UTF-8 BOM and semicolon delimiters
     *
     * @param array $documents
     * @return string CSV content
     */
    private function generateCsvIndex($documents)
    {
        $csv = "\xEF\xBB\xBF"; // UTF-8 BOM

        // Header row
        $headers = [
            'Document Number',
            'Document Date',
            'Document Type',
            'Client',
            'Gross Amount',
            'Net Amount',
            'Tax Amount',
            'Archive Date',
            'Archive Hash',
            'Retention Expires'
        ];
        $csv .= implode(';', $headers) . "\n";

        // Data rows
        foreach ($documents as $doc) {
            $row = [
                $doc['document_exports_number'],
                $doc['document_exports_date'],
                $doc['document_exports_type'],
                '', // Client - would need to join with clients table
                number_format($doc['document_exports_gross'], 2, ',', '.'),
                number_format($doc['document_exports_net'], 2, ',', '.'),
                number_format($doc['document_exports_tax'], 2, ',', '.'),
                $doc['archived_at'],
                $doc['archive_hash'],
                $doc['retention_expires_at']
            ];
            $csv .= implode(';', $row) . "\n";
        }

        return $csv;
    }

    /**
     * Generate deterministic SHA-256 hash from document data
     *
     * Hash is created from: document_exports_number + document_exports_date +
     * document_exports_gross + document_exports_net + instances_id
     *
     * @param array $document Document row data
     * @return string SHA-256 hash (hex)
     */
    private function generateDocumentHash($document)
    {
        $hashInput = implode('|', [
            $document['document_exports_number'],
            $document['document_exports_date'],
            $document['document_exports_gross'],
            $document['document_exports_net'],
            $document['instances_id']
        ]);

        return hash('sha256', $hashInput);
    }
}
