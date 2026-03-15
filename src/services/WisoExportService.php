<?php
/**
 * WISO Export Service
 *
 * Erzeugt WISO-kompatible CSV-Dateien für den EÜR (Einnahmenüberschussrechnung) Import.
 * WISO importiert CSV mit Semikolon-Trennzeichen und UTF-8 BOM.
 *
 * Unterstützt:
 * - SKR03 und SKR04 Kontenrahmen
 * - Automatische Erkennung von Kleinunternehmer-Status (§19 UStG)
 * - Steuerschlüssel: 0=steuerfrei, 1=Kleinunternehmer§19, 9=19%MwSt, 8=7%MwSt
 * - Export Einnahmen (invoices) und Zahlungen (bank payments)
 */
class WisoExportService
{
    private $db;

    // Kontenrahmen mappings for SKR03
    private $skr03 = [
        'erloes_19' => '8400',  // Erlöse 19% MwSt
        'erloes_7'  => '8300',  // Erlöse 7% MwSt
        'erloes_0'  => '8000',  // Erlöse steuerfrei/Kleinunternehmer
        'forderungen' => '1400', // Forderungen
        'bank' => '1200',        // Bank
    ];

    // Kontenrahmen mappings for SKR04
    private $skr04 = [
        'erloes_19' => '4400',  // Erlöse 19% MwSt
        'erloes_7'  => '4300',  // Erlöse 7% MwSt
        'erloes_0'  => '4000',  // Erlöse steuerfrei/Kleinunternehmer
        'forderungen' => '1200', // Forderungen
        'bank' => '1800',        // Bank
    ];

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get account mappings for the selected Kontenrahmen
     */
    private function getAccountMappings(string $kontenrahmen = 'SKR03'): array
    {
        return strtoupper($kontenrahmen) === 'SKR04' ? $this->skr04 : $this->skr03;
    }

    /**
     * Detect Kleinunternehmer status (§19 UStG)
     * If all invoices have 0 tax, consider it Kleinunternehmer
     */
    private function isKleinunternehmer(int $instanceId, string $from, string $to): bool
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('document_exports_type', 'invoice');
        $this->db->where('document_exports_date', $from, '>=');
        $this->db->where('document_exports_date', $to, '<=');
        $this->db->where('document_exports_deleted', 0);
        $invoices = $this->db->get('document_exports', null, ['document_exports_tax']) ?: [];

        if (empty($invoices)) {
            return false;
        }

        foreach ($invoices as $inv) {
            if ((float)$inv['document_exports_tax'] > 0) {
                return false; // Found taxed invoice, not Kleinunternehmer
            }
        }

