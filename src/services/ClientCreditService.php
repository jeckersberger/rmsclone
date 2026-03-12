<?php
/**
 * Kreditlimit-Verwaltung fuer Kunden
 *
 * Prueft ob ein neuer Rechnungsbetrag das Kreditlimit ueberschreiten wuerde
 * und aktualisiert den aktuellen Saldo (offene Rechnungen) des Kunden.
 */
class ClientCreditService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Kreditlimit pruefen
     *
     * @return array ['allowed' => bool, 'creditLimit' => float|null, 'currentBalance' => float, 'remaining' => float|null, 'message' => string]
     */
    public function checkCreditLimit(int $clientId, float $newInvoiceAmount = 0): array
    {
        $this->db->where('clients_id', $clientId);
        $client = $this->db->getOne('clients', ['clients_creditLimit', 'clients_currentBalance', 'clients_name']);

        if (!$client) {
            return [
                'allowed'        => false,
                'creditLimit'    => null,
                'currentBalance' => 0,
                'remaining'      => null,
                'message'        => 'Kunde nicht gefunden.',
            ];
        }

        $creditLimit    = $client['clients_creditLimit'];
        $currentBalance = (float)($client['clients_currentBalance'] ?? 0);

        // Kein Kreditlimit gesetzt -> immer erlaubt
        if ($creditLimit === null || $creditLimit === '') {
            return [
                'allowed'        => true,
                'creditLimit'    => null,
                'currentBalance' => $currentBalance,
                'remaining'      => null,
                'message'        => 'Kein Kreditlimit gesetzt.',
            ];
        }

        $creditLimit = (float)$creditLimit;
        $remaining   = $creditLimit - $currentBalance;
        $allowed     = ($currentBalance + $newInvoiceAmount) <= $creditLimit;

        return [
            'allowed'        => $allowed,
            'creditLimit'    => $creditLimit,
            'currentBalance' => $currentBalance,
            'remaining'      => $remaining,
            'message'        => $allowed
                ? sprintf('Kreditlimit OK. Verbleibend: %.2f', $remaining - $newInvoiceAmount)
                : sprintf('Kreditlimit ueberschritten! Limit: %.2f, Aktuell: %.2f, Neu: %.2f', $creditLimit, $currentBalance, $newInvoiceAmount),
        ];
    }

    /**
     * Aktuellen Saldo (offene Rechnungen) des Kunden neu berechnen und speichern
     *
     * Summiert alle offenen (nicht vollstaendig bezahlten) Rechnungsbetraege
     * aus document_exports fuer Projekte dieses Kunden.
     */
    public function updateCurrentBalance(int $clientId): float
    {
        // Summe offener Rechnungen berechnen
        $this->db->join('projects p', 'de.projects_id = p.projects_id', 'INNER');
        $this->db->where('p.clients_id', $clientId);
        $this->db->where('de.document_exports_type', 'invoice');
        $this->db->where('de.document_exports_status', ['sent', 'overdue', 'reminded', 'partial'], 'IN');
        $result = $this->db->getOne('document_exports de', 'COALESCE(SUM(de.document_exports_total - COALESCE(de.document_exports_paidAmount, 0)), 0) as total_outstanding');

        $balance = (float)($result['total_outstanding'] ?? 0);

        // Saldo auf Kundendatensatz speichern
        $this->db->where('clients_id', $clientId);
        $this->db->update('clients', ['clients_currentBalance' => $balance]);

        return $balance;
    }

    /**
     * Kreditlimit setzen
     */
    public function setCreditLimit(int $clientId, ?float $limit): bool
    {
        $this->db->where('clients_id', $clientId);
        return (bool)$this->db->update('clients', [
            'clients_creditLimit' => $limit,
        ]);
    }

    /**
     * Kredit-Info fuer Anzeige laden
     */
    public function getCreditInfo(int $clientId): array
    {
        $this->db->where('clients_id', $clientId);
        $client = $this->db->getOne('clients', [
            'clients_creditLimit', 'clients_currentBalance'
        ]);

        if (!$client) return ['creditLimit' => null, 'currentBalance' => 0, 'percentage' => 0];

        $limit   = $client['clients_creditLimit'] !== null ? (float)$client['clients_creditLimit'] : null;
        $balance = (float)($client['clients_currentBalance'] ?? 0);
        $pct     = ($limit && $limit > 0) ? round(($balance / $limit) * 100, 1) : 0;

        return [
            'creditLimit'    => $limit,
            'currentBalance' => $balance,
            'percentage'     => min($pct, 100),
        ];
    }
}
