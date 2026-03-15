<?php
/**
 * Smarte automatische Zahlungsabstimmung (Payment Matching)
 *
 * Matched Banktransaktionen zu offenen Rechnungen anhand eines gewichteten
 * Scoring-Algorithmus:
 *
 * 1. Rechnungsnummer im Verwendungszweck (50 Punkte)
 *    - Sucht nach Mustern wie RE-2025-0001, RG-123, #12345
 *
 * 2. Betragsabgleich exakt (30 Punkte)
 *    - Transaktionsbetrag entspricht genau document_exports_gross
 *    - Toleranz: 1 Cent
 *
 * 3. Teilbetragsabgleich (15 Punkte)
 *    - Betrag passt zu einem angemessenen Teil der Rechnung
 *    - z.B. bei Teilzahlungen
 *
 * 4. Kunden-IBAN Match (20 Punkte)
 *    - Absender-IBAN entspricht gespeicherter Kunden-IBAN
 *
 * 5. Kundenname im Verwendungszweck (10 Punkte)
 *    - Kundenname erscheint in der Transaktionsreferenz
 *
 * 6. Datums-Plausibilität (5 Punkte)
 *    - Zahlungsdatum liegt nach Rechnungsdatum
 *    - Innerhalb eines Zeitfensters (180 Tage)
 *
 * Features:
 * - Automatisches Matching mit Confidence Scores
 * - Manuelle Match-Anwendung mit Validierung
 * - Batch-AutoMatch mit konfigurierbarem Schwellenwert
 * - Candidate-Finderung für interaktive Ansichten
 * - Transaktions-Logging für Audit-Trail
 */
class PaymentMatchingService
{
    private $db;

    // Scoring-Gewichte
    const SCORE_INVOICE_NUMBER = 50;
    const SCORE_AMOUNT_EXACT = 30;
    const SCORE_AMOUNT_PARTIAL = 15;
    const SCORE_IBAN_MATCH = 20;
    const SCORE_CLIENT_NAME = 10;
    const SCORE_DATE_PLAUSIBLE = 5;

    // Konfiguration
    const MIN_CONFIDENCE_AUTO_APPLY = 70;
    const MAX_DAYS_BEFORE_INVOICE = 0;
    const MAX_DAYS_AFTER_INVOICE = 180;
    const AMOUNT_TOLERANCE = 0.01;
    const PARTIAL_AMOUNT_MIN_RATIO = 0.5;  // Minimum 50% der Rechnungssumme
    const PARTIAL_AMOUNT_MAX_RATIO = 0.99; // Maximum 99% (unter 100% für Teilzahlungen)