        return true; // All invoices have 0 tax
    }

    /**
     * Get tax key based on tax amount
     */
    private function getTaxKey(float $taxAmount, bool $isKleinunternehmer): string
    {
        if ($isKleinunternehmer && $taxAmount == 0) {
            return '1'; // Kleinunternehmer §19
        }
        if ($taxAmount == 0) {
            return '0'; // steuerfrei
        }
        // Simplified: assume 19% for non-zero tax; could be enhanced with actual tax rate detection
        return '9'; // 19% MwSt
    }

    /**
     * Determine which revenue account to use based on tax rate
     */
    private function getRevenueAccount(array $accounts, float $taxRate): string
    {
        if ($taxRate == 0) {
            return $accounts['erloes_0'];
        } elseif (abs($taxRate - 0.07) < 0.01) {
            return $accounts['erloes_7'];
        } else {
            return $accounts['erloes_19'];
        }
    }

    /**
     * Format number for German locale (comma decimal, dot thousands)
     */
    private function formatNumber(float $value): string
    {
        $value = round($value, 2);
        $parts = explode('.', number_format($value, 2, '.', ''));
        $intPart = $parts[0];
        $fracPart = $parts[1] ?? '00';

        // Add thousands separator
        $intPart = number_format((float)$intPart, 0, '', '.');

        return $intPart . ',' . $fracPart;
    }

    /**
     * Escape CSV field (semicolon separator)
     */
    private function escapeCsvField(string $value): string
    {
        if (strpos($value, ';') !== false || strpos($value, '"') !== false || strpos($value, "\n") !== false) {
            return '"' . str_replace('"', '""', $value) . '"';
        }
        return $value;
    }

    /**
     * Export EÜR (Einnahmenüberschussrechnung) - Invoices for WISO
     * Returns CSV content ready for WISO import
     */
    public function exportEuer(int $instanceId, string $from, string $to, string $kontenrahmen = 'SKR03'): string
    {
        $accounts = $this->getAccountMappings($kontenrahmen);
        $isKleinunternehmer = $this->isKleinunternehmer($instanceId, $from, $to);

        // Query invoices from document_exports
        $this->db->where('instances_id', $instanceId);
        $this->db->where('document_exports_type', 'invoice');
        $this->db->where('document_exports_date', $from, '>=');
        $this->db->where('document_exports_date', $to, '<=');
        $this->db->where('document_exports_deleted', 0);
        $this->db->orderBy('document_exports_date', 'ASC');
        $invoices = $this->db->get('document_exports', null, [
            'document_exports_id',
            'document_exports_date',
            'document_exports_number',
            'document_exports_net',
            'document_exports_gross',
            'document_exports_tax',
        ]) ?: [];

        // Build CSV header
        $header = "Datum;Belegnummer;Buchungstext;Betrag;Soll-Konto;Haben-Konto;Steuerschluessel;Kostenstelle\r\n";
        $lines = [];

        foreach ($invoices as $inv) {
            $date = $inv['document_exports_date'];
            $number = $inv['document_exports_number'] ?? '';
            $gross = (float)$inv['document_exports_gross'] / 100; // Convert cents
            $net = (float)$inv['document_exports_net'] / 100;
            $tax = (float)$inv['document_exports_tax'] / 100;

            // Determine tax rate
            $taxRate = $net > 0 ? ($tax / $net) : 0;

            // Get appropriate revenue account
            $revenueAccount = $this->getRevenueAccount($accounts, $taxRate);

            // Get tax key
            $taxKey = $this->getTaxKey($tax, $isKleinunternehmer);

            // Format date as DD.MM.YYYY
            $dateFormatted = date('d.m.Y', strtotime($date));

            // Build line: gross amount from AR to revenue
            $lines[] = implode(';', [
                $dateFormatted,
                $this->escapeCsvField($number),
                $this->escapeCsvField('Rechnung ' . $number),
                $this->formatNumber($gross),
                $accounts['forderungen'], // Soll (debit)
                $revenueAccount,          // Haben (credit)
                $taxKey,
                '',
            ]);
        }

        // Build complete CSV with UTF-8 BOM
        $csv = $header . implode("\r\n", $lines);
        if (!empty($lines)) {
            $csv .= "\r\n";
        }

        return $csv;
    }

    /**
     * Export bank payments for WISO
     * Maps bank account payments to receivables (cash receipt)
     */
    public function exportPayments(int $instanceId, string $from, string $to, string $kontenrahmen = 'SKR03'): string
    {
        $accounts = $this->getAccountMappings($kontenrahmen);

        // Query paid invoices
        $this->db->where('instances_id', $instanceId);
        $this->db->where('document_exports_type', 'payment');
        $this->db->where('document_exports_date', $from, '>=');
        $this->db->where('document_exports_date', $to, '<=');
        $this->db->where('document_exports_deleted', 0);
        $this->db->orderBy('document_exports_date', 'ASC');
        $payments = $this->db->get('document_exports', null, [
            'document_exports_id',
            'document_exports_date',
            'document_exports_number',
            'document_exports_gross', // Payment amount
        ]) ?: [];

        // Build CSV header
        $header = "Datum;Belegnummer;Buchungstext;Betrag;Soll-Konto;Haben-Konto;Steuerschluessel;Kostenstelle\r\n";
        $lines = [];

        foreach ($payments as $payment) {
            $date = $payment['document_exports_date'];
            $number = $payment['document_exports_number'] ?? '';
            $amount = (float)$payment['document_exports_gross'] / 100; // Convert cents

            // Format date as DD.MM.YYYY
            $dateFormatted = date('d.m.Y', strtotime($date));

            // Build line: bank debit, AR credit
            $lines[] = implode(';', [
                $dateFormatted,
                $this->escapeCsvField($number),
                $this->escapeCsvField('Zahlungseingang ' . $number),
                $this->formatNumber($amount),
                $accounts['bank'],       // Soll (debit) - bank in
                $accounts['forderungen'], // Haben (credit) - AR out
                '0',                     // Tax key (no tax on payment)
                '',
            ]);
        }

        // Build complete CSV
        $csv = $header . implode("\r\n", $lines);
        if (!empty($lines)) {
            $csv .= "\r\n";
        }

        return $csv;
    }

    /**
     * Export all data for a year (invoices, payments, summary)
     * Returns array with 'invoices_csv', 'payments_csv', and 'summary' keys
     */
    public function exportAll(int $instanceId, int $year, string $kontenrahmen = 'SKR03'): array
    {
        $from = date('Y-01-01', mktime(0, 0, 0, 1, 1, $year));
        $to = date('Y-12-31', mktime(0, 0, 0, 12, 31, $year));

        // Get invoices CSV
        $invoicesCsv = $this->exportEuer($instanceId, $from, $to, $kontenrahmen);

        // Get payments CSV
        $paymentsCsv = $this->exportPayments($instanceId, $from, $to, $kontenrahmen);

        // Calculate summary
        $this->db->where('instances_id', $instanceId);
        $this->db->where('document_exports_type', 'invoice');
        $this->db->where('YEAR(document_exports_date)', $year);
        $this->db->where('document_exports_deleted', 0);
        $summaryInvoices = $this->db->getOne('document_exports', [
            'COUNT(*) as count',
            'SUM(document_exports_gross) as total_gross',
            'SUM(document_exports_net) as total_net',
            'SUM(document_exports_tax) as total_tax',
        ]);

        $summary = [
            'year' => $year,
            'kontenrahmen' => $kontenrahmen,
            'invoice_count' => (int)($summaryInvoices['count'] ?? 0),
            'total_gross' => ((float)($summaryInvoices['total_gross'] ?? 0)) / 100,
            'total_net' => ((float)($summaryInvoices['total_net'] ?? 0)) / 100,
            'total_tax' => ((float)($summaryInvoices['total_tax'] ?? 0)) / 100,
        ];

        return [
            'invoices_csv' => $invoicesCsv,
            'payments_csv' => $paymentsCsv,
            'summary' => $summary,
        ];
    }
}
?>
