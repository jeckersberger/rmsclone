<?php
/**
 * DamageWorkflowService - Schadenmanagement-Workflow (K3)
 *
 * Verwaltet den kompletten Workflow von Schadensfall bis Abschluss:
 * - Statusübergänge mit Validierung
 * - Kostenabschätzungen und Angebote
 * - Versicherungsansprüche
 * - Kundenbelastung und Depositabrechnungen
 * - Fotodokumentation
 */
class DamageWorkflowService
{
    private $db;

    // Status Konstanten
    const STATUS_REPORTED = 'reported';
    const STATUS_ASSESSED = 'assessed';
    const STATUS_QUOTE_REQUESTED = 'quote_requested';
    const STATUS_QUOTE_RECEIVED = 'quote_received';
    const STATUS_REPAIR_APPROVED = 'repair_approved';
    const STATUS_IN_REPAIR = 'in_repair';
    const STATUS_REPAIRED = 'repaired';
    const STATUS_VERIFIED = 'verified';
    const STATUS_CHARGED = 'charged';
    const STATUS_INSURANCE_CLAIMED = 'insurance_claimed';
    const STATUS_CLOSED = 'closed';

    // Severity Konstanten
    const SEVERITY_MINOR = 'minor';
    const SEVERITY_MODERATE = 'moderate';
    const SEVERITY_MAJOR = 'major';
    const SEVERITY_TOTAL_LOSS = 'total_loss';

    // Valid transitions for workflow
    private const VALID_TRANSITIONS = [
        self::STATUS_REPORTED => [self::STATUS_ASSESSED],
        self::STATUS_ASSESSED => [self::STATUS_QUOTE_REQUESTED, self::STATUS_REPAIR_APPROVED, self::STATUS_CLOSED],
        self::STATUS_QUOTE_REQUESTED => [self::STATUS_QUOTE_RECEIVED],
        self::STATUS_QUOTE_RECEIVED => [self::STATUS_REPAIR_APPROVED, self::STATUS_CLOSED],
        self::STATUS_REPAIR_APPROVED => [self::STATUS_IN_REPAIR],
        self::STATUS_IN_REPAIR => [self::STATUS_REPAIRED],
        self::STATUS_REPAIRED => [self::STATUS_VERIFIED],
        self::STATUS_VERIFIED => [self::STATUS_CHARGED, self::STATUS_INSURANCE_CLAIMED, self::STATUS_CLOSED],
        self::STATUS_CHARGED => [self::STATUS_CLOSED],
        self::STATUS_INSURANCE_CLAIMED => [self::STATUS_CLOSED],
    ];

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get complete workflow with all related data
     */
    public function getWorkflow(int $damageReportId): ?array
    {
        $this->db->where('damage_report_id', $damageReportId);
        $workflow = $this->db->getOne('damage_workflows', null, ['*']);

        if (!$workflow) return null;

        // Load status log
        $this->db->where('workflow_id', $workflow['id']);
        $this->db->orderBy('created_at', 'ASC');
        $workflow['status_log'] = $this->db->get('damage_workflow_log', null, ['*']) ?: [];

        // Load cost estimates
        $this->db->where('workflow_id', $workflow['id']);
        $this->db->orderBy('created_at', 'DESC');
        $workflow['cost_estimates'] = $this->db->get('damage_cost_estimates', null, ['*']) ?: [];

        // Load photos
        $this->db->where('damage_report_id', $damageReportId);
        $this->db->orderBy('created_at', 'DESC');
        $workflow['photos'] = $this->db->get('damage_photos', null, ['*']) ?: [];

        return $workflow;
    }

