<?php
/**
 * Dunning Queue Service - Approval Workflow
 *
 * Manages the dunning approval queue. Scans for overdue invoices that need dunning,
 * creates proposals (not actual dunnings), stores them in dunning_queue table for
 * manual approval before any letter is sent.
 *
 * Database Migration (reference):
 * CREATE TABLE dunning_queue (
 *   id INT AUTO_INCREMENT PRIMARY KEY,
 *   instances_id INT NOT NULL,
 *   document_lifecycle_id INT NOT NULL,
 *   document_exports_id INT,
 *   dunning_level INT NOT NULL,
 *   proposed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 *   status ENUM('pending','approved','rejected','sent') DEFAULT 'pending',
 *   approved_by INT,
 *   approved_at DATETIME,
 *   sent_at DATETIME,
 *   notes TEXT,
 *   fee_amount DECIMAL(10,2),
 *   interest_amount DECIMAL(10,2),
 *   total_with_fees DECIMAL(12,2),
 *   created_by INT,
 *   updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
 *   UNIQUE KEY unique_pending (document_lifecycle_id, dunning_level, status),
 *   FOREIGN KEY (instances_id) REFERENCES instances(instances_id),
 *   FOREIGN KEY (document_lifecycle_id) REFERENCES document_lifecycle(id),
 *   INDEX idx_instance_status (instances_id, status),
 *   INDEX idx_pending (status, proposed_at)
 * );
 */

class DunningQueueService
{
    private $db;
    private $dunningService;

    public function __construct($db)
    {
        $this->db = $db;
        $this->dunningService = new DunningService($db);
    }

    /**
     * Scan for overdue invoices and generate dunning proposals
     * Creates queue entries for invoices that need next dunning level
     * Skips if there's already a pending proposal for that invoice+level
     *
     * @param int $instanceId
     * @return array ['created' => count, 'skipped' => count, 'details' => []]
     */
    public function generateProposals(int $instanceId): array
    {
        $created = 0;
        $skipped = 0;
        $details = [];

        // Get all overdue invoices with their dunning status
        $overdue = $this->dunningService->getOverdueInvoices($instanceId);

        foreach ($overdue as $invoice) {
            $docLifecycleId = $invoice['id'];
            $nextLevel = $invoice['next_dunning_level'];

            // Skip if no next level needed
            if (!$nextLevel) {
                $skipped++;
                continue;
            }

            $levelNum = (int)$nextLevel['level'];

            // Check if there's already a pending proposal for this invoice+level
            $this->db->where('document_lifecycle_id', $docLifecycleId);
            $this->db->where('dunning_level', $levelNum);
            $this->db->where('status', 'pending');
            $existing = $this->db->getOne('dunning_queue', null);

            if ($existing) {
                $skipped++;
                continue;
            }

            // Calculate fee and interest
            $daysOverdue = $invoice['days_overdue'];
            $fee = (float)$nextLevel['fee'];
            $interestRate = (float)$nextLevel['interest_rate'];
            $interestAmount = 0;

            if ($interestRate > 0) {
                // Verzugszinsen: Rechnungsbetrag * Zinssatz / 365 * ueberfaellige Tage
                $interestAmount = round(
                    (float)$invoice['gross_amount'] * ($interestRate / 100) / 365 * $daysOverdue,
                    2
                );
            }

            $totalWithFees = (float)$invoice['gross_amount'] + $fee + $interestAmount;

            // Create proposal entry
            $this->db->insert('dunning_queue', [
                'instances_id'          => $instanceId,
                'document_lifecycle_id' => $docLifecycleId,
                'document_exports_id'   => $invoice['document_exports_id'] ?? null,
                'dunning_level'         => $levelNum,
                'status'                => 'pending',
                'proposed_at'           => date('Y-m-d H:i:s'),
                'fee_amount'            => $fee,
                'interest_amount'       => $interestAmount,
                'total_with_fees'       => $totalWithFees,
            ]);

            $created++;
            $details[] = [
                'queue_id' => $this->db->getInsertId(),
                'invoice_number' => $invoice['doc_number'],
                'client_name' => $invoice['clients_name'],
                'level' => $levelNum,
                'amount' => $totalWithFees,
            ];
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'details' => $details,
        ];
    }

