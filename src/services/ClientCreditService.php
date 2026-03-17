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
        $client = $this->db->getOne('clients', null, ['clients_creditLimit', 'clients_currentBalance', 'clients_name']);

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
        // Summe offener Rechnungen berechnen aus document_lifecycle
        $sql = "SELECT COALESCE(SUM(dl.gross_amount - COALESCE(dl.paid_amount, 0)), 0) as total_outstanding
                FROM document_lifecycle dl
                INNER JOIN projects p ON dl.projects_id = p.projects_id
                WHERE p.clients_id = ?
                AND dl.doc_type = 'invoice'
                AND dl.status IN ('sent', 'overdue', 'reminded', 'partial')";
        $result = $this->db->rawQuery($sql, [$clientId]);
        $balance = (float)($result[0]['total_outstanding'] ?? 0);

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
        $client = $this->db->getOne('clients', null, [
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
