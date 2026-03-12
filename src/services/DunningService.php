<?php
/**
 * Mahnwesen - Zahlungserinnerungen und Mahnstufen
 *
 * Stufe 0: Zahlungserinnerung (freundlich, nach 7 Tagen)
 * Stufe 1: 1. Mahnung (nach 21 Tagen)
 * Stufe 2: 2. Mahnung + Mahngebuehr (nach 35 Tagen)
 * Stufe 3: Letzte Mahnung + Verzugszinsen (nach 49 Tagen)
 */
class DunningService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get all overdue invoices with their current dunning status
     */
    public function getOverdueInvoices(int $instanceId): array
    {
        $this->db->where('dl.instances_id', $instanceId);
        $this->db->where('dl.doc_type', 'invoice');
        $this->db->where('dl.status', ['sent', 'overdue', 'reminded'], 'IN');
        $this->db->where('dl.due_date', date('Y-m-d'), '<');
        $this->db->join('projects p', 'dl.projects_id=p.projects_id', 'LEFT');
        $this->db->join('clients c', 'p.clients_id=c.clients_id', 'LEFT');
        $this->db->join('document_exports de', 'dl.document_exports_id=de.document_exports_id', 'LEFT');
        $this->db->orderBy('dl.due_date', 'ASC');
        $invoices = $this->db->get('document_lifecycle dl', null, [
            'dl.*', 'p.projects_name', 'c.clients_name', 'c.clients_email',
            'de.document_exports_paidAmount', 'de.document_exports_paymentStatus'
        ]) ?: [];

        foreach ($invoices as &$inv) {
            $inv['days_overdue'] = (int)((time() - strtotime($inv['due_date'])) / 86400);
            $inv['last_dunning'] = $this->getLastDunning($inv['id']);
            $inv['next_dunning_level'] = $this->getNextDunningLevel($instanceId, $inv);
        }
        unset($inv);

        return $invoices;
    }

    /**
     * Get dunning levels for an instance
     */
    public function getDunningLevels(int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->orderBy('level', 'ASC');
        return $this->db->get('dunning_levels') ?: [];
    }

    /**
     * Create a dunning entry and optionally generate PDF
     */
    public function createDunning(int $instanceId, int $docLifecycleId, int $userId): ?array
    {
        $this->db->where('id', $docLifecycleId);
        $this->db->where('instances_id', $instanceId);
        $invoice = $this->db->getOne('document_lifecycle');
        if (!$invoice) return null;

        // Mahnsperre pruefen: pausiertes Mahnverfahren nicht fortsetzen
        $lastDunning = $this->getLastDunning($docLifecycleId);
        if ($lastDunning && !empty($lastDunning['dunning_history_paused'])) {
            return null; // Mahnverfahren pausiert
        }

        $daysOverdue = (int)((time() - strtotime($invoice['due_date'])) / 86400);
        $nextLevel = $this->getNextDunningLevel($instanceId, $invoice);
        if (!$nextLevel) return null;

        // Calculate fee and interest
        $fee = (float)$nextLevel['fee'];
        $interestRate = (float)$nextLevel['interest_rate'];
        $interestAmount = 0;
        if ($interestRate > 0) {
            // Verzugszinsen: Rechnungsbetrag * Zinssatz / 365 * ueberfaellige Tage
            $interestAmount = round((float)$invoice['gross_amount'] * ($interestRate / 100) / 365 * $daysOverdue, 2);
        }
        $totalDue = (float)$invoice['gross_amount'] + $fee + $interestAmount;

        $this->db->insert('dunning_history', [
            'instances_id'          => $instanceId,
            'document_lifecycle_id' => $docLifecycleId,
            'dunning_level_id'      => $nextLevel['id'],
            'dunning_level'         => $nextLevel['level'],
            'dunning_date'          => date('Y-m-d'),
            'fee_amount'            => $fee,
            'interest_amount'       => $interestAmount,
            'total_due'             => $totalDue,
            'created_by'            => $userId,
        ]);

        $dunningId = $this->db->getInsertId();

        // Update invoice status
        $lifecycle = new DocumentLifecycleService($this->db);
        $lifecycle->changeStatus($docLifecycleId, 'reminded', $userId,
            $nextLevel['name'] . " erstellt (Mahnstufe " . $nextLevel['level'] . ")");

        return [
            'dunning_id'      => $dunningId,
            'level'           => $nextLevel['level'],
            'level_name'      => $nextLevel['name'],
            'fee'             => $fee,
            'interest'        => $interestAmount,
            'total_due'       => $totalDue,
            'days_overdue'    => $daysOverdue,
        ];
    }

    /**
     * Get dunning history for an invoice
     */
    public function getDunningHistory(int $docLifecycleId): array
    {
        $this->db->where('dh.document_lifecycle_id', $docLifecycleId);
        $this->db->join('dunning_levels dl', 'dh.dunning_level_id=dl.id', 'LEFT');
        $this->db->orderBy('dh.dunning_date', 'ASC');
        return $this->db->get('dunning_history dh', null, [
            'dh.*', 'dl.name as level_name'
        ]) ?: [];
    }

    /**
     * Get summary statistics for dunning dashboard
     */
    public function getDashboardStats(int $instanceId): array
    {
        $overdue = $this->getOverdueInvoices($instanceId);

        $totalOverdue = 0;
        $countByLevel = [0 => 0, 1 => 0, 2 => 0, 3 => 0];

        foreach ($overdue as $inv) {
            $totalOverdue += (float)$inv['gross_amount'];
            if ($inv['next_dunning_level']) {
                $lvl = min(3, (int)$inv['next_dunning_level']['level']);
                $countByLevel[$lvl]++;
            }
        }

        return [
            'total_overdue_count'  => count($overdue),
            'total_overdue_amount' => round($totalOverdue, 2),
            'by_level'             => $countByLevel,
        ];
    }

    private function getLastDunning(int $docLifecycleId): ?array
    {
        $this->db->where('document_lifecycle_id', $docLifecycleId);
        $this->db->orderBy('dunning_date', 'DESC');
        $this->db->orderBy('dunning_level', 'DESC');
        $result = $this->db->getOne('dunning_history');
        return $result ?: null;
    }

    private function getNextDunningLevel(int $instanceId, array $invoice): ?array
    {
        $daysOverdue = (int)((time() - strtotime($invoice['due_date'])) / 86400);
        $lastDunning = isset($invoice['last_dunning']) ? $invoice['last_dunning'] : $this->getLastDunning($invoice['id']);
        $currentLevel = $lastDunning ? (int)$lastDunning['dunning_level'] : -1;

        $this->db->where('instances_id', $instanceId);
        $this->db->where('level', $currentLevel + 1);
        $nextLevel = $this->db->getOne('dunning_levels');

        if (!$nextLevel) return null;
        if ($daysOverdue < (int)$nextLevel['days_after_due']) return null;

        return $nextLevel;
    }
}
