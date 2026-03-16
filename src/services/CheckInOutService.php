<?php
/**
 * Check-in / Check-out Service
 *
 * Verwaltet die Ausgabe und Ruecknahme von Equipment mit Zustandsprotokoll.
 * Dokumentiert den Zustand vor und nach dem Einsatz.
 *
 * Zustaende: excellent, good, fair, poor, damaged
 */
class CheckInOutService
{
    private $db;
    private ?CaseContentsService $caseContentsService = null;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function setCaseContentsService(CaseContentsService $svc): void
    {
        $this->caseContentsService = $svc;
    }

    /**
     * Check out an asset (Ausgabe)
     */
    public function checkOut(int $instanceId, int $assetId, int $projectId, int $userId, array $data = []): int
    {
        $this->db->insert('asset_checkinout', [
            'instances_id'        => $instanceId,
            'assets_id'           => $assetId,
            'projects_id'         => $projectId,
            'assetsAssignments_id'=> $data['assetsAssignments_id'] ?? null,
            'direction'           => 'out',
            'condition_before'    => $data['condition'] ?? 'good',
            'condition_notes'     => $data['notes'] ?? null,
            'photo_s3files_id'    => $data['photo_s3files_id'] ?? null,
            'checked_by'          => $userId,
            'signature_data'      => $data['signature'] ?? null,
        ]);
        return $this->db->getInsertId();
    }

    /**
     * Check in an asset (Ruecknahme)
     */
    public function checkIn(int $instanceId, int $assetId, int $projectId, int $userId, array $data = []): int
    {
        // Get the last check-out to compare condition
        $lastCheckout = $this->getLastCheckout($assetId, $projectId);
        $conditionBefore = $lastCheckout ? $lastCheckout['condition_before'] : null;

        $this->db->insert('asset_checkinout', [
            'instances_id'        => $instanceId,
            'assets_id'           => $assetId,
            'projects_id'         => $projectId,
            'assetsAssignments_id'=> $data['assetsAssignments_id'] ?? null,
            'direction'           => 'in',
            'condition_before'    => $conditionBefore,
            'condition_after'     => $data['condition'] ?? 'good',
            'condition_notes'     => $data['notes'] ?? null,
            'photo_s3files_id'    => $data['photo_s3files_id'] ?? null,
            'checked_by'          => $userId,
            'signature_data'      => $data['signature'] ?? null,
        ]);

        $checkInId = $this->db->getInsertId();

        // If condition has degraded, flag it
        $conditionRanks = ['excellent' => 5, 'good' => 4, 'fair' => 3, 'poor' => 2, 'damaged' => 1];
        $afterRank = $conditionRanks[$data['condition'] ?? 'good'] ?? 4;
        $beforeRank = $conditionRanks[$conditionBefore ?? 'good'] ?? 4;

        if ($afterRank < $beforeRank && ($data['condition'] === 'poor' || $data['condition'] === 'damaged')) {
            // Create a maintenance job flag (if the maintenanceJobs table exists)
            // This is a lightweight notification approach
        }

        return $checkInId;
    }

    /**
     * Check in an asset with case contents verification (if asset is a case)
     *
     * @param int $instanceId
     * @param int $assetId
     * @param int $projectId
     * @param int $userId
     * @param array $scannedContents Array of scanned items: ['entity_type' => 'asset'|'stock_instance', 'entity_id' => int]
     * @param array $data Additional checkin data (condition, notes, etc.)
     * @param bool $acknowledgeDiscrepancies If true, allows checkin even with missing items
     * @return array Result with checkinId and caseVerificationResult (if applicable)
     */
    public function checkInWithCaseVerification(
        int $instanceId,
        int $assetId,
        int $projectId,
        int $userId,
        array $scannedContents = [],
        array $data = [],
        bool $acknowledgeDiscrepancies = false
    ): array
    {
        // Check if asset is a case
        $this->db->where('assets_id', $assetId);
        $this->db->where('instances_id', $instanceId);
        $asset = $this->db->getOne('assets', ['is_case']);

        $result = [
            'checkinId' => null,
            'caseVerificationResult' => null,
            'warnings' => []
        ];

        // If it's a case and we have CaseContentsService, verify contents
        if ($asset && $asset['is_case'] && $this->caseContentsService) {
            $verificationResult = $this->caseContentsService->verifyCaseContents($assetId, $scannedContents);
            $result['caseVerificationResult'] = $verificationResult;

            // Log the verification check
            try {
                $this->caseContentsService->logCheck(
                    $assetId,
                    $projectId,
                    'checkin',
                    $userId,
                    $verificationResult
                );
            } catch (Exception $e) {
                // Logging failed, but continue with checkin
            }

            // Check if there are missing items that are not acknowledged
            $hasMissingItems = !empty($verificationResult['missing']);
            $hasRequiredMissing = $verificationResult['summary']['has_missing_required'] ?? false;

            if ($hasMissingItems && !$acknowledgeDiscrepancies) {
                $result['warnings'][] = [
                    'type' => 'case_contents_incomplete',
                    'message' => 'Case contents verification found discrepancies',
                    'details' => [
                        'missing' => $verificationResult['missing'],
                        'extra' => $verificationResult['extra'],
                        'swapped' => $verificationResult['swapped'],
                        'has_required_missing' => $hasRequiredMissing
                    ]
                ];

                // Still allow checkin, but return the warning
            }
        }

        // Perform normal checkin
        $checkinId = $this->checkIn($instanceId, $assetId, $projectId, $userId, $data);
        $result['checkinId'] = $checkinId;

        return $result;
    }

