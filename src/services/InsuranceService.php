<?php
/**
 * Versicherungsnachweis-Verwaltung
 *
 * Kunden muessen vor Equipment-Ausgabe eine Versicherung nachweisen.
 * - Upload-Pflicht vor Check-out
 * - Ablaufdatum-Tracking
 * - Automatische Warnung bei abgelaufener Versicherung
 *
 * Tabellen:
 *   client_insurance  - Versicherungsnachweise pro Kunde
 */
class InsuranceService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Add insurance proof for a client
     */
    public function addProof(int $clientId, array $data, int $userId): int
    {
        return $this->db->insert('client_insurance', [
            'clients_id' => $clientId,
            'insurance_type' => $data['type'] ?? 'liability', // liability, equipment, all_risk
            'provider' => $data['provider'],
            'policy_number' => $data['policy_number'] ?? null,
            'coverage_amount' => $data['coverage_amount'] ?? null,
            'valid_from' => $data['valid_from'],
            'valid_until' => $data['valid_until'],
            's3files_id' => $data['s3files_id'] ?? null, // uploaded document
            'verified' => 0,
            'uploaded_by' => $userId,
        ]);
    }

    /**
     * Check if a client has valid insurance
     */
    public function hasValidInsurance(int $clientId, string $forDate = null): array
    {
        $checkDate = $forDate ?: date('Y-m-d');
        $this->db->where('clients_id', $clientId);
        $this->db->where('deleted', 0);
        $this->db->where('valid_from', $checkDate, '<=');
        $this->db->where('valid_until', $checkDate, '>=');
        $proofs = $this->db->get('client_insurance') ?: [];

        return [
            'valid' => count($proofs) > 0,
            'proofs' => $proofs,
            'verified' => count(array_filter($proofs, fn($p) => $p['verified'])) > 0,
        ];
    }

    /**
     * Get insurance proofs for a client
     */
    public function getClientInsurance(int $clientId): array
    {
        $this->db->where('clients_id', $clientId);
        $this->db->where('deleted', 0);
        $this->db->orderBy('valid_until', 'DESC');
        return $this->db->get('client_insurance') ?: [];
    }

    /**
     * Get clients with expiring insurance (warning)
     */
    public function getExpiringInsurance(int $instanceId, int $warningDays = 30): array
    {
        $warningDate = date('Y-m-d', strtotime("+{$warningDays} days"));
        $sql = "SELECT ci.*, c.clients_name, c.clients_email
                FROM client_insurance ci
                JOIN clients c ON ci.clients_id = c.clients_id
                WHERE c.instances_id = ? AND ci.deleted = 0
                AND ci.valid_until BETWEEN CURDATE() AND ?
                ORDER BY ci.valid_until ASC";
        return $this->db->rawQuery($sql, [$instanceId, $warningDate]) ?: [];
    }

    /**
     * Verify insurance proof
     */
    public function verify(int $proofId, int $userId): bool
    {
        $this->db->where('id', $proofId);
        return $this->db->update('client_insurance', [
            'verified' => 1, 'verified_by' => $userId, 'verified_at' => date('Y-m-d H:i:s')
        ]);
    }
}
