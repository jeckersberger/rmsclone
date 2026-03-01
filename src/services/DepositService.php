<?php
/**
 * Kautionsverwaltung
 *
 * Verwaltet Kautionen fuer Equipment-Verleih:
 * - Kaution pro Projekt berechnen (basierend auf Equipment-Wert)
 * - Einzahlung tracken
 * - Automatische Erstattung nach Rueckgabe (wenn kein Schaden)
 *
 * Tabellen:
 *   deposits  - Kautionsbuchungen
 */
class DepositService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Calculate recommended deposit for a project
     */
    public function calculateDeposit(int $projectId, float $percentOfValue = 20.0): array
    {
        $sql = "SELECT SUM(COALESCE(at.assetTypes_value, 0)) as total_value
                FROM assetsAssignments aa
                JOIN assets a ON aa.assets_id = a.assets_id
                JOIN assetTypes at ON a.assetTypes_id = at.assetTypes_id
                WHERE aa.projects_id = ? AND aa.assetsAssignments_deleted = 0 AND a.assets_deleted = 0";
        $result = $this->db->rawQuery($sql, [$projectId]);
        $totalValue = ($result && isset($result[0])) ? (float)$result[0]['total_value'] : 0;

        $recommended = round($totalValue * $percentOfValue / 100, 2);

        return [
            'equipment_value' => $totalValue,
            'deposit_percent' => $percentOfValue,
            'recommended_deposit' => $recommended,
        ];
    }

    /**
     * Record a deposit payment
     */
    public function recordDeposit(int $instanceId, int $projectId, float $amount, string $method, int $userId, ?string $reference = null): int
    {
        return $this->db->insert('deposits', [
            'instances_id' => $instanceId,
            'projects_id' => $projectId,
            'amount' => $amount,
            'type' => 'received',
            'payment_method' => $method,
            'reference' => $reference,
            'status' => 'held',
            'recorded_by' => $userId,
        ]);
    }

    /**
     * Refund a deposit
     */
    public function refundDeposit(int $depositId, float $refundAmount, int $userId, ?string $deductionReason = null): bool
    {
        $this->db->where('id', $depositId);
        $deposit = $this->db->getOne('deposits');
        if (!$deposit || $deposit['status'] !== 'held') return false;

        $deduction = $deposit['amount'] - $refundAmount;

        $this->db->where('id', $depositId);
        $this->db->update('deposits', [
            'status' => $deduction > 0 ? 'partially_refunded' : 'refunded',
            'refunded_amount' => $refundAmount,
            'deduction_amount' => $deduction,
            'deduction_reason' => $deductionReason,
            'refunded_at' => date('Y-m-d H:i:s'),
            'refunded_by' => $userId,
        ]);

        return true;
    }

    /**
     * Get deposits for a project
     */
    public function getProjectDeposits(int $projectId): array
    {
        $this->db->where('projects_id', $projectId);
        $this->db->where('deleted', 0);
        $this->db->orderBy('created_at', 'DESC');
        return $this->db->get('deposits') ?: [];
    }

    /**
     * Get deposit summary for a project
     */
    public function getProjectDepositSummary(int $projectId): array
    {
        $deposits = $this->getProjectDeposits($projectId);
        $totalHeld = 0;
        $totalRefunded = 0;
        $totalDeducted = 0;

        foreach ($deposits as $d) {
            if ($d['status'] === 'held') $totalHeld += $d['amount'];
            $totalRefunded += $d['refunded_amount'] ?? 0;
            $totalDeducted += $d['deduction_amount'] ?? 0;
        }

        return [
            'deposits' => $deposits,
            'total_held' => $totalHeld,
            'total_refunded' => $totalRefunded,
            'total_deducted' => $totalDeducted,
        ];
    }
}
