<?php
/**
 * Kassenbuch-Service (Cash Book)
 *
 * Verwaltet Bareinnahmen und -ausgaben mit laufendem Saldo,
 * automatischer Belegnummern-Vergabe und CSV-Export.
 */
class KassenbuchService
{
    private $db;

    private const CATEGORIES = [
        'Bareinnahme',
        'Barausgabe',
        'Privateinlage',
        'Privatentnahme',
        'Durchlaufender Posten',
    ];

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Neuen Kassenbuch-Eintrag hinzufuegen
     */
    public function addEntry(int $instanceId, array $data): array
    {
        $type = $data['type'] ?? 'ausgabe';
        if (!in_array($type, ['einnahme', 'ausgabe'])) {
            return ['success' => false, 'message' => 'Ungueltiger Typ. Erlaubt: einnahme, ausgabe.'];
        }

        $amount = abs((float)($data['amount'] ?? 0));
        if ($amount <= 0) {
            return ['success' => false, 'message' => 'Betrag muss groesser als 0 sein.'];
        }

        $entryDate = $data['entry_date'] ?? date('Y-m-d');
        $description = trim($data['description'] ?? '');
        if (empty($description)) {
            return ['success' => false, 'message' => 'Beschreibung ist erforderlich.'];
        }

        $receiptNumber = !empty($data['receipt_number'])
            ? trim($data['receipt_number'])
            : $this->generateReceiptNumber($instanceId, $entryDate);

        $insertData = [
            'instances_id'   => $instanceId,
            'entry_date'     => $entryDate,
            'description'    => $description,
            'amount'         => $amount,
            'type'           => $type,
            'category'       => $data['category'] ?? null,
            'receipt_number' => $receiptNumber,
            'payment_method' => $data['payment_method'] ?? null,
            'notes'          => $data['notes'] ?? null,
            'created_at'     => date('Y-m-d H:i:s'),
        ];

        $id = $this->db->insert('kassenbuch_entries', $insertData);
        if (!$id) {
            return ['success' => false, 'message' => 'Datenbankfehler beim Speichern.'];
        }

        return ['success' => true, 'id' => $id, 'receipt_number' => $receiptNumber];
    }

    /**
     * Eintraege mit laufendem Saldo abrufen
     */
    public function getEntries(int $instanceId, string $dateFrom, string $dateTo): array
    {
        // Vortragssaldo berechnen (Saldo vor dateFrom)
        $previousBalance = $this->getBalance($instanceId, date('Y-m-d', strtotime($dateFrom . ' -1 day')));

        // Eintraege im Zeitraum laden
        $this->db->where('instances_id', $instanceId);
        $this->db->where('entry_date', $dateFrom, '>=');
        $this->db->where('entry_date', $dateTo, '<=');
        $this->db->orderBy('entry_date', 'ASC');
        $this->db->orderBy('id', 'ASC');
        $entries = $this->db->get('kassenbuch_entries') ?: [];

        // Laufenden Saldo berechnen
        $runningBalance = $previousBalance;
        foreach ($entries as &$entry) {
            if ($entry['type'] === 'einnahme') {
                $runningBalance += (float)$entry['amount'];
            } else {
                $runningBalance -= (float)$entry['amount'];
            }
            $entry['saldo'] = round($runningBalance, 2);
        }
        unset($entry);

        return [
            'entries'          => $entries,
            'previous_balance' => round($previousBalance, 2),
            'final_balance'    => round($runningBalance, 2),
        ];
    }

    /**
     * Eintrag aktualisieren
     */
    public function updateEntry(int $entryId, array $data): array
    {
        $this->db->where('id', $entryId);
        $entry = $this->db->getOne('kassenbuch_entries');
        if (!$entry) {
            return ['success' => false, 'message' => 'Eintrag nicht gefunden.'];
        }

        $updateData = [];

        if (isset($data['entry_date'])) $updateData['entry_date'] = $data['entry_date'];
        if (isset($data['description'])) $updateData['description'] = trim($data['description']);
        if (isset($data['amount'])) $updateData['amount'] = abs((float)$data['amount']);
        if (isset($data['type']) && in_array($data['type'], ['einnahme', 'ausgabe'])) {
            $updateData['type'] = $data['type'];
        }
        if (isset($data['category'])) $updateData['category'] = $data['category'];
        if (isset($data['receipt_number'])) $updateData['receipt_number'] = $data['receipt_number'];
        if (isset($data['payment_method'])) $updateData['payment_method'] = $data['payment_method'];
        if (isset($data['notes'])) $updateData['notes'] = $data['notes'];

        if (empty($updateData)) {
            return ['success' => false, 'message' => 'Keine Aenderungen angegeben.'];
        }

        $this->db->where('id', $entryId);
        $result = $this->db->update('kassenbuch_entries', $updateData);

        return ['success' => (bool)$result];
    }