    /**
     * Get queue entries with full details
     *
     * @param int $instanceId
     * @param string $status Filter by status (pending, approved, rejected, sent)
     * @return array
     */
    public function getQueue(int $instanceId, string $status = 'pending'): array
    {
        $this->db->where('dq.instances_id', $instanceId);

        if (!empty($status) && $status !== 'all') {
            $this->db->where('dq.status', $status);
        }

        $this->db->join('document_lifecycle dl', 'dq.document_lifecycle_id=dl.id', 'LEFT');
        $this->db->join('projects p', 'dl.projects_id=p.projects_id', 'LEFT');
        $this->db->join('clients c', 'p.clients_id=c.clients_id', 'LEFT');
        $this->db->orderBy('dq.proposed_at', 'ASC');

        $rows = $this->db->get('dunning_queue dq', null, [
            'dq.*',
            'dl.doc_number',
            'dl.gross_amount',
            'dl.due_date',
            'p.projects_name',
            'c.clients_name',
            'c.clients_email',
        ]) ?: [];

        // Enrich with calculated fields
        foreach ($rows as &$row) {
            $row['days_overdue'] = (int)((time() - strtotime($row['due_date'])) / 86400);
        }
        unset($row);

        return $rows;
    }

    /**
     * Approve a proposal
     * Marks as approved but doesn't generate PDF or send email yet
     *
     * @param int $queueId
     * @param int $userId
     * @param string|null $notes
     * @return bool
     */
    public function approve(int $queueId, int $userId, ?string $notes = null): bool
    {
        $this->db->where('id', $queueId);
        $queue = $this->db->getOne('dunning_queue', null);

        if (!$queue) return false;

        $this->db->where('id', $queueId);
        $this->db->update('dunning_queue', [
            'status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => date('Y-m-d H:i:s'),
            'notes' => $notes,
        ]);

        return true;
    }

    /**
     * Reject a proposal
     *
     * @param int $queueId
     * @param int $userId
     * @param string|null $notes
     * @return bool
     */
    public function reject(int $queueId, int $userId, ?string $notes = null): bool
    {
        $this->db->where('id', $queueId);
        $queue = $this->db->getOne('dunning_queue', null);

        if (!$queue) return false;

        $this->db->where('id', $queueId);
        $this->db->update('dunning_queue', [
            'status' => 'rejected',
            'notes' => $notes,
        ]);

        return true;
    }

