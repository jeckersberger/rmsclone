<?php
/**
 * Teilzahlungs-Tracking auf Rechnungsebene
 *
 * Verwaltet einzelne Zahlungseingaenge pro Rechnung (invoice_payments),
 * aktualisiert den Gesamtstatus in document_exports und steuert die
 * automatische Mahnsperre bei Teilzahlungen.
 */
class PaymentTrackingService
{
    private $db;

    /** Karenzzeit in Tagen nach Teilzahlung, bevor Mahnverfahren fortgesetzt wird */
    private int $gracePeriodDays;

    public function __construct($db, int $gracePeriodDays = 14)
    {
        $this->db = $db;
        $this->gracePeriodDays = $gracePeriodDays;
    }

    /**
     * Zahlung erfassen
     *
     * Speichert die Zahlung, aktualisiert paidAmount / paymentStatus
     * und pausiert ggf. das Mahnverfahren bei Teilzahlung.
     *
     * @return array Zahlungsdatensatz inkl. aktualisierter Statusinfos
     */
    public function recordPayment(
        int    $documentId,
        float  $amount,
        string $date,
        string $method = 'bank_transfer',
        string $reference = '',
        string $notes = ''
    ): array {
        $export = $this->getExport($documentId);
        if (!$export) {
            throw new \RuntimeException("Rechnung (document_exports_id={$documentId}) nicht gefunden.");
        }

        $validMethods = ['bank_transfer', 'cash', 'sepa', 'paypal', 'other'];
        if (!in_array($method, $validMethods)) {
            $method = 'other';
        }

        // Zahlung einfuegen
        $this->db->insert('invoice_payments', [
            'document_exports_id' => $documentId,
            'amount'              => $amount,
            'payment_date'        => $date,
            'payment_method'      => $method,
            'reference'           => $reference ?: null,
            'notes'               => $notes ?: null,
        ]);
        $paymentId = $this->db->getInsertId();

        // Gesamtbetrag und Status aktualisieren
        $this->recalculate($documentId);

        // Mahnsperre pruefen
        $remaining = $this->getRemainingAmount($documentId);
        if ($remaining > 0.01) {
            // Teilzahlung: Mahnverfahren pausieren
            $this->pauseDunningForDocument($documentId, 'Teilzahlung eingegangen (' . number_format($amount, 2, ',', '.') . ' EUR)');
        } else {
            // Vollstaendig bezahlt: Mahnsperre aufheben
            $this->resumeDunningForDocument($documentId);
        }

        return [
            'payment_id'     => $paymentId,
            'amount'         => $amount,
            'total_paid'     => $this->getPaidAmount($documentId),
            'remaining'      => $this->getRemainingAmount($documentId),
            'payment_status' => $this->getPaymentStatus($documentId),
        ];
    }

    /**
     * Alle Zahlungen fuer eine Rechnung auflisten
     */
    public function getPayments(int $documentId): array
    {
        $this->db->where('document_exports_id', $documentId);
        $this->db->orderBy('payment_date', 'ASC');
        $this->db->orderBy('id', 'ASC');
        return $this->db->get('invoice_payments') ?: [];
    }

    /**
     * Offener Restbetrag berechnen
     */
    public function getRemainingAmount(int $documentId): float
    {
        $export = $this->getExport($documentId);
        if (!$export) return 0.0;

        $gross = $this->getGrossAmount($export);
        $paid = (float)($export['document_exports_paidAmount'] ?? 0);

        return round(max(0, $gross - $paid), 2);
    }

    /**
     * Zahlung loeschen und Betraege neu berechnen
     */
    public function deletePayment(int $paymentId): bool
    {
        $this->db->where('id', $paymentId);
        $payment = $this->db->getOne('invoice_payments', null);
        if (!$payment) return false;

        $documentId = (int)$payment['document_exports_id'];

        $this->db->where('id', $paymentId);
        $this->db->delete('invoice_payments');

        $this->recalculate($documentId);

        // Mahnsperre-Status nach Neuberechnung pruefen
        $remaining = $this->getRemainingAmount($documentId);
        if ($remaining > 0.01) {
            // Noch nicht bezahlt - pruefen ob Karenzzeit abgelaufen
            $this->checkGracePeriod($documentId);
        }

        return true;
    }

    /**
     * Mahnverfahren manuell pausieren/fortsetzen
     */
    public function toggleDunningPause(int $documentId, bool $paused, string $reason = ''): bool
    {
        // Alle dunning_history-Eintraege fuer dieses Dokument aktualisieren
        $docLifecycleId = $this->getDocLifecycleId($documentId);
        if (!$docLifecycleId) return false;

        $this->db->where('document_lifecycle_id', $docLifecycleId);
        $this->db->orderBy('dunning_date', 'DESC');
        $lastDunning = $this->db->getOne('dunning_history', null);

        if ($lastDunning) {
            $this->db->where('id', $lastDunning['id']);
            $this->db->update('dunning_history', [
                'dunning_history_paused'      => $paused ? 1 : 0,
                'dunning_history_pauseReason' => $paused ? ($reason ?: 'Manuell pausiert') : null,
            ]);
        }

        return true;
    }

