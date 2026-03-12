<?php
/**
 * DATEV-Export Service
 *
 * Erzeugt DATEV-kompatible CSV-Dateien im Buchungsstapel-Format
 * fuer den Import in DATEV, lexoffice, sevDesk o.ae.
 *
 * Format: DATEV Buchungsstapel (EXTF)
 * Kontenrahmen: SKR03 (Standard) oder SKR04
 */
class DatevExportService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Export Buchungsstapel (posting batch) for a period
     */
    public function exportBuchungen(int $instanceId, string $periodFrom, string $periodTo, int $userId): array
    {
        // Load instance settings
        $this->db->where('instances_id', $instanceId);
        $instance = $this->db->getOne('instances');

        // Load account mappings
        $accounts = $this->getAccountMappings($instanceId);

        // Load all invoices in the period
        $this->db->where('dl.instances_id', $instanceId);
        $this->db->where('dl.doc_type', ['invoice', 'credit_note'], 'IN');
        $this->db->where('dl.status', ['sent', 'paid', 'overdue', 'reminded'], 'IN');
        $this->db->where('DATE(dl.created_at)', $periodFrom, '>=');
        $this->db->where('DATE(dl.created_at)', $periodTo, '<=');
        $this->db->join('projects p', 'dl.projects_id=p.projects_id', 'LEFT');
        $this->db->join('clients c', 'p.clients_id=c.clients_id', 'LEFT');
        $this->db->orderBy('dl.created_at', 'ASC');
        $documents = $this->db->get('document_lifecycle dl', null, [
            'dl.*', 'p.projects_name', 'c.clients_name', 'c.clients_customerNumber'
        ]) ?: [];

        // Load payments received in the period
        $this->db->where('dl.instances_id', $instanceId);
        $this->db->where('dl.doc_type', 'invoice');
        $this->db->where('dl.paid_date', $periodFrom, '>=');
        $this->db->where('dl.paid_date', $periodTo, '<=');
        $this->db->where('dl.status', 'paid');
        $this->db->join('projects p', 'dl.projects_id=p.projects_id', 'LEFT');
        $this->db->join('clients c', 'p.clients_id=c.clients_id', 'LEFT');
        $paidInvoices = $this->db->get('document_lifecycle dl', null, [
            'dl.*', 'p.projects_name', 'c.clients_name', 'c.clients_customerNumber'
        ]) ?: [];

        // Build DATEV header
        $beraternr = $instance['instances_datevBeraternr'] ?? '0001';
        $mandantennr = $instance['instances_datevMandantennr'] ?? '00001';
        $wjBeginn = $instance['instances_datevWjBeginn'] ?? date('Y') . '0101';
        $kontenrahmen = $instance['instances_datevKontenrahmen'] ?? 'SKR03';

        $header = $this->buildDatevHeader($beraternr, $mandantennr, $wjBeginn, $periodFrom, $periodTo);
        $columnHeader = $this->getColumnHeader();

        // Build booking lines
        $lines = [];

        // Ausgangsrechnungen (outgoing invoices)
        foreach ($documents as $doc) {
            $isCredit = $doc['doc_type'] === 'credit_note';
            $debitAccount = $accounts['receivable']['account_number'] ?? '1400';
            $creditAccount = $this->getRevenueAccount($accounts, $instance);
            $taxKey = $this->getTaxKey($accounts, $instance);

            $lines[] = $this->buildBookingLine([
                'amount'         => abs((float)$doc['gross_amount']),
                'debit_credit'   => $isCredit ? 'H' : 'S',
                'debit_account'  => $isCredit ? $creditAccount : $debitAccount,
                'credit_account' => $isCredit ? $debitAccount : $creditAccount,
                'tax_key'        => $taxKey,
                'date'           => $doc['created_at'],
                'doc_number'     => $doc['doc_number'],
                'description'    => ($isCredit ? 'Gutschrift ' : 'Rechnung ') . $doc['doc_number'],
                'client_number'  => $doc['clients_customerNumber'] ?? '',
                'client_name'    => $doc['clients_name'] ?? '',
            ]);
        }

        // Zahlungseingaenge (incoming payments)
        foreach ($paidInvoices as $doc) {
            $bankAccount = $accounts['bank']['account_number'] ?? '1200';
            $receivableAccount = $accounts['receivable']['account_number'] ?? '1400';

            $lines[] = $this->buildBookingLine([
                'amount'         => abs((float)$doc['paid_amount'] ?: (float)$doc['gross_amount']),
                'debit_credit'   => 'S',
                'debit_account'  => $bankAccount,
                'credit_account' => $receivableAccount,
                'tax_key'        => '0',
                'date'           => $doc['paid_date'],
                'doc_number'     => $doc['doc_number'],
                'description'    => 'Zahlung ' . $doc['doc_number'],
                'client_number'  => $doc['clients_customerNumber'] ?? '',
                'client_name'    => $doc['clients_name'] ?? '',
            ]);
        }

        // Build CSV
        $csv = $header . "\r\n" . $columnHeader . "\r\n";
        foreach ($lines as $line) {
            $csv .= $line . "\r\n";
        }

        // Log export
        $filename = "DATEV_Buchungen_{$periodFrom}_{$periodTo}.csv";
        $this->db->insert('datev_exports', [
            'instances_id' => $instanceId,
            'export_type'  => 'buchungen',
            'period_from'  => $periodFrom,
            'period_to'    => $periodTo,
            'filename'     => $filename,
            'record_count' => count($lines),
            'created_by'   => $userId,
        ]);

        return [
            'csv'          => $csv,
            'filename'     => $filename,
            'record_count' => count($lines),
            'encoding'     => 'Windows-1252',
        ];
    }

    /**
     * Export Debitoren-Stammdaten (debtor master data)
     */
    public function exportStammdaten(int $instanceId, int $userId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('clients_deleted', 0);
        $this->db->orderBy('clients_name', 'ASC');
        $clients = $this->db->get('clients') ?: [];

        $lines = [];
        foreach ($clients as $c) {
            $custNr = $c['clients_customerNumber'] ?: ('KD' . str_pad($c['clients_id'], 5, '0', STR_PAD_LEFT));
            $lines[] = implode(';', [
                $this->csvField($custNr),
                $this->csvField(''),  // Unternehmen
                $this->csvField($c['clients_name']),
                $this->csvField(''),  // Vorname
                $this->csvField(''),  // Name
                $this->csvField($c['clients_address'] ?? ''),
                $this->csvField(''),  // PLZ
                $this->csvField(''),  // Ort
                $this->csvField(''),  // Land
                $this->csvField($c['clients_phone'] ?? ''),
                $this->csvField($c['clients_email'] ?? ''),
                $this->csvField($c['clients_vatId'] ?? ''),
            ]);
        }

        $filename = "DATEV_Debitoren_{$instanceId}.csv";
        $this->db->insert('datev_exports', [
            'instances_id' => $instanceId,
            'export_type'  => 'stammdaten',
            'period_from'  => date('Y-01-01'),
            'period_to'    => date('Y-12-31'),
            'filename'     => $filename,
            'record_count' => count($lines),
            'created_by'   => $userId,
        ]);

        $header = "Konto;Unternehmen;Name;Vorname;Namenszusatz;Strasse;PLZ;Ort;Land;Telefon;E-Mail;USt-IdNr\r\n";
        $csv = $header . implode("\r\n", $lines);

        return [
            'csv'          => $csv,
            'filename'     => $filename,
            'record_count' => count($lines),
            'encoding'     => 'Windows-1252',
        ];
    }

    /**
     * Get account mappings for an instance
     */
    public function getAccountMappings(int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        $rows = $this->db->get('datev_account_mapping') ?: [];
        $result = [];
        foreach ($rows as $row) {
            $result[$row['account_type']] = $row;
        }
        return $result;
    }

    private function buildDatevHeader(string $beraternr, string $mandantennr, string $wjBeginn, string $from, string $to): string
    {
        // DATEV EXTF Header
        $fromD = date('Ymd', strtotime($from));
        $toD = date('Ymd', strtotime($to));
        $now = date('YmdHis');

        return "\"EXTF\";700;21;\"Buchungsstapel\";7;{$now};;\"{$beraternr}\";\"{$mandantennr}\";{$wjBeginn};4;\"{$fromD}\";\"{$toD}\";\"RMS Export\";;\"\";1;0;0;\"EUR\";;;;;;;;;";
    }

    private function getColumnHeader(): string
    {
        return "Umsatz (ohne Soll/Haben-Kz);Soll/Haben-Kennzeichen;WKZ Umsatz;Kurs;Basisumsatz;WKZ Basisumsatz;Konto;Gegenkonto (ohne BU-Schluessel);BU-Schluessel;Belegdatum;Belegfeld 1;Belegfeld 2;Skonto;Buchungstext;Postensperre;Diverse Adressnummer;Geschaeftspartnerbank;Sachverhalt;Zinssperre;Beleglink";
    }

    private function buildBookingLine(array $data): string
    {
        $amount = number_format($data['amount'], 2, ',', '');
        $date = date('dm', strtotime($data['date']));

        return implode(';', [
            $amount,                                    // Umsatz
            $data['debit_credit'],                      // Soll/Haben
            '""',                                       // WKZ
            '""',                                       // Kurs
            '""',                                       // Basisumsatz
            '""',                                       // WKZ Basis
            $data['debit_account'],                     // Konto
            $data['credit_account'],                    // Gegenkonto
            $data['tax_key'],                           // BU-Schluessel
            $date,                                      // Belegdatum
            $this->csvField($data['doc_number']),       // Belegfeld 1
            '""',                                       // Belegfeld 2
            '""',                                       // Skonto
            $this->csvField($data['description']),      // Buchungstext
            '""',                                       // Postensperre
            $this->csvField($data['client_number']),    // Adressnummer
            '""',                                       // Bank
            '""',                                       // Sachverhalt
            '""',                                       // Zinssperre
            '""',                                       // Beleglink
        ]);
    }

    private function getRevenueAccount(array $accounts, array $instance): string
    {
        $isKur = (bool)($instance['instances_kurEnabled'] ?? true);
        if ($isKur && isset($accounts['revenue_kur'])) {
            return $accounts['revenue_kur']['account_number'];
        }
        return $accounts['revenue']['account_number'] ?? '8400';
    }

    private function getTaxKey(array $accounts, array $instance): string
    {
        $isKur = (bool)($instance['instances_kurEnabled'] ?? true);
        if ($isKur) return '0';
        return $accounts['revenue']['tax_key'] ?? '3';
    }

    private function csvField(string $value): string
    {
        return '"' . str_replace('"', '""', $value) . '"';
    }
}
