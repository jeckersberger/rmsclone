<?php
/**
 * Umsatzsteuervoranmeldung (UStVA) Service
 *
 * Berechnet die UStVA-Kennzahlen aus Rechnungen (document_exports / document_lifecycle)
 * und Vorsteuer-Buchungen (euer_bookings) fuer einen Meldezeitraum (Monat).
 *
 * Kennzahlen gemaess ELSTER UStVA-Formular:
 *   Kz. 81 - Steuerpflichtige Umsaetze 19 % (Bemessungsgrundlage)
 *   Kz. 86 - Steuerpflichtige Umsaetze  7 % (Bemessungsgrundlage)
 *   Kz. 21 - Innergemeinschaftliche Lieferungen (§ 4 Nr. 1b UStG)
 *   Kz. 41 - Nicht steuerbare Umsaetze (Reverse Charge)
 *   Kz. 66 - Vorsteuerbetraege aus Rechnungen anderer Unternehmer
 *   Kz. 83 - Verbleibende USt-Vorauszahlung
 */
class UstvaService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Berechnet alle UStVA-Felder aus Rechnungen und Vorsteuer-Buchungen
     * fuer den angegebenen Meldezeitraum.
     */
    public function calculateUstva(int $instanceId, int $year, int $month): array
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));

        // ── 1) Ausgangsrechnungen aus document_exports (mit totals_json) ──
        // Wir verwenden das Rechnungsdatum (generated_at) fuer die Zuordnung
        $sql = "SELECT de.totals_json, de.doc_number, de.generated_at
                FROM document_exports de
                WHERE de.instances_id = ?
                  AND de.type = 'invoice'
                  AND DATE(de.generated_at) >= ?
                  AND DATE(de.generated_at) <= ?";
        $invoices = $this->db->rawQuery($sql, [$instanceId, $startDate, $endDate]) ?: [];

        // Auch document_lifecycle nutzen fuer Reverse-Charge-Kennzeichnung
        $sql = "SELECT dl.id, dl.doc_number, dl.net_amount, dl.gross_amount,
                       dl.created_at, dl.status,
                       c.clients_reverseCharge, c.clients_vatId, c.clients_country
                FROM document_lifecycle dl
                LEFT JOIN projects p ON dl.projects_id = p.projects_id
                LEFT JOIN clients c ON p.clients_id = c.clients_id
                WHERE dl.instances_id = ?
                  AND dl.doc_type = 'invoice'
                  AND DATE(dl.created_at) >= ?
                  AND DATE(dl.created_at) <= ?";
        $lifecycleInvoices = $this->db->rawQuery($sql, [$instanceId, $startDate, $endDate]) ?: [];

        // Index lifecycle by doc_number for cross-reference
        $lifecycleByDoc = [];
        foreach ($lifecycleInvoices as $li) {
            $lifecycleByDoc[$li['doc_number']] = $li;
        }

        // Kz. 81: Netto-Umsaetze 19 % (Bemessungsgrundlage)
        $kz81 = 0.0;
        // Steuer auf Kz. 81 (19 % von Kz. 81)
        $kz81Tax = 0.0;
        // Kz. 86: Netto-Umsaetze 7 % (Bemessungsgrundlage)
        $kz86 = 0.0;
        // Steuer auf Kz. 86 (7 % von Kz. 86)
        $kz86Tax = 0.0;
        // Kz. 21: Innergemeinschaftliche Lieferungen (steuerfrei)
        $kz21 = 0.0;
        // Kz. 41: Nicht steuerbare Umsaetze (Reverse Charge)
        $kz41 = 0.0;

        foreach ($invoices as $inv) {
            $totals = json_decode($inv['totals_json'], true);
            if (!$totals) {
                continue;
            }

            $net = (float)($totals['net'] ?? 0);
            $vatRate = (float)($totals['vat_rate'] ?? 0);
            $vat = (float)($totals['vat'] ?? 0);
            $isKur = !empty($totals['kur']);
            $isReverseCharge = !empty($totals['reverse_charge']);
            $docNumber = $inv['doc_number'];

            // KUR-Rechnungen haben keine USt -> nicht relevant fuer UStVA
            if ($isKur) {
                continue;
            }

            // Reverse-Charge: Kz. 41
            if ($isReverseCharge) {
                $kz41 += $net;
                continue;
            }

            // Innergemeinschaftliche Lieferung pruefen (EU-Ausland mit USt-ID, aber kein RC)
            $lcData = $lifecycleByDoc[$docNumber] ?? null;
            if ($lcData && !empty($lcData['clients_vatId']) && !empty($lcData['clients_country'])
                && $lcData['clients_country'] !== 'DE' && $lcData['clients_country'] !== 'Deutschland'
                && $vatRate == 0) {
                $kz21 += $net;
                continue;
            }

            // Regelbesteuerung: nach Steuersatz aufteilen
            if (abs($vatRate - 19.0) < 0.01) {
                $kz81 += $net;
                $kz81Tax += $vat;
            } elseif (abs($vatRate - 7.0) < 0.01) {
                $kz86 += $net;
                $kz86Tax += $vat;
            } elseif ($vatRate > 0) {
                // Anderer Steuersatz -> zu 19 % zaehlen (Fallback)
                $kz81 += $net;
                $kz81Tax += $vat;
            }
        }

        // ── 2) Vorsteuer aus euer_bookings (Eingangsbuchungen Typ 'expense' mit vat_amount) ──
        $sql = "SELECT SUM(eb.vat_amount) as total_vat
                FROM euer_bookings eb
                JOIN euer_categories ec ON eb.euer_categories_id = ec.id
                WHERE eb.instances_id = ?
                  AND ec.category_type = 'expense'
                  AND eb.vat_amount > 0
                  AND eb.booking_date >= ?
                  AND eb.booking_date <= ?";
        $vstRow = $this->db->rawQuery($sql, [$instanceId, $startDate, $endDate]);
        $kz66 = (float)($vstRow[0]['total_vat'] ?? 0);

        // ── 3) Kz. 83: Verbleibende USt-Vorauszahlung ──
        // Formel: USt auf steuerpflichtige Umsaetze - Vorsteuer
        $ustGesamt = $kz81Tax + $kz86Tax;
        $kz83 = round($ustGesamt - $kz66, 2);

        return [
            'year'  => $year,
            'month' => $month,
            'period' => sprintf('%04d-%02d', $year, $month),
            'kz81'  => round($kz81, 2),
            'kz81_tax' => round($kz81Tax, 2),
            'kz86'  => round($kz86, 2),
            'kz86_tax' => round($kz86Tax, 2),
            'kz21'  => round($kz21, 2),
            'kz41'  => round($kz41, 2),
            'kz66'  => round($kz66, 2),
            'kz83'  => $kz83,
            'ust_gesamt' => round($ustGesamt, 2),
        ];
    }

    /**
     * Speichert einen UStVA-Bericht als Entwurf
     */
    public function saveReport(int $instanceId, int $year, int $month, array $data): int
    {
        $period = sprintf('%04d-%02d', $year, $month);

        // Pruefen ob schon ein Bericht existiert
        $existing = $this->getReport($instanceId, $year, $month);

        $row = [
            'instances_id'   => $instanceId,
            'year'           => $year,
            'month'          => $month,
            'period'         => $period,
            'kz81'           => (float)($data['kz81'] ?? 0),
            'kz81_tax'       => (float)($data['kz81_tax'] ?? 0),
            'kz86'           => (float)($data['kz86'] ?? 0),
            'kz86_tax'       => (float)($data['kz86_tax'] ?? 0),
            'kz21'           => (float)($data['kz21'] ?? 0),
            'kz41'           => (float)($data['kz41'] ?? 0),
            'kz66'           => (float)($data['kz66'] ?? 0),
            'kz83'           => (float)($data['kz83'] ?? 0),
            'status'         => 'draft',
        ];

        if ($existing) {
            // Bereits uebermittelte Berichte nicht ueberschreiben
            if ($existing['status'] === 'submitted') {
                return (int)$existing['id'];
            }
            $this->db->where('id', $existing['id']);
            $this->db->update('ustva_reports', $row);
            return (int)$existing['id'];
        }

        $this->db->insert('ustva_reports', $row);
        return $this->db->getInsertId();
    }

    /**
     * Markiert einen UStVA-Bericht als uebermittelt
     */
    public function markSubmitted(int $reportId, string $elsterReference): bool
    {
        $this->db->where('id', $reportId);
        return $this->db->update('ustva_reports', [
            'status'            => 'submitted',
            'elster_reference'  => $elsterReference,
            'submitted_at'      => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Laedt einen gespeicherten UStVA-Bericht
     */
    public function getReport(int $instanceId, int $year, int $month): ?array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('year', $year);
        $this->db->where('month', $month);
        $result = $this->db->getOne('ustva_reports');
        return $result ?: null;
    }

    /**
     * Jahresuebersicht: 12 Monate mit allen Kennzahlen
     */
    public function getAnnualOverview(int $instanceId, int $year): array
    {
        $overview = [];
        for ($m = 1; $m <= 12; $m++) {
            $saved = $this->getReport($instanceId, $year, $m);
            if ($saved) {
                $overview[] = [
                    'month'     => $m,
                    'kz81'      => (float)$saved['kz81'],
                    'kz81_tax'  => (float)$saved['kz81_tax'],
                    'kz86'      => (float)$saved['kz86'],
                    'kz86_tax'  => (float)$saved['kz86_tax'],
                    'kz21'      => (float)$saved['kz21'],
                    'kz41'      => (float)$saved['kz41'],
                    'kz66'      => (float)$saved['kz66'],
                    'kz83'      => (float)$saved['kz83'],
                    'status'    => $saved['status'],
                    'elster_reference' => $saved['elster_reference'] ?? null,
                    'source'    => 'saved',
                ];
            } else {
                // Noch nicht gespeichert -> live berechnen
                $calc = $this->calculateUstva($instanceId, $year, $m);
                $overview[] = [
                    'month'     => $m,
                    'kz81'      => $calc['kz81'],
                    'kz81_tax'  => $calc['kz81_tax'],
                    'kz86'      => $calc['kz86'],
                    'kz86_tax'  => $calc['kz86_tax'],
                    'kz21'      => $calc['kz21'],
                    'kz41'      => $calc['kz41'],
                    'kz66'      => $calc['kz66'],
                    'kz83'      => $calc['kz83'],
                    'status'    => 'none',
                    'elster_reference' => null,
                    'source'    => 'calculated',
                ];
            }
        }
        return $overview;
    }
}