    /**
     * Create a new workflow from damage report
     */
    public function createWorkflow(int $damageReportId, string $severity, ?float $estimatedCost, ?int $assignedTo, int $instanceId): int
    {
        // Verify damage report exists
        $this->db->where('id', $damageReportId);
        $report = $this->db->getOne('damage_reports', null, ['id']);
        if (!$report) {
            return 0;
        }

        $workflowId = $this->db->insert('damage_workflows', [
            'damage_report_id' => $damageReportId,
            'instances_id' => $instanceId,
            'status' => self::STATUS_REPORTED,
            'severity' => $severity,
            'estimated_cost' => $estimatedCost,
            'assigned_to' => $assignedTo,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $workflowId ?: 0;
    }

    /**
     * Get valid transitions for current status
     */
    public function getValidTransitions(string $currentStatus): array
    {
        return self::VALID_TRANSITIONS[$currentStatus] ?? [];
    }

    /**
     * Transition workflow to new status with validation
     */
    public function transitionStatus(int $workflowId, string $newStatus, int $userId, ?string $notes = null): bool
    {
        $this->db->where('id', $workflowId);
        $workflow = $this->db->getOne('damage_workflows', null, ['status']);

        if (!$workflow) {
            return false;
        }

        $currentStatus = $workflow['status'];

        // Validate transition
        $validTransitions = $this->getValidTransitions($currentStatus);
        if (!in_array($newStatus, $validTransitions)) {
            return false;
        }

        // Update workflow status
        $this->db->where('id', $workflowId);
        $updated = $this->db->update('damage_workflows', [
            'status' => $newStatus,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if ($updated) {
            // Log the transition
            $this->db->insert('damage_workflow_log', [
                'workflow_id' => $workflowId,
                'from_status' => $currentStatus,
                'to_status' => $newStatus,
                'changed_by' => $userId,
                'notes' => $notes,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return $updated;
    }

    /**
     * Add cost estimate to workflow
     */
    public function addCostEstimate(int $workflowId, array $data): int
    {
        $estimateId = $this->db->insert('damage_cost_estimates', [
            'workflow_id' => $workflowId,
            'vendor_name' => $data['vendor_name'],
            'description' => $data['description'] ?? null,
            'amount' => floatval($data['amount']),
            'is_accepted' => $data['is_accepted'] ?? false,
            'document_path' => $data['document_path'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Update workflow estimated_cost with this estimate if it's higher
        if ($estimateId && ($data['is_accepted'] ?? false)) {
            $this->db->where('id', $workflowId);
            $workflow = $this->db->getOne('damage_workflows', null, ['estimated_cost']);

            $newCost = floatval($data['amount']);
            if (!$workflow['estimated_cost'] || $newCost > floatval($workflow['estimated_cost'])) {
                $this->db->where('id', $workflowId);
                $this->db->update('damage_workflows', [
                    'estimated_cost' => $newCost,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        return $estimateId ?: 0;
    }

    /**
     * Accept an estimate and update workflow
     */
    public function acceptEstimate(int $estimateId): bool
    {
        $this->db->where('id', $estimateId);
        $estimate = $this->db->getOne('damage_cost_estimates', null, ['workflow_id', 'amount']);

        if (!$estimate) {
            return false;
        }

        // Update estimate
        $this->db->where('id', $estimateId);
        $updated = $this->db->update('damage_cost_estimates', [
            'is_accepted' => true,
        ]);

        if ($updated) {
            // Update workflow estimated_cost
            $this->db->where('id', $estimate['workflow_id']);
            $this->db->update('damage_workflows', [
                'estimated_cost' => floatval($estimate['amount']),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return $updated;
    }

    /**
     * Add damage photo documentation
     */
    public function addPhoto(int $damageReportId, string $filePath, string $photoType, ?string $description, int $userId): int
    {
        $photoId = $this->db->insert('damage_photos', [
            'damage_report_id' => $damageReportId,
            'file_path' => $filePath,
            'description' => $description,
            'photo_type' => $photoType,
            'uploaded_by' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $photoId ?: 0;
    }

    /**
     * Charge customer for damage
     */
    public function chargeCustomer(int $workflowId, float $amount, ?int $invoiceId): bool
    {
        $this->db->where('id', $workflowId);
        $updated = $this->db->update('damage_workflows', [
            'customer_charged' => true,
            'customer_charge_amount' => $amount,
            'customer_charge_invoice_id' => $invoiceId,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $updated;
    }

    /**
     * Deduct damage cost from customer deposit
     */
    public function deductFromDeposit(int $workflowId, float $amount): bool
    {
        $this->db->where('id', $workflowId);
        $updated = $this->db->update('damage_workflows', [
            'deposit_deducted' => true,
            'deposit_deduction_amount' => $amount,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $updated;
    }

    /**
     * Submit insurance claim
     */
    public function submitInsuranceClaim(int $workflowId, string $claimId): bool
    {
        $this->db->where('id', $workflowId);
        $updated = $this->db->update('damage_workflows', [
            'insurance_claim_id' => $claimId,
            'insurance_claim_status' => 'claimed',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $updated;
    }

    /**
     * Update insurance claim status
     */
    public function updateInsuranceStatus(int $workflowId, string $status): bool
    {
        $validStatuses = ['not_claimed', 'claimed', 'approved', 'rejected', 'paid'];
        if (!in_array($status, $validStatuses)) {
            return false;
        }

        $this->db->where('id', $workflowId);
        $updated = $this->db->update('damage_workflows', [
            'insurance_claim_status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $updated;
    }

    /**
     * Get all open workflows for instance
     */
    public function getOpenWorkflows(int $instanceId, ?string $severity = null): array
    {
        $this->db->where('dw.instances_id', $instanceId);
        $this->db->where('dw.status', self::STATUS_CLOSED, '!=');

        if ($severity) {
            $this->db->where('dw.severity', $severity);
        }

        $this->db->join('damage_reports dr', 'dw.damage_report_id = dr.id', 'LEFT');
        $this->db->join('assets a', 'dr.assets_id = a.assets_id', 'LEFT');
        $this->db->join('assetTypes at', 'a.assetTypes_id = at.assetTypes_id', 'LEFT');
        $this->db->join('users u', 'dw.assigned_to = u.users_userid', 'LEFT');
        $this->db->orderBy('dw.created_at', 'DESC');

        return $this->db->get('damage_workflows dw', null, [
            'dw.*',
            'dr.severity as report_severity',
            'a.assets_tag',
            'at.assetTypes_name',
            'u.users_name1',
            'u.users_name2',
        ]) ?: [];
    }

    /**
     * Get workflow status log
     */
    public function getStatusLog(int $workflowId): array
    {
        $this->db->where('workflow_id', $workflowId);
        $this->db->join('users u', 'damage_workflow_log.changed_by = u.users_userid', 'LEFT');
        $this->db->orderBy('damage_workflow_log.created_at', 'ASC');

        return $this->db->get('damage_workflow_log', null, [
            'damage_workflow_log.*',
            'u.users_name1',
            'u.users_name2',
        ]) ?: [];
    }

    /**
     * Get dashboard statistics
     */
    public function getDashboardStats(int $instanceId): array
    {
        // Count by status
        $statusCounts = [];
        foreach ([
            self::STATUS_REPORTED, self::STATUS_ASSESSED, self::STATUS_QUOTE_REQUESTED,
            self::STATUS_QUOTE_RECEIVED, self::STATUS_REPAIR_APPROVED, self::STATUS_IN_REPAIR,
            self::STATUS_REPAIRED, self::STATUS_VERIFIED, self::STATUS_CHARGED,
            self::STATUS_INSURANCE_CLAIMED, self::STATUS_CLOSED
        ] as $status) {
            $this->db->where('instances_id', $instanceId);
            $this->db->where('status', $status);
            $count = $this->db->getValue('damage_workflows', 'COUNT(*)');
            $statusCounts[$status] = intval($count);
        }

        // Total open
        $this->db->where('instances_id', $instanceId);
        $this->db->where('status', self::STATUS_CLOSED, '!=');
        $openCount = $this->db->getValue('damage_workflows', 'COUNT(*)');

        // Count by severity
        $severityCounts = [];
        foreach ([self::SEVERITY_MINOR, self::SEVERITY_MODERATE, self::SEVERITY_MAJOR, self::SEVERITY_TOTAL_LOSS] as $sev) {
            $this->db->where('instances_id', $instanceId);
            $this->db->where('severity', $sev);
            $count = $this->db->getValue('damage_workflows', 'COUNT(*)');
            $severityCounts[$sev] = intval($count);
        }

        // Total costs this month
        $thisMonth = date('Y-m-01');
        $result = $this->db->rawQuery(
            "SELECT SUM(actual_cost) as total_actual, SUM(estimated_cost) as total_estimated
             FROM damage_workflows
             WHERE instances_id = ? AND created_at >= ?",
            [$instanceId, $thisMonth . ' 00:00:00']
        );
        $totalEstimated = floatval($result[0]['total_estimated'] ?? 0);
        $totalActual = floatval($result[0]['total_actual'] ?? 0);

        // Average resolution time (closed workflows)
        $result = $this->db->rawQuery(
            "SELECT AVG(TIMESTAMPDIFF(DAY, created_at, updated_at)) as avg_days
             FROM damage_workflows
             WHERE instances_id = ? AND status = ?",
            [$instanceId, self::STATUS_CLOSED]
        );
        $avgResolutionDays = intval($result[0]['avg_days'] ?? 0);

        // Count by assigned user
        $result = $this->db->rawQuery(
            "SELECT assigned_to, COUNT(*) as count
             FROM damage_workflows
             WHERE instances_id = ? AND status != ? AND assigned_to IS NOT NULL
             GROUP BY assigned_to",
            [$instanceId, self::STATUS_CLOSED]
        );
        $assignedCounts = [];
        foreach ($result as $row) {
            $assignedCounts[$row['assigned_to']] = intval($row['count']);
        }

        return [
            'status_counts' => $statusCounts,
            'severity_counts' => $severityCounts,
            'open_count' => intval($openCount),
            'total_estimated_cost_this_month' => $totalEstimated,
            'total_actual_cost_this_month' => $totalActual,
            'avg_resolution_days' => $avgResolutionDays,
            'assigned_counts' => $assignedCounts,
        ];
    }

    /**
     * Get damage history for asset
     */
    public function getWorkflowsByAsset(int $assetId): array
    {
        $this->db->where('dr.assets_id', $assetId);
        $this->db->join('damage_reports dr', 'dw.damage_report_id = dr.id', 'LEFT');
        $this->db->orderBy('dw.created_at', 'DESC');

        return $this->db->get('damage_workflows dw', null, [
            'dw.*',
            'dr.description',
            'dr.projects_id',
        ]) ?: [];
    }

    /**
     * Get damage history for client/company
     */
    public function getWorkflowsByClient(int $clientId): array
    {
        $this->db->join('damage_reports dr', 'dw.damage_report_id = dr.id', 'LEFT');
        $this->db->join('assets a', 'dr.assets_id = a.assets_id', 'LEFT');
        $this->db->join('projects p', 'dr.projects_id = p.projects_id', 'LEFT');
        $this->db->where('p.companies_id', $clientId);
        $this->db->orderBy('dw.created_at', 'DESC');

        return $this->db->get('damage_workflows dw', null, [
            'dw.*',
            'dr.description',
            'a.assets_tag',
        ]) ?: [];
    }

    /**
     * Update actual costs when repair is completed
     */
    public function setActualCost(int $workflowId, float $actualCost): bool
    {
        $this->db->where('id', $workflowId);
        return $this->db->update('damage_workflows', [
            'actual_cost' => $actualCost,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Assign workflow to user
     */
    public function assignTo(int $workflowId, int $userId): bool
    {
        $this->db->where('id', $workflowId);
        return $this->db->update('damage_workflows', [
            'assigned_to' => $userId,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Update vendor and contact information
     */
    public function setRepairVendor(int $workflowId, string $vendorName, ?string $contact): bool
    {
        $this->db->where('id', $workflowId);
        return $this->db->update('damage_workflows', [
            'repair_vendor' => $vendorName,
            'repair_vendor_contact' => $contact,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Add notes to workflow
     */
    public function addNotes(int $workflowId, string $notes): bool
    {
        $this->db->where('id', $workflowId);
        $workflow = $this->db->getOne('damage_workflows', null, ['notes']);

        $combinedNotes = ($workflow['notes'] ?? '') . "\n---\n[" . date('Y-m-d H:i:s') . "]: " . $notes;

        $this->db->where('id', $workflowId);
        return $this->db->update('damage_workflows', [
            'notes' => $combinedNotes,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
