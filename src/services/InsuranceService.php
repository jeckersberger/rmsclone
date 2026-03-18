<?php
/**
 * InsuranceService - Versicherungsmanagement (K2)
 *
 * Verwaltet Versicherungspolicen, Deckung von Assets und Kundennachweise,
 * sowie die Verarbeitung von Schadensmeldinungen und Versicherungsansprüchen.
 */
class InsuranceService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get all policies for an instance, optionally filtered to active only
     */
    public function getPolicies(int $instanceId, ?bool $activeOnly = true): array
    {
        $this->db->where('instances_id', $instanceId);

        if ($activeOnly === true) {
            $this->db->where('is_active', true);
        }

        $this->db->orderBy('valid_until', 'DESC');
        return $this->db->get('insurance_policies', null, ['*']) ?: [];
    }

    /**
     * Get single policy with covered assets information
     */
    public function getPolicy(int $id): ?array
    {
        $this->db->where('id', $id);
        $policy = $this->db->getOne('insurance_policies', null, ['*']);

        if (!$policy) {
            return null;
        }

        // Load covered assets
        $this->db->where('policy_id', $id);
        $coverage = $this->db->get('insurance_asset_coverage', null, ['*']) ?: [];

        $policy['covered_assets'] = [];
        $policy['covered_asset_types'] = [];
        $policy['covered_stock_items'] = [];

        foreach ($coverage as $cov) {
            if ($cov['asset_id']) {
                $policy['covered_assets'][] = $cov['asset_id'];
            }
            if ($cov['asset_type_id']) {
                $policy['covered_asset_types'][] = $cov['asset_type_id'];
            }
            if ($cov['stock_item_id']) {
                $policy['covered_stock_items'][] = $cov['stock_item_id'];
            }
        }

        return $policy;
    }

    /**
     * Create a new insurance policy
     */
    public function createPolicy(array $data): int
    {
        $policyId = $this->db->insert('insurance_policies', [
            'instances_id' => $data['instances_id'],
            'name' => $data['name'],
            'provider' => $data['provider'],
            'policy_number' => $data['policy_number'],
            'coverage_type' => $data['coverage_type'],
            'coverage_amount' => floatval($data['coverage_amount']),
            'deductible' => isset($data['deductible']) ? floatval($data['deductible']) : null,
            'premium_monthly' => floatval($data['premium_monthly']),
            'valid_from' => $data['valid_from'],
            'valid_until' => $data['valid_until'],
            'document_path' => $data['document_path'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $policyId ?: 0;
    }

    /**
     * Update an existing policy
     */
    public function updatePolicy(int $id, array $data): bool
    {
        $updateData = [];

        if (isset($data['name'])) $updateData['name'] = $data['name'];
        if (isset($data['provider'])) $updateData['provider'] = $data['provider'];
        if (isset($data['policy_number'])) $updateData['policy_number'] = $data['policy_number'];
        if (isset($data['coverage_type'])) $updateData['coverage_type'] = $data['coverage_type'];
        if (isset($data['coverage_amount'])) $updateData['coverage_amount'] = floatval($data['coverage_amount']);
        if (isset($data['deductible'])) $updateData['deductible'] = $data['deductible'] ? floatval($data['deductible']) : null;
        if (isset($data['premium_monthly'])) $updateData['premium_monthly'] = floatval($data['premium_monthly']);
        if (isset($data['valid_from'])) $updateData['valid_from'] = $data['valid_from'];
        if (isset($data['valid_until'])) $updateData['valid_until'] = $data['valid_until'];
        if (isset($data['document_path'])) $updateData['document_path'] = $data['document_path'];
        if (isset($data['notes'])) $updateData['notes'] = $data['notes'];
        if (isset($data['is_active'])) $updateData['is_active'] = $data['is_active'];

        if (empty($updateData)) {
            return false;
        }

        $updateData['updated_at'] = date('Y-m-d H:i:s');

        $this->db->where('id', $id);
        return $this->db->update('insurance_policies', $updateData);
    }

    /**
     * Delete a policy (soft or hard delete)
     */
    public function deletePolicy(int $id): bool
    {
        // Remove coverage associations first
        $this->db->where('policy_id', $id);
        $this->db->delete('insurance_asset_coverage');

        // Delete policy
        $this->db->where('id', $id);
        return $this->db->delete('insurance_policies');
    }

    /**
     * Add asset coverage to a policy
     * entityType: 'asset', 'asset_type', or 'stock_item'
     */
    public function addAssetCoverage(int $policyId, string $entityType, int $entityId): bool
    {
        // Check if coverage already exists
        $this->db->where('policy_id', $policyId);
        if ($entityType === 'asset') {
            $this->db->where('asset_id', $entityId);
        } elseif ($entityType === 'asset_type') {
            $this->db->where('asset_type_id', $entityId);
        } elseif ($entityType === 'stock_item') {
            $this->db->where('stock_item_id', $entityId);
        } else {
            return false;
        }

        $existing = $this->db->getOne('insurance_asset_coverage', null, ['id']);
        if ($existing) {
            return true; // Already exists
        }

        // Insert new coverage
        $data = [
            'policy_id' => $policyId,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        if ($entityType === 'asset') {
            $data['asset_id'] = $entityId;
        } elseif ($entityType === 'asset_type') {
            $data['asset_type_id'] = $entityId;
        } elseif ($entityType === 'stock_item') {
            $data['stock_item_id'] = $entityId;
        }

        $result = $this->db->insert('insurance_asset_coverage', $data);
        return $result ? true : false;
    }

    /**
     * Remove asset coverage from a policy
     */
    public function removeAssetCoverage(int $policyId, string $entityType, int $entityId): bool
    {
        $this->db->where('policy_id', $policyId);

        if ($entityType === 'asset') {
            $this->db->where('asset_id', $entityId);
        } elseif ($entityType === 'asset_type') {
            $this->db->where('asset_type_id', $entityId);
        } elseif ($entityType === 'stock_item') {
            $this->db->where('stock_item_id', $entityId);
        } else {
            return false;
        }

        return $this->db->delete('insurance_asset_coverage');
    }

    /**
     * Check which policies cover a specific asset
     */
    public function checkCoverage(int $assetId, int $instanceId): array
    {
        // Get asset details for type checking
        $this->db->where('assets_id', $assetId);
        $asset = $this->db->getOne('assets', null, ['assetTypes_id']);

        if (!$asset) {
            return [];
        }

        // Find policies covering this asset (directly or by type)
        $this->db->where('ip.instances_id', $instanceId);
        $this->db->where('ip.is_active', true);
        $this->db->where('ip.valid_until', date('Y-m-d'), '>=');

        $this->db->where([
            'iac.asset_id' => $assetId,
            'iac.asset_type_id' => $asset['assetTypes_id'],
        ], null, 'OR');

        $this->db->join('insurance_asset_coverage iac', 'ip.id = iac.policy_id', 'LEFT');
        $this->db->groupBy('ip.id');

        return $this->db->get('insurance_policies ip', null, ['ip.*']) ?: [];
    }

    /**
     * Get all assets without any insurance coverage
     */
    public function getUncoveredAssets(int $instanceId): array
    {
        // Get all active assets in the instance
        $this->db->where('instances_id', $instanceId);
        $this->db->orderBy('assets_tag', 'ASC');
        $allAssets = $this->db->get('assets', null, ['assets_id', 'assets_tag', 'assetTypes_id']) ?: [];

        $uncovered = [];
        foreach ($allAssets as $asset) {
            $policies = $this->checkCoverage($asset['assets_id'], $instanceId);
            if (empty($policies)) {
                $uncovered[] = $asset;
            }
        }

        return $uncovered;
    }

    /**
     * Get policies expiring within X days
     */
    public function getExpiringPolicies(int $instanceId, int $daysAhead = 30): array
    {
        $cutoffDate = date('Y-m-d', strtotime("+{$daysAhead} days"));
        $today = date('Y-m-d');

        $this->db->where('instances_id', $instanceId);
        $this->db->where('is_active', true);
        $this->db->where('valid_until', $today, '>=');
        $this->db->where('valid_until', $cutoffDate, '<=');
        $this->db->orderBy('valid_until', 'ASC');

        return $this->db->get('insurance_policies', null, ['*']) ?: [];
    }

    /**
     * Get coverage status for an asset
     * Returns: 'covered', 'uncovered', 'expiring'
     */
    public function getCoverageStatus(int $assetId): string
    {
        $this->db->where('assets_id', $assetId);
        $asset = $this->db->getOne('assets', null, ['assetTypes_id']);

        if (!$asset) {
            return 'uncovered';
        }

        $today = date('Y-m-d');

        // Check for active coverage
        $this->db->where('iac.asset_id', $assetId);
        $this->db->where('ip.is_active', true);
        $this->db->where('ip.valid_until', $today, '>=');
        $this->db->join('insurance_policies ip', 'iac.policy_id = ip.id', 'INNER');
        $activeCoverage = $this->db->getOne('insurance_asset_coverage iac', null, ['iac.id']);

        if ($activeCoverage) {
            // Check if expiring within 30 days
            $cutoff = date('Y-m-d', strtotime('+30 days'));
            $this->db->where('iac.asset_id', $assetId);
            $this->db->where('ip.is_active', true);
            $this->db->where('ip.valid_until', $today, '>=');
            $this->db->where('ip.valid_until', $cutoff, '<=');
            $this->db->join('insurance_policies ip', 'iac.policy_id = ip.id', 'INNER');
            $expiringCoverage = $this->db->getOne('insurance_asset_coverage iac', null, ['iac.id']);

            return $expiringCoverage ? 'expiring' : 'covered';
        }

        return 'uncovered';
    }

    /**
     * Get client insurance certificates (liability, property, event)
     */
    public function getClientCertificates(int $clientId, int $instanceId): array
    {
        $this->db->where('client_id', $clientId);
        $this->db->where('instances_id', $instanceId);
        $this->db->orderBy('valid_until', 'DESC');

        return $this->db->get('insurance_client_certificates', null, ['*']) ?: [];
    }

    /**
     * Upload a client insurance certificate
     */
    public function uploadClientCertificate(int $clientId, array $data): int
    {
        $certId = $this->db->insert('insurance_client_certificates', [
            'instances_id' => $data['instances_id'],
            'client_id' => $clientId,
            'certificate_type' => $data['certificate_type'],
            'file_path' => $data['file_path'],
            'valid_until' => $data['valid_until'],
            'verified' => false,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $certId ?: 0;
    }

    /**
     * Verify a client certificate
     */
    public function verifyCertificate(int $certId, int $userId): bool
    {
        $this->db->where('id', $certId);
        return $this->db->update('insurance_client_certificates', [
            'verified' => true,
            'verified_by' => $userId,
            'verified_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get claims for an instance, optionally filtered by status
     */
    public function getClaims(int $instanceId, ?string $status = null): array
    {
        $this->db->where('instances_id', $instanceId);

        if ($status !== null) {
            $this->db->where('status', $status);
        }

        $this->db->join('insurance_policies ip', 'ic.policy_id = ip.id', 'LEFT');
        $this->db->orderBy('ic.created_at', 'DESC');

        return $this->db->get('insurance_claims ic', null, [
            'ic.*',
            'ip.name as policy_name',
            'ip.provider',
        ]) ?: [];
    }

    /**
     * Create a new insurance claim
     */
    public function createClaim(array $data): int
    {
        $claimId = $this->db->insert('insurance_claims', [
            'instances_id' => $data['instances_id'],
            'policy_id' => $data['policy_id'],
            'damage_workflow_id' => $data['damage_workflow_id'] ?? null,
            'claim_number' => $data['claim_number'],
            'claim_date' => $data['claim_date'] ?? date('Y-m-d'),
            'description' => $data['description'] ?? null,
            'claimed_amount' => floatval($data['claimed_amount']),
            'status' => 'draft',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $claimId ?: 0;
    }

    /**
     * Update claim status and approved amount
     */
    public function updateClaimStatus(int $claimId, string $status, ?float $approvedAmount = null): bool
    {
        $validStatuses = ['draft', 'submitted', 'under_review', 'approved', 'partially_approved', 'rejected', 'paid', 'closed'];

        if (!in_array($status, $validStatuses)) {
            return false;
        }

        $updateData = [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($approvedAmount !== null) {
            $updateData['approved_amount'] = floatval($approvedAmount);
        }

        if ($status === 'paid') {
            $updateData['payout_date'] = date('Y-m-d');
        }

        $this->db->where('id', $claimId);
        return $this->db->update('insurance_claims', $updateData);
    }

    /**
     * Get dashboard statistics for insurance management
     */
    public function getDashboardStats(int $instanceId): array
    {
        // Total coverage amount
        $this->db->where('instances_id', $instanceId);
        $this->db->where('is_active', true);
        $result = $this->db->rawQuery(
            "SELECT SUM(coverage_amount) as total_coverage
             FROM insurance_policies
             WHERE instances_id = ? AND is_active = true",
            [$instanceId]
        );
        $totalCoverage = floatval($result[0]['total_coverage'] ?? 0);

        // Total monthly premium
        $monthlyPremium = $this->calculateMonthlyPremium($instanceId);

        // Open claims count
        $this->db->where('instances_id', $instanceId);
        $this->db->where('status', ['submitted', 'under_review', 'approved', 'partially_approved'], 'IN');
        $openClaimsCount = $this->db->getValue('insurance_claims', 'COUNT(*)');

        // Uncovered assets count
        $uncoveredAssets = $this->getUncoveredAssets($instanceId);
        $uncoveredCount = count($uncoveredAssets);

        // Policies expiring within 30 days
        $expiringPolicies = $this->getExpiringPolicies($instanceId, 30);
        $expiringCount = count($expiringPolicies);

        // Total claimed amount (this year)
        $thisYear = date('Y-01-01');
        $result = $this->db->rawQuery(
            "SELECT SUM(claimed_amount) as total_claimed, SUM(approved_amount) as total_approved
             FROM insurance_claims
             WHERE instances_id = ? AND claim_date >= ?",
            [$instanceId, $thisYear]
        );
        $totalClaimedThisYear = floatval($result[0]['total_claimed'] ?? 0);
        $totalApprovedThisYear = floatval($result[0]['total_approved'] ?? 0);

        // Policy count by coverage type
        $result = $this->db->rawQuery(
            "SELECT coverage_type, COUNT(*) as count
             FROM insurance_policies
             WHERE instances_id = ? AND is_active = true
             GROUP BY coverage_type",
            [$instanceId]
        );
        $coverageByType = [];
        foreach ($result as $row) {
            $coverageByType[$row['coverage_type']] = intval($row['count']);
        }

        return [
            'total_coverage_amount' => $totalCoverage,
            'monthly_premium' => $monthlyPremium,
            'open_claims_count' => intval($openClaimsCount),
            'uncovered_assets_count' => $uncoveredCount,
            'expiring_policies_count' => $expiringCount,
            'total_claimed_this_year' => $totalClaimedThisYear,
            'total_approved_this_year' => $totalApprovedThisYear,
            'policies_by_coverage_type' => $coverageByType,
        ];
    }

    /**
     * Calculate total monthly premium for an instance
     */
    public function calculateMonthlyPremium(int $instanceId): float
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('is_active', true);
        $result = $this->db->rawQuery(
            "SELECT SUM(premium_monthly) as total
             FROM insurance_policies
             WHERE instances_id = ? AND is_active = true",
            [$instanceId]
        );

        return floatval($result[0]['total'] ?? 0);
    }
}
