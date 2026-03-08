<?php
/**
 * BWA-Service (Betriebswirtschaftliche Auswertung)
 *
 * Erzeugt eine BWA nach DATEV-Standard mit allen relevanten Positionen:
 * - Umsatzerloese
 * - Materialaufwand
 * - Personalkosten
 * - Raumkosten
 * - Sonstige betriebliche Aufwendungen
 * - Abschreibungen
 * - Betriebsergebnis / EBIT
 *
 * Datenquellen:
 * - document_lifecycle (Rechnungen = Umsatz)
 * - euer_bookings / euer_categories (Ausgaben nach Kategorie)
 */
class BwaService
{
    private $db;

    /**
     * Mapping: EUeR-Zeilen auf BWA-Positionen
     * Die euer_categories.euer_line wird auf BWA-Zeilen gemappt.
     */
    private static $euerLineToBwa = [
        '14' => 'umsatzerloese',       // Betriebseinnahmen
        '51' => 'materialaufwand',      // Bezogene Leistungen / Fremdpersonal
        '52' => 'personalkosten',       // Eigenes Personal
        '54' => 'sonstige_aufwendungen', // Fahrzeugkosten
        '56' => 'raumkosten',           // Raumkosten
        '62' => 'sonstige_aufwendungen', // Sonstige unbeschraenkt abziehbare BA
        '64' => 'abschreibungen',       // Abschreibungen auf Anlagevermoegen
    ];