    /**
     * Automatische Mahnsperre bei Teilzahlung setzen
     */
    public function pauseDunningForDocument(int $documentId, string $reason): void
    {
        $docLifecycleId = $this->getDocLifecycleId($documentId);
        if (!$docLifecycleId) return;

        $this->db->where('document_lifecycle_id', $docLifecycleId);
        $entries = $this->db->get('dunning_history') ?: [];

        foreach ($entries as $entry) {
            $this->db->where('id', $entry['id']);
            $this->db->update('dunning_history', [
                'dunning_history_paused'      => 1,
                'dunning_history_pauseReason' => $reason,
            ]);
        }
    }

    /**
     * Mahnsperre aufheben
     */
    public function resumeDunningForDocument(int $documentId): void
    {
        $docLifecycleId = $this->getDocLifecycleId($documentId);
        if (!$docLifecycleId) return;

        $this->db->where('document_lifecycle_id', $docLifecycleId);
        $entries = $this->db->get('dunning_history') ?: [];

        foreach ($entries as $entry) {
            $this->db->where('id', $entry['id']);
            $this->db->update('dunning_history', [
                'dunning_history_paused'      => 0,
                'dunning_history_pauseReason' => null,
            ]);
        }
    }

    /**
     * Pruefen ob Karenzzeit nach letzter Teilzahlung abgelaufen ist.
     * Falls ja, Mahnverfahren automatisch fortsetzen.
     */
    public function checkGracePeriod(int $documentId): bool
    {
        $remaining = $this->getRemainingAmount($documentId);
        if ($remaining <= 0.01) return false; // vollstaendig bezahlt

        // Letzte Zahlung ermitteln
        $this->db->where('document_exports_id', $documentId);
        $this->db->orderBy('payment_date', 'DESC');
        $lastPayment = $this->db->getOne('invoice_payments', null);

        if (!$lastPayment) return false;

        $lastPaymentDate = strtotime($lastPayment['payment_date']);
        $graceEnd = $lastPaymentDate + ($this->gracePeriodDays * 86400);

        if (time() > $graceEnd) {
            // Karenzzeit abgelaufen: Mahnverfahren fortsetzen
            $this->resumeDunningForDocument($documentId);
            return true;
        }

        return false;
    }

    /**
     * Batch: Alle Rechnungen mit Teilzahlung auf abgelaufene Karenzzeit pruefen.
     * Geeignet fuer einen Cronjob / taeglichen Aufruf.
     */
    public function checkAllGracePeriods(): int
    {
        $this->db->where('document_exports_paymentStatus', 'partial');
        $partials = $this->db->get('document_exports', null, ['document_exports_id']) ?: [];

        $resumed = 0;
        foreach ($partials as $row) {
            if ($this->checkGracePeriod((int)$row['document_exports_id'])) {
                $resumed++;
            }
        }

        return $resumed;
    }

    // ═══════════════════════════════════════════════
    //  Interne Hilfsfunktionen
    // ═══════════════════════════════════════════════

    /**
     * Gezahlten Betrag und Status neu berechnen
     */
    private function recalculate(int $documentId): void
    {
        $this->db->where('document_exports_id', $documentId);
        $result = $this->db->getOne('invoice_payments', null, ['SUM(amount) as total_paid']);
        $totalPaid = (float)($result['total_paid'] ?? 0);

        $export = $this->getExport($documentId);
        if (!$export) return;

        $gross = $this->getGrossAmount($export);

        if ($totalPaid <= 0) {
            $status = 'unpaid';
        } elseif ($totalPaid >= $gross - 0.01) {
            $status = 'paid';
        } else {
            $status = 'partial';
        }

        $this->db->where('document_exports_id', $documentId);
        $this->db->update('document_exports', [
            'document_exports_paidAmount'    => round($totalPaid, 2),
            'document_exports_paymentStatus' => $status,
        ]);
    }

    private function getExport(int $documentId): ?array
    {
        $this->db->where('document_exports_id', $documentId);
        $row = $this->db->getOne('document_exports', null);
        return $row ?: null;
    }

    private function getGrossAmount(array $export): float
    {
        // Bruttobetrag aus totals_json auslesen
        if (!empty($export['totals_json'])) {
            $totals = json_decode($export['totals_json'], true);
            if (isset($totals['gross'])) {
                return (float)$totals['gross'];
            }
        }
        // Fallback: document_exports_total
        return (float)($export['document_exports_total'] ?? 0);
    }

    private function getPaidAmount(int $documentId): float
    {
        $export = $this->getExport($documentId);
        return (float)($export['document_exports_paidAmount'] ?? 0);
    }

    private function getPaymentStatus(int $documentId): string
    {
        $export = $this->getExport($documentId);
        return $export['document_exports_paymentStatus'] ?? 'unpaid';
    }

    /**
     * document_lifecycle ID fuer eine document_exports_id ermitteln
     */
    private function getDocLifecycleId(int $documentExportsId): ?int
    {
        $this->db->where('document_exports_id', $documentExportsId);
        $row = $this->db->getOne('document_lifecycle', null, ['id']);
        return $row ? (int)$row['id'] : null;
    }
}
