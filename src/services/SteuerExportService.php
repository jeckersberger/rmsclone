<?php
/**
 * Steuer-Export Service
 *
 * Erzeugt einfache CSV-Dateien für WISO Steuer und die
 * eigenstaendige Steuererklaerung (EÜR / Anlage EÜR).
 *
 * Exportiert:
 * - Einnahmen (Zufluss-Prinzip)
 * - Ausgaben
 * - Kundenliste
 * - EÜR-Zusammenfassung (Anlage EÜR Zeilen)
 */
class SteuerExportService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Export Einnahmen als CSV (für WISO Import)
     */
    public function exportEinnahmen(int $instanceId, string $from, string $to): array
    {
        // Bezahlte Rechnungen im Zeitraum (Zufluss-Prinzip: paid_date zählt)
        $this->db->where('dl.instances_id', $instanceId);
        $this->db->where('dl.doc_type', ['invoice', 'credit_note'], 'IN');
        $this->db->where('dl.status', 'paid');
        $this->db->where('DATE(dl.paid_date)', $from, '>=');
        $this->db->where('DATE(dl.paid_date)', $to, '<=');
        $this->db->join('projects p', 'dl.projects_id=p.projects_id', 'LEFT');
        $this->db->join('clients c', 'p.clients_id=c.clients_id', 'LEFT');
        $this->db->orderBy('dl.paid_date', 'ASC');
        $docs = $this->db->get('document_lifecycle dl', null, [
            'dl.*', 'p.projects_name', 'c.clients_name'
        ]) ?: [];

        $header = "Datum;Rechnungsnr;Kunde;Projekt;Netto;Brutto;Zahlungseingang;Bemerkung\r\n";
        $lines = [];
        $totalNet = 0;
        $totalGross = 0;

        foreach ($docs as $doc) {
            $isCredit = $doc['doc_type'] === 'credit_note';
            $net = (float)($doc['net_amount'] ?? 0);
            $gross = (float)($doc['gross_amount'] ?? $net);
            if ($isCredit) { $net = -$net; $gross = -$gross; }

            $totalNet += $net;
            $totalGross += $gross;

            $lines[] = implode(';', [
                date('d.m.Y', strtotime($doc['paid_date'])),
                $this->esc($doc['doc_number'] ?? ''),
                $this->esc($doc['clients_name'] ?? ''),
                $this->esc($doc['projects_name'] ?? ''),
                $this->num($net),
                $this->num($gross),
                date('d.m.Y', strtotime($doc['paid_date'])),
                $isCredit ? 'Gutschrift' : 'Rechnung',
            ]);
        }

        // Summenzeile
        $lines[] = implode(';', ['', '', '', 'SUMME', $this->num($totalNet), $this->num($totalGross), '', '']);

        $csv = $header . implode("\r\n", $lines);
        $filename = "Einnahmen_{$from}_{$to}.csv";

        return ['csv' => $csv, 'filename' => $filename, 'count' => count($docs), 'total_net' => $totalNet, 'total_gross' => $totalGross];
    }

    /**
     * Export Ausgaben als CSV
     */
    public function exportAusgaben(int $instanceId, string $from, string $to): array
    {
        // Manuelle EÜR-Buchungen (Ausgaben)
        $this->db->where('eb.instances_id', $instanceId);
        $this->db->where('DATE(eb.booking_date)', $from, '>=');
        $this->db->where('DATE(eb.booking_date)', $to, '<=');
        $this->db->join('euer_categories ec', 'eb.euer_categories_id=ec.id', 'LEFT');
        $this->db->where('ec.type', 'expense');
        $this->db->orderBy('eb.booking_date', 'ASC');
        $bookings = $this->db->get('euer_bookings eb', null, [
            'eb.*', 'ec.name AS category_name', 'ec.euer_line'
        ]) ?: [];

        $header = "Datum;Kategorie;Beschreibung;Betrag;Beleg/Referenz;EÜR-Zeile\r\n";
        $lines = [];
        $total = 0;

        foreach ($bookings as $b) {
            $amount = (float)($b['amount'] ?? 0);
            $total += $amount;
            $lines[] = implode(';', [
                date('d.m.Y', strtotime($b['booking_date'])),
                $this->esc($b['category_name'] ?? ''),
                $this->esc($b['description'] ?? ''),
                $this->num($amount),
                $this->esc($b['reference'] ?? ''),
                $b['euer_line'] ?? '',
            ]);
        }

        $lines[] = implode(';', ['', '', 'SUMME', $this->num($total), '', '']);

        $csv = $header . implode("\r\n", $lines);
        $filename = "Ausgaben_{$from}_{$to}.csv";

        return ['csv' => $csv, 'filename' => $filename, 'count' => count($bookings), 'total' => $total];
    }

    /**
     * Export Kundenliste als CSV
     */
    public function exportKunden(int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('clients_deleted', 0);
        $this->db->orderBy('clients_name', 'ASC');
        $clients = $this->db->get('clients') ?: [];

        $header = "Kundennr;Name;Adresse;E-Mail;Telefon;USt-IdNr\r\n";
        $lines = [];

        foreach ($clients as $c) {
            $lines[] = implode(';', [
                $this->esc($c['clients_customerNumber'] ?? ''),
                $this->esc($c['clients_name'] ?? ''),
                $this->esc($c['clients_address'] ?? ''),
                $this->esc($c['clients_email'] ?? ''),
                $this->esc($c['clients_phone'] ?? ''),
                $this->esc($c['clients_vatId'] ?? ''),
            ]);
        }

        $csv = $header . implode("\r\n", $lines);
        $filename = "Kundenliste.csv";

        return ['csv' => $csv, 'filename' => $filename, 'count' => count($clients)];
    }

    /**
     * Export EÜR-Zusammenfassung (nach Anlage-EÜR-Zeilen gruppiert)
     * Direkt nutzbar für WISO Steuer EÜR-Eingabe
     */
    public function exportEuerZusammenfassung(int $instanceId, int $year): array
    {
        // Einnahmen aus bezahlten Rechnungen
        $this->db->where('dl.instances_id', $instanceId);
        $this->db->where('dl.doc_type', 'invoice');
        $this->db->where('dl.status', 'paid');
        $this->db->where('YEAR(dl.paid_date)', $year);
        $invoiceTotal = $this->db->getOne('document_lifecycle dl', 'SUM(dl.gross_amount) as total');
        $einnahmenGesamt = (float)($invoiceTotal['total'] ?? 0);

        // Ausgaben nach EÜR-Kategorie
        $this->db->where('eb.instances_id', $instanceId);
        $this->db->where('YEAR(eb.booking_date)', $year);
        $this->db->join('euer_categories ec', 'eb.euer_categories_id=ec.id', 'LEFT');
        $this->db->groupBy('ec.euer_line');
        $this->db->orderBy('ec.euer_line', 'ASC');
        $categories = $this->db->get('euer_bookings eb', null, [
            'ec.euer_line', 'ec.name AS category_name', 'ec.type',
            'SUM(eb.amount) AS total'
        ]) ?: [];

        $header = "EÜR-Zeile;Kategorie;Typ;Betrag\r\n";
        $lines = [];

        // Zeile 11: Betriebseinnahmen (Kleinunternehmer brutto = netto)
        $lines[] = implode(';', ['11', 'Betriebseinnahmen', 'Einnahme', $this->num($einnahmenGesamt)]);

        $totalAusgaben = 0;
        foreach ($categories as $cat) {
            $amount = (float)($cat['total'] ?? 0);
            if ($cat['type'] === 'expense') $totalAusgaben += $amount;
            $lines[] = implode(';', [
                $cat['euer_line'] ?? '',
                $this->esc($cat['category_name'] ?? ''),
                $cat['type'] === 'income' ? 'Einnahme' : 'Ausgabe',
                $this->num($amount),
            ]);
        }

        // Gewinn
        $gewinn = $einnahmenGesamt - $totalAusgaben;
        $lines[] = implode(';', ['', 'GEWINN/VERLUST', '', $this->num($gewinn)]);

        $csv = $header . implode("\r\n", $lines);
        $filename = "EUER_Zusammenfassung_{$year}.csv";

        return [
            'csv' => $csv,
            'filename' => $filename,
            'einnahmen' => $einnahmenGesamt,
            'ausgaben' => $totalAusgaben,
            'gewinn' => $gewinn,
        ];
    }

    private function esc(string $val): string
    {
        return '"' . str_replace('"', '""', $val) . '"';
    }

    private function num(float $val): string
    {
        return number_format($val, 2, ',', '');
    }
}