    private static $monthNames = [
        1 => 'Januar', 2 => 'Februar', 3 => 'Maerz', 4 => 'April',
        5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
    ];

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Monatliche BWA generieren
     *
     * @param int $instanceId
     * @param int $year
     * @param int $month 1-12
     * @return array BWA-Daten mit allen Positionen
     */
    public function generateBwa(int $instanceId, int $year, int $month): array
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));

        // 1. Umsatzerloese aus bezahlten Rechnungen
        $umsatzerloese = $this->getInvoiceRevenue($instanceId, $startDate, $endDate);

        // 2. Aufwendungen aus EUeR-Buchungen nach Kategorie
        $expenses = $this->getExpensesByBwaPosition($instanceId, $startDate, $endDate);

        // BWA-Positionen aufbauen
        $materialaufwand = $expenses['materialaufwand'] ?? 0;
        $personalkosten = $expenses['personalkosten'] ?? 0;
        $raumkosten = $expenses['raumkosten'] ?? 0;
        $sonstigeAufwendungen = $expenses['sonstige_aufwendungen'] ?? 0;
        $abschreibungen = $expenses['abschreibungen'] ?? 0;

        $gesamtAufwendungen = $materialaufwand + $personalkosten + $raumkosten
                            + $sonstigeAufwendungen + $abschreibungen;

        $rohertrag = $umsatzerloese - $materialaufwand;
        $betriebsergebnis = $umsatzerloese - $gesamtAufwendungen;
        $ebit = $betriebsergebnis; // Vereinfacht: keine Zinsen/Steuern in KUR

        $bwa = [
            'instance_id' => $instanceId,
            'year' => $year,
            'month' => $month,
            'month_name' => self::$monthNames[$month] ?? '',
            'period' => sprintf('%02d/%04d', $month, $year),

            // Ertraege
            'umsatzerloese' => round($umsatzerloese, 2),
            'sonstige_ertraege' => 0,
            'gesamtleistung' => round($umsatzerloese, 2),

            // Aufwendungen
            'materialaufwand' => round($materialaufwand, 2),
            'rohertrag' => round($rohertrag, 2),

            'personalkosten' => round($personalkosten, 2),
            'raumkosten' => round($raumkosten, 2),
            'sonstige_aufwendungen' => round($sonstigeAufwendungen, 2),
            'abschreibungen' => round($abschreibungen, 2),

            'gesamt_aufwendungen' => round($gesamtAufwendungen, 2),

            // Ergebnis
            'betriebsergebnis' => round($betriebsergebnis, 2),
            'zinsen_ertraege' => 0,
            'zinsen_aufwendungen' => 0,
            'ebit' => round($ebit, 2),

            // Detaildaten
            'details' => [
                'invoice_count' => $this->getInvoiceCount($instanceId, $startDate, $endDate),
                'expense_items' => $expenses['items'] ?? [],
            ],
        ];

        // BWA-Report in DB speichern/aktualisieren
        $this->saveBwaReport($instanceId, $year, $month, $bwa);

        return $bwa;
    }

    /**
     * Jahresuebersicht mit 12 Monatsspalten
     *
     * @param int $instanceId
     * @param int $year
     * @return array Jahres-BWA mit monatlichen Spalten und Summen
     */
    public function generateBwaReport(int $instanceId, int $year): array
    {
        $months = [];
        $totals = [
            'umsatzerloese' => 0,
            'materialaufwand' => 0,
            'rohertrag' => 0,
            'personalkosten' => 0,
            'raumkosten' => 0,
            'sonstige_aufwendungen' => 0,
            'abschreibungen' => 0,
            'gesamt_aufwendungen' => 0,
            'betriebsergebnis' => 0,
            'ebit' => 0,
        ];

        $currentMonth = ($year == (int)date('Y')) ? (int)date('n') : 12;

        for ($m = 1; $m <= 12; $m++) {
            if ($m <= $currentMonth) {
                $bwa = $this->generateBwa($instanceId, $year, $m);
            } else {
                // Zukunft: leere Werte
                $bwa = $this->emptyBwa($year, $m);
            }
            $months[$m] = $bwa;

            // Summen
            foreach (array_keys($totals) as $key) {
                $totals[$key] += $bwa[$key] ?? 0;
            }
        }

        // Durchschnitte berechnen
        $avgDivisor = max($currentMonth, 1);
        $averages = [];
        foreach ($totals as $key => $val) {
            $averages[$key] = round($val / $avgDivisor, 2);
        }

        return [
            'year' => $year,
            'months' => $months,
            'totals' => array_map(fn($v) => round($v, 2), $totals),
            'averages' => $averages,
            'current_month' => $currentMonth,
        ];
    }

    /**
     * Periodenvergleich (z.B. Vorjahr vs. aktuelles Jahr)
     *
     * @param int $instanceId
     * @param int $year1 Vergleichsjahr (aelter)
     * @param int $month1
     * @param int $year2 Aktuelles Jahr
     * @param int $month2
     * @return array Vergleichsdaten mit absoluter und prozentualer Abweichung
     */
    public function comparePeriods(int $instanceId, int $year1, int $month1, int $year2, int $month2): array
    {
        $bwa1 = $this->generateBwa($instanceId, $year1, $month1);
        $bwa2 = $this->generateBwa($instanceId, $year2, $month2);

        $compareKeys = [
            'umsatzerloese', 'materialaufwand', 'rohertrag',
            'personalkosten', 'raumkosten', 'sonstige_aufwendungen',
            'abschreibungen', 'gesamt_aufwendungen', 'betriebsergebnis', 'ebit',
        ];

        $comparison = [];
        foreach ($compareKeys as $key) {
            $val1 = $bwa1[$key] ?? 0;
            $val2 = $bwa2[$key] ?? 0;
            $diff = $val2 - $val1;
            $pctChange = $val1 != 0 ? round(($diff / abs($val1)) * 100, 1) : ($val2 != 0 ? 100 : 0);

            $comparison[$key] = [
                'period1' => round($val1, 2),
                'period2' => round($val2, 2),
                'difference' => round($diff, 2),
                'pct_change' => $pctChange,
            ];
        }

        return [
            'period1' => [
                'year' => $year1,
                'month' => $month1,
                'label' => sprintf('%s %d', self::$monthNames[$month1] ?? '', $year1),
                'bwa' => $bwa1,
            ],
            'period2' => [
                'year' => $year2,
                'month' => $month2,
                'label' => sprintf('%s %d', self::$monthNames[$month2] ?? '', $year2),
                'bwa' => $bwa2,
            ],
            'comparison' => $comparison,
        ];
    }

    // ──────────────────────────────────────────────
    // Private Hilfsmethoden
    // ──────────────────────────────────────────────

    /**
     * Umsatzerloese aus bezahlten Rechnungen (Zufluss-Prinzip)
     */
    private function getInvoiceRevenue(int $instanceId, string $startDate, string $endDate): float
    {
        $sql = "SELECT COALESCE(SUM(COALESCE(paid_amount, gross_amount)), 0) as total
                FROM document_lifecycle
                WHERE instances_id = ?
                  AND doc_type = 'invoice'
                  AND status = 'paid'
                  AND paid_date >= ?
                  AND paid_date <= ?";
        $result = $this->db->rawQuery($sql, [$instanceId, $startDate, $endDate]);
        return (float)($result[0]['total'] ?? 0);
    }

    /**
     * Anzahl Rechnungen im Zeitraum
     */
    private function getInvoiceCount(int $instanceId, string $startDate, string $endDate): int
    {
        $sql = "SELECT COUNT(*) as cnt
                FROM document_lifecycle
                WHERE instances_id = ?
                  AND doc_type = 'invoice'
                  AND status = 'paid'
                  AND paid_date >= ?
                  AND paid_date <= ?";
        $result = $this->db->rawQuery($sql, [$instanceId, $startDate, $endDate]);
        return (int)($result[0]['cnt'] ?? 0);
    }

    /**
     * Aufwendungen aus EUeR-Buchungen, gruppiert nach BWA-Position
     */
    private function getExpensesByBwaPosition(int $instanceId, string $startDate, string $endDate): array
    {
        $sql = "SELECT ec.euer_line, ec.name as category_name,
                       SUM(eb.amount) as total, COUNT(eb.id) as item_count
                FROM euer_bookings eb
                JOIN euer_categories ec ON eb.euer_categories_id = ec.id
                WHERE eb.instances_id = ?
                  AND ec.category_type = 'expense'
                  AND eb.booking_date >= ?
                  AND eb.booking_date <= ?
                GROUP BY ec.euer_line, ec.name";
        $rows = $this->db->rawQuery($sql, [$instanceId, $startDate, $endDate]) ?: [];

        $positions = [
            'materialaufwand' => 0,
            'personalkosten' => 0,
            'raumkosten' => 0,
            'sonstige_aufwendungen' => 0,
            'abschreibungen' => 0,
        ];

        $items = [];
        foreach ($rows as $row) {
            $bwaPos = self::$euerLineToBwa[$row['euer_line']] ?? 'sonstige_aufwendungen';
            $positions[$bwaPos] += (float)$row['total'];
            $items[] = [
                'euer_line' => $row['euer_line'],
                'category' => $row['category_name'],
                'bwa_position' => $bwaPos,
                'total' => round((float)$row['total'], 2),
                'count' => (int)$row['item_count'],
            ];
        }

        $positions['items'] = $items;
        return $positions;
    }

    /**
     * Leere BWA fuer Zukunftsmonate
     */
    private function emptyBwa(int $year, int $month): array
    {
        return [
            'year' => $year,
            'month' => $month,
            'month_name' => self::$monthNames[$month] ?? '',
            'period' => sprintf('%02d/%04d', $month, $year),
            'umsatzerloese' => 0,
            'sonstige_ertraege' => 0,
            'gesamtleistung' => 0,
            'materialaufwand' => 0,
            'rohertrag' => 0,
            'personalkosten' => 0,
            'raumkosten' => 0,
            'sonstige_aufwendungen' => 0,
            'abschreibungen' => 0,
            'gesamt_aufwendungen' => 0,
            'betriebsergebnis' => 0,
            'zinsen_ertraege' => 0,
            'zinsen_aufwendungen' => 0,
            'ebit' => 0,
            'details' => ['invoice_count' => 0, 'expense_items' => []],
        ];
    }

    /**
     * BWA-Report in DB speichern (Upsert)
     */
    private function saveBwaReport(int $instanceId, int $year, int $month, array $data): void
    {
        // Pruefen ob bereits vorhanden
        $this->db->where('instances_id', $instanceId);
        $this->db->where('year', $year);
        $this->db->where('month', $month);
        $existing = $this->db->getOne('bwa_reports');

        $record = [
            'instances_id' => $instanceId,
            'year' => $year,
            'month' => $month,
            'generated_at' => date('Y-m-d H:i:s'),
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
        ];

        if ($existing) {
            $this->db->where('id', $existing['id']);
            $this->db->update('bwa_reports', $record);
        } else {
            $this->db->insert('bwa_reports', $record);
        }
    }
}