    /**
     * Mark a queue entry as sent (after email has been dispatched)
     *
     * @param int $queueId
     * @return bool
     */
    public function markSent(int $queueId): bool
    {
        $this->db->where('id', $queueId);
        $queue = $this->db->getOne('dunning_queue', null);

        if (!$queue) return false;

        $this->db->where('id', $queueId);
        $this->db->update('dunning_queue', [
            'status' => 'sent',
            'sent_at' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    /**
     * Approve a proposal, generate PDF letter, and send email in one step
     *
     * @param int $queueId
     * @param int $userId
     * @param string|null $notes
     * @return array ['success' => bool, 'pdf_path' => string|null, 'email_sent' => bool, 'dunning_id' => int|null, 'message' => string]
     */
    public function approveAndSend(int $queueId, int $userId, ?string $notes = null): array
    {
        $result = [
            'success' => false,
            'pdf_path' => null,
            'email_sent' => false,
            'dunning_id' => null,
            'message' => '',
        ];

        // Get queue entry
        $this->db->where('id', $queueId);
        $queue = $this->db->getOne('dunning_queue', null);
        if (!$queue) {
            $result['message'] = 'Queue entry not found';
            return $result;
        }

        $instanceId = (int)$queue['instances_id'];
        $docLifecycleId = (int)$queue['document_lifecycle_id'];

        // Create actual dunning entry via DunningService
        $dunning = $this->dunningService->createDunning($instanceId, $docLifecycleId, $userId);
        if (!$dunning) {
            $result['message'] = 'Failed to create dunning entry';
            return $result;
        }

        $dunningId = $dunning['dunning_id'];
        $result['dunning_id'] = $dunningId;

        // Generate PDF letter
        $letterService = new DunningLetterService($this->db);
        $letterResult = $letterService->generateLetter($instanceId, $dunningId, $userId);
        if ($letterResult) {
            $result['pdf_path'] = $letterResult['filename'];
        }

        // Get invoice details for email
        $this->db->where('id', $docLifecycleId);
        $this->db->where('instances_id', $instanceId);
        $this->db->join('projects p', 'document_lifecycle.projects_id=p.projects_id', 'LEFT');
        $this->db->join('clients c', 'p.clients_id=c.clients_id', 'LEFT');
        $invoice = $this->db->getOne('document_lifecycle', null, [
            'document_lifecycle.*',
            'c.clients_name',
            'c.clients_email',
        ]);

        $emailSent = false;
        if ($invoice && !empty($invoice['clients_email'])) {
            // Send email with attachment
            try {
                $mailer = new EmailService();
                $emailSent = $mailer->sendDunningLetter(
                    $invoice['clients_email'],
                    $invoice['clients_name'],
                    $dunning,
                    $letterResult['s3files_id'] ?? null
                );

                if ($emailSent) {
                    $letterService->markLetterSent($dunningId, $invoice['clients_email']);
                }
            } catch (\Exception $e) {
                // Log error but don't fail the whole operation
                error_log('Dunning letter email failed: ' . $e->getMessage());
            }
        }

        // Mark queue as approved and sent
        $this->db->where('id', $queueId);
        $this->db->update('dunning_queue', [
            'status' => 'sent',
            'approved_by' => $userId,
            'approved_at' => date('Y-m-d H:i:s'),
            'sent_at' => date('Y-m-d H:i:s'),
            'notes' => $notes,
        ]);

        $result['success'] = true;
        $result['email_sent'] = $emailSent;
        $result['message'] = 'Dunning approval and send completed successfully';

        return $result;
    }

    /**
     * Approve multiple queue entries at once
     *
     * @param array $queueIds
     * @param int $userId
     * @return array ['approved' => count, 'failed' => count, 'failures' => []]
     */
    public function bulkApprove(array $queueIds, int $userId): array
    {
        $approved = 0;
        $failed = 0;
        $failures = [];

        foreach ($queueIds as $queueId) {
            if ($this->approve((int)$queueId, $userId)) {
                $approved++;
            } else {
                $failed++;
                $failures[] = ['queue_id' => $queueId, 'reason' => 'Not found or already processed'];
            }
        }

        return [
            'approved' => $approved,
            'failed' => $failed,
            'failures' => $failures,
        ];
    }

    /**
     * Get statistics for the queue
     *
     * @param int $instanceId
     * @return array ['pending' => count, 'approved' => count, 'sent' => count, 'rejected' => count, 'total_pending_amount' => float]
     */
    public function getStats(int $instanceId): array
    {
        // Count by status
        $this->db->where('instances_id', $instanceId);
        $pending = $this->db->getValue('dunning_queue', 'COUNT(*)', ['status' => 'pending']) ?: 0;

        $this->db->where('instances_id', $instanceId);
        $approved = $this->db->getValue('dunning_queue', 'COUNT(*)', ['status' => 'approved']) ?: 0;

        $this->db->where('instances_id', $instanceId);
        $sent = $this->db->getValue('dunning_queue', 'COUNT(*)', ['status' => 'sent']) ?: 0;

        $this->db->where('instances_id', $instanceId);
        $rejected = $this->db->getValue('dunning_queue', 'COUNT(*)', ['status' => 'rejected']) ?: 0;

        // Sum pending amounts
        $this->db->where('instances_id', $instanceId);
        $this->db->where('status', 'pending');
        $totalPending = (float)$this->db->getValue('dunning_queue', 'SUM(total_with_fees)') ?? 0;

        return [
            'pending' => (int)$pending,
            'approved' => (int)$approved,
            'sent' => (int)$sent,
            'rejected' => (int)$rejected,
            'total_pending_amount' => round($totalPending, 2),
        ];
    }
}