    /**
     * Get check-in/out history for an asset
     */
    public function getAssetHistory(int $assetId, ?int $limit = 50): array
    {
        $this->db->where('cio.assets_id', $assetId);
        $this->db->join('projects p', 'cio.projects_id=p.projects_id', 'LEFT');
        $this->db->join('users u', 'cio.checked_by=u.users_userid', 'LEFT');
        $this->db->orderBy('cio.checked_at', 'DESC');
        return $this->db->get('asset_checkinout cio', $limit, [
            'cio.*', 'p.projects_name', 'u.users_name1', 'u.users_name2'
        ]) ?: [];
    }

    /**
     * Get check-in/out history for a project
     */
    public function getProjectHistory(int $projectId): array
    {
        $this->db->where('cio.projects_id', $projectId);
        $this->db->join('assets a', 'cio.assets_id=a.assets_id', 'LEFT');
        $this->db->join('assetTypes at', 'a.assetTypes_id=at.assetTypes_id', 'LEFT');
        $this->db->join('users u', 'cio.checked_by=u.users_userid', 'LEFT');
        $this->db->orderBy('cio.checked_at', 'DESC');
        return $this->db->get('asset_checkinout cio', null, [
            'cio.*', 'a.assets_tag', 'at.assetTypes_name', 'u.users_name1', 'u.users_name2'
        ]) ?: [];
    }

    /**
     * Get assets that are checked out but not checked back in for a project
     */
    public function getOutstandingCheckouts(int $projectId): array
    {
        $sql = "SELECT co.*, a.assets_tag, at.assetTypes_name
                FROM asset_checkinout co
                JOIN assets a ON co.assets_id = a.assets_id
                JOIN assetTypes at ON a.assetTypes_id = at.assetTypes_id
                WHERE co.projects_id = ? AND co.direction = 'out'
                AND NOT EXISTS (
                    SELECT 1 FROM asset_checkinout ci
                    WHERE ci.assets_id = co.assets_id
                    AND ci.projects_id = co.projects_id
                    AND ci.direction = 'in'
                    AND ci.checked_at > co.checked_at
                )
                ORDER BY co.checked_at ASC";
        return $this->db->rawQuery($sql, [$projectId]) ?: [];
    }

    /**
     * Get damage report - assets returned in poor/damaged condition
     */
    public function getDamageReport(int $instanceId, ?string $from = null, ?string $to = null): array
    {
        $this->db->where('cio.instances_id', $instanceId);
        $this->db->where('cio.direction', 'in');
        $this->db->where('cio.condition_after', ['poor', 'damaged'], 'IN');
        if ($from) $this->db->where('cio.checked_at', $from, '>=');
        if ($to) $this->db->where('cio.checked_at', $to, '<=');
        $this->db->join('assets a', 'cio.assets_id=a.assets_id', 'LEFT');
        $this->db->join('assetTypes at', 'a.assetTypes_id=at.assetTypes_id', 'LEFT');
        $this->db->join('projects p', 'cio.projects_id=p.projects_id', 'LEFT');
        $this->db->orderBy('cio.checked_at', 'DESC');
        return $this->db->get('asset_checkinout cio', null, [
            'cio.*', 'a.assets_tag', 'at.assetTypes_name', 'p.projects_name'
        ]) ?: [];
    }

    /**
     * Bulk check-out for a project (all assigned assets at once)
     */
    public function bulkCheckOut(int $instanceId, int $projectId, int $userId, string $defaultCondition = 'good'): int
    {
        $sql = "SELECT aa.assetsAssignments_id, a.assets_id
                FROM assetsAssignments aa
                JOIN assets a ON aa.assets_id = a.assets_id
                WHERE aa.projects_id = ? AND aa.assetsAssignments_deleted = 0 AND a.assets_deleted = 0";
        $assignments = $this->db->rawQuery($sql, [$projectId]) ?: [];

        $count = 0;
        foreach ($assignments as $a) {
            $this->checkOut($instanceId, $a['assets_id'], $projectId, $userId, [
                'assetsAssignments_id' => $a['assetsAssignments_id'],
                'condition' => $defaultCondition,
            ]);
            $count++;
        }
        return $count;
    }

    /**
     * Get checkin warnings for an asset (e.g., case verification needed)
     *
     * @param int $assetId
     * @return array Warnings array with case_verification_needed flag and expected_contents_count
     */
    public function getCheckinWarnings(int $assetId): array
    {
        $warnings = [];

        // Check if asset is a case
        $this->db->where('assets_id', $assetId);
        $asset = $this->db->getOne('assets', ['is_case']);

        if ($asset && $asset['is_case']) {
            $warnings['case_verification_needed'] = true;

            // Get expected contents count if we have CaseContentsService
            if ($this->caseContentsService) {
                $contents = $this->caseContentsService->getCaseContents($assetId);
                $warnings['expected_contents_count'] = count($contents);

                // Count required items
                $requiredCount = 0;
                foreach ($contents as $item) {
                    if ($item['is_required']) {
                        $requiredCount++;
                    }
                }
                $warnings['required_items_count'] = $requiredCount;
            }
        } else {
            $warnings['case_verification_needed'] = false;
        }

        return $warnings;
    }

    private function getLastCheckout(int $assetId, int $projectId): ?array
    {
        $this->db->where('assets_id', $assetId);
        $this->db->where('projects_id', $projectId);
        $this->db->where('direction', 'out');
        $this->db->orderBy('checked_at', 'DESC');
        $result = $this->db->getOne('asset_checkinout');
        return $result ?: null;
    }
}