    public function __construct($db)
    {
        $this->db = $db;
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    //  AUTO-MATCHING (Alle Kandidaten mit Scores finden)
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Scanned alle ungematchten Banktransaktionen und findet beste Matches.
     * Gibt Array mit vorgeschlagenen Matches und Confidence Scores zurück.
     *
     * @param int $instanceId
     * @return array Array von ['transaction_id' => X, 'document_id' => Y, 'score' => Z, 'matched_at' => ...]
     */
    public function autoMatch(int $instanceId): array
    {
        // Alle ungematchten, positiven Transaktionen
        $transactions = $this->getUnmatched($instanceId);
        if (empty($transactions)) {
            return [];
        }

        $matches = [];

        foreach ($transactions as $tx) {
            $candidates = $this->findCandidates($instanceId, $tx['id']);
            if (!empty($candidates)) {
                // Besten Kandidaten nehmen
                usort($candidates, function ($a, $b) {
                    return $b['score'] <=> $a['score'];
                });
                $best = reset($candidates);
                if ($best['score'] > 0) {
                    $matches[] = [
                        'transaction_id' => $tx['id'],
                        'document_id' => $best['document_id'],
                        'score' => $best['score'],
                        'method' => $best['method'],
                        'details' => $best['details'],
                    ];
                }
            }
        }

        return $matches;
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    //  MATCH ANWENDEN
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Wendet ein Match an: aktualisiert bank_transaction Status und
     * document_exports Zahlungsbetrag.
     *
     * @param int $instanceId
     * @param int $transactionId        bank_transactions.id
     * @param int $documentExportId     document_exports.document_exports_id
     * @param int $userId              Benutzer der diese Aktion durchführt
     * @return bool
     */
    public function applyMatch(int $instanceId, int $transactionId, int $documentExportId, int $userId): bool
    {
        $this->db->startTransaction();
        try {
            // Transaktion laden
            $this->db->where('id', $transactionId);
            $this->db->where('instances_id', $instanceId);
            $transaction = $this->db->getOne('bank_transactions');
            if (!$transaction) {
                $this->db->rollback();
                return false;
            }

            // Rechnung laden und Lock
            $this->db->where('document_exports_id', $documentExportId);
            $this->db->where('instances_id', $instanceId);
            $this->db->setQueryOption('FOR UPDATE');
            $invoice = $this->db->getOne('document_exports');
            if (!$invoice) {
                $this->db->rollback();
                return false;
            }

            // Transaktion als gematchted markieren
            $this->db->where('id', $transactionId);
            $this->db->update('bank_transactions', [
                'document_exports_id' => $documentExportId,
                'status' => 'matched',
                'matched_at' => date('Y-m-d H:i:s'),
                'matched_by' => $userId,
            ]);

            // Zahlungsbetrag aktualisieren
            $previousPaid = (float)($invoice['document_exports_paidAmount'] ?? 0);
            $transactionAmount = (float)$transaction['amount'];
            $newPaid = round($previousPaid + $transactionAmount, 2);
            $gross = (float)($invoice['document_exports_gross'] ?? 0);

            $updateData = ['document_exports_paidAmount' => $newPaid];

            // Status auf "paid" setzen wenn vollständig bezahlt
            if ($newPaid >= $gross - self::AMOUNT_TOLERANCE) {
                $updateData['document_exports_status'] = 'paid';
            }

            $this->db->where('document_exports_id', $documentExportId);
            $this->db->update('document_exports', $updateData);

            // Audit Log
            $this->_logMatchAction($instanceId, $transactionId, $documentExportId, $userId, 'manual_match');

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Wendet alle AutoMatches über einem Schwellenwert automatisch an.
     * Gibt Array der angewendeten Matches zurück.
     *
     * @param int $instanceId
     * @param int $userId
     * @param int $minScore         Minimum-Score für Auto-Anwendung (default 70)
     * @return array
     */
    public function applyAutoMatches(int $instanceId, int $userId, int $minScore = self::MIN_CONFIDENCE_AUTO_APPLY): array
    {
        $autoMatches = $this->autoMatch($instanceId);
        $applied = [];

        foreach ($autoMatches as $match) {
            if ($match['score'] >= $minScore) {
                if ($this->applyMatch($instanceId, $match['transaction_id'], $match['document_id'], $userId)) {
                    $applied[] = $match;
                }
            }
        }

        return $applied;
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    //  KANDIDATEN-FINDER
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Findet mögliche Matches für eine spezifische Transaktion mit Scores.
     *
     * @param int $instanceId
     * @param int $transactionId   bank_transactions.id
     * @return array Array von ['document_id' => X, 'score' => Y, 'method' => '...', 'details' => [...]]
     */
    public function findCandidates(int $instanceId, int $transactionId): array
    {
        // Transaktion laden
        $this->db->where('id', $transactionId);
        $this->db->where('instances_id', $instanceId);
        $transaction = $this->db->getOne('bank_transactions');
        if (!$transaction) {
            return [];
        }

        // Nur positive Transaktionen matchen
        $txAmount = (float)$transaction['amount'];
        if ($txAmount <= 0) {
            return [];
        }

        // Offene Rechnungen laden
        $openInvoices = $this->_getOpenInvoices($instanceId);
        if (empty($openInvoices)) {
            return [];
        }

        $candidates = [];

        foreach ($openInvoices as $invoice) {
            $score = 0;
            $methods = [];
            $details = [];

            // 1. RECHNUNGSNUMMER im Verwendungszweck (50 Punkte)
            $invoiceScore = $this->_scoreInvoiceNumber($transaction, $invoice);
            if ($invoiceScore > 0) {
                $score += $invoiceScore;
                $methods[] = 'invoice_number_match';
                $details['invoice_number'] = true;
            }

            // 2. BETRAGSABGLEICH exakt (30 Punkte)
            $amountScore = $this->_scoreAmountExact($transaction, $invoice);
            if ($amountScore > 0) {
                $score += $amountScore;
                $methods[] = 'amount_exact_match';
                $details['amount_exact'] = true;
            }

            // 3. TEILBETRAGSABGLEICH (15 Punkte)
            if ($amountScore === 0) {  // Nur wenn kein exakter Match
                $partialScore = $this->_scoreAmountPartial($transaction, $invoice);
                if ($partialScore > 0) {
                    $score += $partialScore;
                    $methods[] = 'amount_partial_match';
                    $details['amount_partial'] = true;
                }
            }

            // 4. KUNDEN-IBAN Match (20 Punkte)
            $ibanScore = $this->_scoreIbanMatch($transaction, $invoice);
            if ($ibanScore > 0) {
                $score += $ibanScore;
                $methods[] = 'iban_match';
                $details['iban'] = true;
            }

            // 5. KUNDENNAME im Verwendungszweck (10 Punkte)
            $nameScore = $this->_scoreClientNameMatch($transaction, $invoice);
            if ($nameScore > 0) {
                $score += $nameScore;
                $methods[] = 'client_name_match';
                $details['client_name'] = true;
            }

            // 6. DATUMS-PLAUSIBILITÄT (5 Punkte)
            $dateScore = $this->_scoreDatePlausibility($transaction, $invoice);
            if ($dateScore > 0) {
                $score += $dateScore;
                $methods[] = 'date_plausible';
                $details['date_plausible'] = true;
            }

            // Nur Kandidaten mit Score > 0 hinzufügen
            if ($score > 0) {
                $candidates[] = [
                    'document_id' => (int)$invoice['document_exports_id'],
                    'score' => $score,
                    'method' => implode(', ', $methods),
                    'details' => $details,
                ];
            }
        }

        return $candidates;
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    //  ABFRAGEN
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Gibt alle ungematchten Transaktionen einer Instanz zurück.
     *
     * @param int $instanceId
     * @return array
     */
    public function getUnmatched(int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('status', 'unmatched');
        $this->db->where('amount', 0, '>');
        $this->db->orderBy('transaction_date', 'DESC');
        return $this->db->get('bank_transactions') ?: [];
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    //  SCORING-FUNKTIONEN
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Scoring: Rechnungsnummer im Verwendungszweck (50 Punkte)
     * Sucht nach Mustern: RE-2025-0001, RG-123, #12345
     */
    private function _scoreInvoiceNumber(array $transaction, array $invoice): int
    {
        $reference = strtoupper($transaction['reference'] ?? '');
        $invoiceNumber = strtoupper($invoice['document_exports_number'] ?? '');

        if (empty($reference) || empty($invoiceNumber)) {
            return 0;
        }

        // Direkter Match
        if (strpos($reference, $invoiceNumber) !== false) {
            return self::SCORE_INVOICE_NUMBER;
        }

        // Wildcard-Matching für Rechnungsnummern-Variationen
        // z.B. RE-2025-0001 könnte als RE-2025-1 oder 0001 oder 2025-0001 erscheinen
        $invoiceNum = preg_replace('/[^0-9A-Z\-]/', '', $invoiceNumber);
        if (!empty($invoiceNum) && strpos($reference, $invoiceNum) !== false) {
            return self::SCORE_INVOICE_NUMBER;
        }

        return 0;
    }

    /**
     * Scoring: Betragsabgleich exakt (30 Punkte)
     * Mit 1 Cent Toleranz
     */
    private function _scoreAmountExact(array $transaction, array $invoice): int
    {
        $txAmount = (float)$transaction['amount'];
        $paidAmount = (float)($invoice['document_exports_paidAmount'] ?? 0);
        $gross = (float)($invoice['document_exports_gross'] ?? 0);
        $remaining = $gross - $paidAmount;

        // Exakter Match zur Gesamtsumme oder zum Restbetrag
        if (abs($txAmount - $gross) < self::AMOUNT_TOLERANCE ||
            abs($txAmount - $remaining) < self::AMOUNT_TOLERANCE) {
            return self::SCORE_AMOUNT_EXACT;
        }

        return 0;
    }

    /**
     * Scoring: Teilbetragsabgleich (15 Punkte)
     * Betrag passt zu einem angemessenen Teil der Rechnung (50-99%)
     */
    private function _scoreAmountPartial(array $transaction, array $invoice): int
    {
        $txAmount = (float)$transaction['amount'];
        $paidAmount = (float)($invoice['document_exports_paidAmount'] ?? 0);
        $gross = (float)($invoice['document_exports_gross'] ?? 0);

        if ($gross <= 0) {
            return 0;
        }

        $remaining = $gross - $paidAmount;

        // Teilzahlung im sinnvollen Bereich
        if ($txAmount > 0 && $txAmount <= $remaining) {
            $ratio = $txAmount / $gross;
            if ($ratio >= self::PARTIAL_AMOUNT_MIN_RATIO &&
                $ratio <= self::PARTIAL_AMOUNT_MAX_RATIO) {
                return self::SCORE_AMOUNT_PARTIAL;
            }
        }

        return 0;
    }

    /**
     * Scoring: Kunden-IBAN Match (20 Punkte)
     * Absender-IBAN entspricht gespeicherter Kunden-IBAN
     */
    private function _scoreIbanMatch(array $transaction, array $invoice): int
    {
        $senderIban = $transaction['sender_iban'] ?? '';
        $clientIban = $invoice['clients_iban'] ?? '';

        if (empty($senderIban) || empty($clientIban)) {
            return 0;
        }

        if ($this->_normalizeIban($senderIban) === $this->_normalizeIban($clientIban)) {
            return self::SCORE_IBAN_MATCH;
        }

        return 0;
    }

    /**
     * Scoring: Kundenname im Verwendungszweck (10 Punkte)
     * Kundenname erscheint in der Transaktionsreferenz
     */
    private function _scoreClientNameMatch(array $transaction, array $invoice): int
    {
        $reference = strtolower($transaction['reference'] ?? '');
        $clientName = strtolower($invoice['clients_name'] ?? '');

        if (empty($reference) || empty($clientName)) {
            return 0;
        }

        // Direkter Match
        if (strpos($reference, $clientName) !== false) {
            return self::SCORE_CLIENT_NAME;
        }

        // Teilworte des Namens matchen
        $nameParts = array_filter(explode(' ', $clientName));
        foreach ($nameParts as $part) {
            if (strlen($part) >= 3 && strpos($reference, strtolower($part)) !== false) {
                return self::SCORE_CLIENT_NAME;
            }
        }

        return 0;
    }

    /**
     * Scoring: Datums-Plausibilität (5 Punkte)
     * Zahlungsdatum liegt nach Rechnungsdatum, innerhalb 180 Tagen
     */
    private function _scoreDatePlausibility(array $transaction, array $invoice): int
    {
        $txDate = new \DateTime($transaction['transaction_date']);
        $invoiceDate = new \DateTime($invoice['document_exports_date']);

        $daysDiff = $txDate->diff($invoiceDate)->days;
        $direction = $txDate < $invoiceDate ? -1 : 1;

        // Zahlungseingang muss nach oder am gleichen Datum der Rechnung sein
        if ($direction < 0 && $daysDiff > self::MAX_DAYS_BEFORE_INVOICE) {
            return 0;  // Bezahlung vor Rechnungsdatum - nicht plausibel
        }

        // Zahlungseingang muss innerhalb des Zeitfensters liegen
        if ($direction >= 0 && $daysDiff <= self::MAX_DAYS_AFTER_INVOICE) {
            return self::SCORE_DATE_PLAUSIBLE;
        }

        return 0;
    }

    // ═══════════════════════════════════════════════════════════════════════════════
    //  HILFSFUNKTIONEN
    // ═══════════════════════════════════════════════════════════════════════════════

    /**
     * Lädt offene Rechnungen mit Kunden-IBAN für Matching.
     */
    private function _getOpenInvoices(int $instanceId): array
    {
        $this->db->where('de.instances_id', $instanceId);
        $this->db->where('de.document_exports_status', ['sent', 'overdue', 'partial'], 'IN');
        $this->db->where('de.document_exports_deleted', 0);
        $this->db->join('projects p', 'de.projects_id=p.projects_id', 'LEFT');
        $this->db->join('clients c', 'p.clients_id=c.clients_id', 'LEFT');
        $this->db->orderBy('de.document_exports_date', 'DESC');

        return $this->db->get('document_exports de', null, [
            'de.document_exports_id',
            'de.document_exports_number',
            'de.document_exports_gross',
            'de.document_exports_paidAmount',
            'de.document_exports_date',
            'de.document_exports_status',
            'c.clients_name',
            'c.clients_iban',
        ]) ?: [];
    }

    /**
     * Normalisiert IBAN für Vergleich (Großbuchstaben, keine Leerzeichen)
     */
    private function _normalizeIban(string $iban): string
    {
        return strtoupper(preg_replace('/\s+/', '', $iban));
    }

    /**
     * Protokolliert Match-Aktionen für Audit-Trail
     */
    private function _logMatchAction(int $instanceId, int $transactionId, int $documentId,
                                      int $userId, string $action): void
    {
        $table = 'payment_matching_log';

        // Prüfen ob Tabelle existiert (optional für Audit)
        $tableExists = false;
        try {
            $this->db->where('1', '0');
            $this->db->getOne($table);
            $tableExists = true;
        } catch (\Exception $e) {
            // Tabelle existiert nicht, ignorieren
        }

        if ($tableExists) {
            $this->db->insert($table, [
                'instances_id' => $instanceId,
                'transaction_id' => $transactionId,
                'document_id' => $documentId,
                'action' => $action,
                'user_id' => $userId,
                'logged_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
}