    /**
     * Eintrag loeschen (nur wenn nicht gesperrt/archiviert)
     */
    public function deleteEntry(int $entryId): array
    {
        $this->db->where('id', $entryId);
        $entry = $this->db->getOne('kassenbuch_entries');
        if (!$entry) {
            return ['success' => false, 'message' => 'Eintrag nicht gefunden.'];
        }

        $this->db->where('id', $entryId);
        $result = $this->db->delete('kassenbuch_entries');

        return ['success' => (bool)$result];
    }

    /**
     * Kassenbestand bis zu einem bestimmten Datum
     */
    public function getBalance(int $instanceId, string $date): float
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('entry_date', $date, '<=');
        $this->db->where('type', 'einnahme');
        $einnahmen = $this->db->getOne('kassenbuch_entries', 'COALESCE(SUM(amount), 0) as total');
        $totalEinnahmen = (float)($einnahmen['total'] ?? 0);

        $this->db->where('instances_id', $instanceId);
        $this->db->where('entry_date', $date, '<=');
        $this->db->where('type', 'ausgabe');
        $ausgaben = $this->db->getOne('kassenbuch_entries', 'COALESCE(SUM(amount), 0) as total');
        $totalAusgaben = (float)($ausgaben['total'] ?? 0);

        return round($totalEinnahmen - $totalAusgaben, 2);
    }

    /**
     * Tageszusammenfassung
     */
    public function getDailySummary(int $instanceId, string $date): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('entry_date', $date);
        $this->db->where('type', 'einnahme');
        $ein = $this->db->getOne('kassenbuch_entries', 'COALESCE(SUM(amount), 0) as total, COUNT(*) as count');

        $this->db->where('instances_id', $instanceId);
        $this->db->where('entry_date', $date);
        $this->db->where('type', 'ausgabe');
        $aus = $this->db->getOne('kassenbuch_entries', 'COALESCE(SUM(amount), 0) as total, COUNT(*) as count');

        $einnahmen = (float)($ein['total'] ?? 0);
        $ausgaben = (float)($aus['total'] ?? 0);

        return [
            'date'              => $date,
            'einnahmen'         => round($einnahmen, 2),
            'ausgaben'          => round($ausgaben, 2),
            'saldo'             => round($einnahmen - $ausgaben, 2),
            'anzahl_einnahmen'  => (int)($ein['count'] ?? 0),
            'anzahl_ausgaben'   => (int)($aus['count'] ?? 0),
            'kassenbestand'     => $this->getBalance($instanceId, $date),
        ];
    }

    /**
     * Monatszusammenfassung mit Summen
     */
    public function getMonthlySummary(int $instanceId, int $year, int $month): array
    {
        $dateFrom = sprintf('%04d-%02d-01', $year, $month);
        $dateTo = date('Y-m-t', strtotime($dateFrom));

        // Vortragssaldo
        $previousBalance = $this->getBalance($instanceId, date('Y-m-d', strtotime($dateFrom . ' -1 day')));

        // Summen im Monat
        $this->db->where('instances_id', $instanceId);
        $this->db->where('entry_date', $dateFrom, '>=');
        $this->db->where('entry_date', $dateTo, '<=');
        $this->db->where('type', 'einnahme');
        $ein = $this->db->getOne('kassenbuch_entries', 'COALESCE(SUM(amount), 0) as total, COUNT(*) as count');

        $this->db->where('instances_id', $instanceId);
        $this->db->where('entry_date', $dateFrom, '>=');
        $this->db->where('entry_date', $dateTo, '<=');
        $this->db->where('type', 'ausgabe');
        $aus = $this->db->getOne('kassenbuch_entries', 'COALESCE(SUM(amount), 0) as total, COUNT(*) as count');

        $einnahmen = (float)($ein['total'] ?? 0);
        $ausgaben = (float)($aus['total'] ?? 0);

        // Summen nach Kategorie
        $sql = "SELECT category, type, SUM(amount) as total, COUNT(*) as count
                FROM kassenbuch_entries
                WHERE instances_id = ?
                  AND entry_date >= ?
                  AND entry_date <= ?
                GROUP BY category, type";

        $categoryRows = $this->db->rawQuery($sql, [$instanceId, $dateFrom, $dateTo]) ?: [];

        return [
            'year'              => $year,
            'month'             => $month,
            'previous_balance'  => round($previousBalance, 2),
            'einnahmen'         => round($einnahmen, 2),
            'ausgaben'          => round($ausgaben, 2),
            'saldo'             => round($einnahmen - $ausgaben, 2),
            'final_balance'     => round($previousBalance + $einnahmen - $ausgaben, 2),
            'anzahl_einnahmen'  => (int)($ein['count'] ?? 0),
            'anzahl_ausgaben'   => (int)($aus['count'] ?? 0),
            'categories'        => $categoryRows,
        ];
    }

    /**
     * CSV-Export fuer Steuerberater
     */
    public function exportCsv(int $instanceId, int $year, int $month): array
    {
        $dateFrom = sprintf('%04d-%02d-01', $year, $month);
        $dateTo = date('Y-m-t', strtotime($dateFrom));

        $result = $this->getEntries($instanceId, $dateFrom, $dateTo);
        $entries = $result['entries'];
        $previousBalance = $result['previous_balance'];

        $monthNames = [
            1 => 'Januar', 2 => 'Februar', 3 => 'Maerz', 4 => 'April',
            5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
        ];

        $lines = [];
        // Header
        $lines[] = implode(';', [
            'Datum',
            'Belegnummer',
            'Beschreibung',
            'Einnahme',
            'Ausgabe',
            'Saldo',
            'Kategorie',
            'Zahlungsart',
            'Notizen',
        ]);

        // Vortrag
        $lines[] = implode(';', [
            date('d.m.Y', strtotime($dateFrom)),
            '',
            '"Vortrag"',
            '',
            '',
            $this->formatDecimal($previousBalance),
            '',
            '',
            '',
        ]);

        $totalEinnahmen = 0;
        $totalAusgaben = 0;

        foreach ($entries as $entry) {
            $einnahme = '';
            $ausgabe = '';
            if ($entry['type'] === 'einnahme') {
                $einnahme = $this->formatDecimal($entry['amount']);
                $totalEinnahmen += (float)$entry['amount'];
            } else {
                $ausgabe = $this->formatDecimal($entry['amount']);
                $totalAusgaben += (float)$entry['amount'];
            }

            $lines[] = implode(';', [
                date('d.m.Y', strtotime($entry['entry_date'])),
                $this->csvField($entry['receipt_number'] ?? ''),
                $this->csvField($entry['description']),
                $einnahme,
                $ausgabe,
                $this->formatDecimal($entry['saldo']),
                $this->csvField($entry['category'] ?? ''),
                $this->csvField($entry['payment_method'] ?? ''),
                $this->csvField($entry['notes'] ?? ''),
            ]);
        }

        // Summenzeile
        $lines[] = implode(';', [
            '',
            '',
            '"Summe ' . $monthNames[$month] . ' ' . $year . '"',
            $this->formatDecimal($totalEinnahmen),
            $this->formatDecimal($totalAusgaben),
            $this->formatDecimal($result['final_balance']),
            '',
            '',
            '',
        ]);

        $filename = sprintf('Kassenbuch_%04d_%02d.csv', $year, $month);
        $csv = implode("\r\n", $lines);

        return [
            'csv'          => $csv,
            'filename'     => $filename,
            'record_count' => count($entries),
        ];
    }

    /**
     * Automatische Belegnummer generieren: KB-YYYY-NNNN
     */
    private function generateReceiptNumber(int $instanceId, string $date): string
    {
        $year = date('Y', strtotime($date));
        $prefix = 'KB-' . $year . '-';

        $this->db->where('instances_id', $instanceId);
        $this->db->where('receipt_number', $prefix . '%', 'LIKE');
        $this->db->orderBy('receipt_number', 'DESC');
        $last = $this->db->getOne('kassenbuch_entries', 'receipt_number');

        $nextNum = 1;
        if ($last && !empty($last['receipt_number'])) {
            $parts = explode('-', $last['receipt_number']);
            $lastNum = (int)end($parts);
            $nextNum = $lastNum + 1;
        }

        return $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Verfuegbare Kategorien zurueckgeben
     */
    public function getCategories(): array
    {
        return self::CATEGORIES;
    }

    private function formatDecimal(float $value): string
    {
        return number_format($value, 2, ',', '');
    }

    private function csvField(string $value): string
    {
        return '"' . str_replace('"', '""', $value) . '"';
    }
}
