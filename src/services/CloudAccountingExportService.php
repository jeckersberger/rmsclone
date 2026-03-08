<?php
/**
 * Export-Service fuer Cloud-Buchhaltungssoftware
 *
 * Erzeugt Export-Dateien fuer:
 * - lexoffice (JSON-Format fuer lexoffice Public API)
 * - sevDesk (CSV-Format fuer sevDesk Import)
 *
 * Die Exporte koennen heruntergeladen und manuell importiert
 * oder ueber die jeweilige API direkt uebertragen werden.
 */
class CloudAccountingExportService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Export Rechnungen im lexoffice-kompatiblen JSON-Format
     * Kompatibel mit lexoffice Public API v1 (POST /v1/invoices)
     */
    public function exportLexoffice(int $instanceId, string $periodFrom, string $periodTo): array
    {
        $instance = $this->loadInstance($instanceId);
        $documents = $this->loadDocuments($instanceId, $periodFrom, $periodTo);

        $invoices = [];
        foreach ($documents as $doc) {
            $client = $this->loadClient($doc['clients_id'] ?? 0);
            $items = $this->loadDocumentItems($doc['id']);

            $lineItems = [];
            foreach ($items as $item) {
                $lineItems[] = [
                    'type'          => 'custom',
                    'name'          => $item['description'] ?? $item['item_name'] ?? 'Position',
                    'quantity'      => (float)($item['quantity'] ?? 1),
                    'unitName'      => $item['unit'] ?? 'Stueck',
                    'unitPrice'     => [
                        'currency'          => 'EUR',
                        'netAmount'         => round((float)($item['net_amount'] ?? 0), 2),
                        'taxRatePercentage' => (float)($item['tax_rate'] ?? 19),
                    ],
                ];
            }

            // Fallback: Gesamtbetrag als einzelne Position
            if (empty($lineItems)) {
                $lineItems[] = [
                    'type'      => 'custom',
                    'name'      => 'Leistung gemaess Rechnung ' . $doc['doc_number'],
                    'quantity'  => 1,
                    'unitName'  => 'pauschal',
                    'unitPrice' => [
                        'currency'          => 'EUR',
                        'netAmount'         => round((float)($doc['net_amount'] ?? $doc['gross_amount'] ?? 0), 2),
                        'taxRatePercentage' => !empty($instance['instances_kurEnabled']) ? 0 : 19,
                    ],
                ];
            }

            $invoice = [
                'voucherDate'      => date('Y-m-d', strtotime($doc['created_at'])),
                'address'          => [
                    'name'         => $client['clients_name'] ?? 'Unbekannt',
                    'street'       => $client['clients_address'] ?? '',
                    'zip'          => $client['clients_postcode'] ?? '',
                    'city'         => $client['clients_city'] ?? '',
                    'countryCode'  => $client['clients_country'] ?? 'DE',
                ],
                'lineItems'        => $lineItems,
                'totalPrice'       => [
                    'currency'     => 'EUR',
                ],
                'taxConditions'    => [
                    'taxType'      => !empty($instance['instances_kurEnabled']) ? 'vatfree' : 'net',
                ],
                'title'            => 'Rechnung',
                'introduction'     => 'Rechnung Nr. ' . $doc['doc_number'],
                'remark'           => $doc['notes'] ?? '',
                'voucherNumber'    => $doc['doc_number'],
            ];

            if (!empty($doc['due_date'])) {
                $invoice['paymentConditions'] = [
                    'paymentTermLabel'    => 'Zahlbar bis ' . date('d.m.Y', strtotime($doc['due_date'])),
                    'paymentTermDuration' => max(1, (int)round((strtotime($doc['due_date']) - strtotime($doc['created_at'])) / 86400)),
                ];
            }

            if ($doc['doc_type'] === 'credit_note') {
                $invoice['title'] = 'Gutschrift';
                $invoice['introduction'] = 'Gutschrift Nr. ' . $doc['doc_number'];
            }

            $invoices[] = $invoice;
        }

        $filename = "lexoffice_export_{$periodFrom}_{$periodTo}.json";

        $this->logExport($instanceId, 'lexoffice', $periodFrom, $periodTo, $filename, count($invoices));

        return [
            'data'         => $invoices,
            'json'         => json_encode($invoices, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'filename'     => $filename,
            'record_count' => count($invoices),
            'format'       => 'json',
        ];
    }

    /**
     * Export Rechnungen im sevDesk-kompatiblen CSV-Format
     * Kompatibel mit sevDesk Rechnungs-Import
     */
    public function exportSevdesk(int $instanceId, string $periodFrom, string $periodTo): array
    {
        $instance = $this->loadInstance($instanceId);
        $documents = $this->loadDocuments($instanceId, $periodFrom, $periodTo);

        $header = implode(';', [
            'Rechnungsnummer',
            'Rechnungsdatum',
            'Faelligkeitsdatum',
            'Kundenname',
            'Kundenadresse',
            'Kunden-PLZ',
            'Kunden-Ort',
            'Kunden-Land',
            'Kunden-USt-IdNr',
            'Nettobetrag',
            'MwSt-Satz',
            'MwSt-Betrag',
            'Bruttobetrag',
            'Waehrung',
            'Status',
            'Zahlungsart',
            'Bezahlt am',
            'Buchungstext',
            'Typ',
        ]);

        $lines = [];
        foreach ($documents as $doc) {
            $client = $this->loadClient($doc['clients_id'] ?? 0);
            $isKur = !empty($instance['instances_kurEnabled']);
            $gross = (float)($doc['gross_amount'] ?? 0);
            $taxRate = $isKur ? 0 : 19;
            $net = $isKur ? $gross : round($gross / 1.19, 2);
            $tax = round($gross - $net, 2);

            if (!empty($doc['net_amount'])) {
                $net = (float)$doc['net_amount'];
                $tax = $gross - $net;
                $taxRate = $net > 0 ? round(($tax / $net) * 100, 0) : 0;
            }

            $statusMap = [
                'draft'    => 'Entwurf',
                'sent'     => 'Offen',
                'overdue'  => 'Ueberfaellig',
                'reminded' => 'Gemahnt',
                'paid'     => 'Bezahlt',
                'cancelled' => 'Storniert',
            ];

            $lines[] = implode(';', [
                $this->csvField($doc['doc_number']),
                date('d.m.Y', strtotime($doc['created_at'])),
                !empty($doc['due_date']) ? date('d.m.Y', strtotime($doc['due_date'])) : '',
                $this->csvField($client['clients_name'] ?? ''),
                $this->csvField($client['clients_address'] ?? ''),
                $this->csvField($client['clients_postcode'] ?? ''),
                $this->csvField($client['clients_city'] ?? ''),
                $this->csvField($client['clients_country'] ?? 'DE'),
                $this->csvField($client['clients_vatId'] ?? ''),
                number_format($net, 2, ',', ''),
                number_format($taxRate, 0),
                number_format($tax, 2, ',', ''),
                number_format($gross, 2, ',', ''),
                'EUR',
                $statusMap[$doc['status']] ?? $doc['status'],
                $doc['payment_method'] ?? '',
                !empty($doc['paid_date']) ? date('d.m.Y', strtotime($doc['paid_date'])) : '',
                $this->csvField('Rechnung ' . $doc['doc_number'] . ' - ' . ($client['clients_name'] ?? '')),
                $doc['doc_type'] === 'credit_note' ? 'Gutschrift' : 'Rechnung',
            ]);
        }

        $csv = $header . "\r\n" . implode("\r\n", $lines);
        $filename = "sevdesk_export_{$periodFrom}_{$periodTo}.csv";

        $this->logExport($instanceId, 'sevdesk', $periodFrom, $periodTo, $filename, count($lines));

        return [
            'csv'          => $csv,
            'filename'     => $filename,
            'record_count' => count($lines),
            'format'       => 'csv',
            'encoding'     => 'UTF-8',
        ];
    }

    /**
     * Export Kundenstammdaten fuer lexoffice (Kontakte-Format)
     */
    public function exportLexofficeContacts(int $instanceId): array
    {
        $clients = $this->loadClients($instanceId);

        $contacts = [];
        foreach ($clients as $c) {
            $contacts[] = [
                'version'  => 0,
                'roles'    => ['customer' => new \stdClass()],
                'company'  => [
                    'name'             => $c['clients_name'],
                    'taxNumber'        => $c['clients_taxNumber'] ?? '',
                    'vatRegistrationId' => $c['clients_vatId'] ?? '',
                    'contactPersons'   => [],
                ],
                'addresses' => [
                    'billing' => [[
                        'street'      => $c['clients_address'] ?? '',
                        'zip'         => $c['clients_postcode'] ?? '',
                        'city'        => $c['clients_city'] ?? '',
                        'countryCode' => $c['clients_country'] ?? 'DE',
                    ]],
                ],
                'emailAddresses' => [
                    'business' => [$c['clients_email'] ?? ''],
                ],
                'phoneNumbers' => [
                    'business' => [$c['clients_phone'] ?? ''],
                ],
                'note' => 'Import aus AdamRMS - Kundennr: ' . ($c['clients_customerNumber'] ?? $c['clients_id']),
            ];
        }

        $filename = "lexoffice_kontakte_export.json";
        return [
            'data'         => $contacts,
            'json'         => json_encode($contacts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'filename'     => $filename,
            'record_count' => count($contacts),
            'format'       => 'json',
        ];
    }

    /**
     * Export Kundenstammdaten fuer sevDesk (CSV)
     */
    public function exportSevdeskContacts(int $instanceId): array
    {
        $clients = $this->loadClients($instanceId);

        $header = implode(';', [
            'Kundennummer', 'Firmenname', 'Anrede', 'Vorname', 'Nachname',
            'Strasse', 'PLZ', 'Ort', 'Land', 'Telefon', 'E-Mail',
            'USt-IdNr', 'Steuernummer', 'IBAN', 'BIC', 'Bemerkung',
        ]);

        $lines = [];
        foreach ($clients as $c) {
            $lines[] = implode(';', [
                $this->csvField($c['clients_customerNumber'] ?? ''),
                $this->csvField($c['clients_name'] ?? ''),
                '',
                '',
                '',
                $this->csvField($c['clients_address'] ?? ''),
                $this->csvField($c['clients_postcode'] ?? ''),
                $this->csvField($c['clients_city'] ?? ''),
                $this->csvField($c['clients_country'] ?? 'DE'),
                $this->csvField($c['clients_phone'] ?? ''),
                $this->csvField($c['clients_email'] ?? ''),
                $this->csvField($c['clients_vatId'] ?? ''),
                '',
                '',
                '',
                $this->csvField('Import aus AdamRMS'),
            ]);
        }

        $csv = $header . "\r\n" . implode("\r\n", $lines);
        $filename = "sevdesk_kontakte_export.csv";

        return [
            'csv'          => $csv,
            'filename'     => $filename,
            'record_count' => count($lines),
            'format'       => 'csv',
            'encoding'     => 'UTF-8',
        ];
    }

    // -- Hilfsmethoden --

    private function loadInstance(int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        return $this->db->getOne('instances') ?: [];
    }

    private function loadDocuments(int $instanceId, string $from, string $to): array
    {
        $this->db->where('dl.instances_id', $instanceId);
        $this->db->where('dl.doc_type', ['invoice', 'credit_note'], 'IN');
        $this->db->where('dl.status', ['sent', 'paid', 'overdue', 'reminded', 'cancelled'], 'IN');
        $this->db->where('DATE(dl.created_at)', $from, '>=');
        $this->db->where('DATE(dl.created_at)', $to, '<=');
        $this->db->join('projects p', 'dl.projects_id=p.projects_id', 'LEFT');
        $this->db->orderBy('dl.created_at', 'ASC');
        return $this->db->get('document_lifecycle dl', null, [
            'dl.*', 'p.projects_name', 'p.clients_id'
        ]) ?: [];
    }

    private function loadDocumentItems(int $documentId): array
    {
        $this->db->where('document_lifecycle_id', $documentId);
        return $this->db->get('document_line_items') ?: [];
    }

    private function loadClient(int $clientId): array
    {
        if ($clientId <= 0) return [];
        $this->db->where('clients_id', $clientId);
        return $this->db->getOne('clients') ?: [];
    }

    private function loadClients(int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('clients_deleted', 0);
        $this->db->orderBy('clients_name', 'ASC');
        return $this->db->get('clients') ?: [];
    }

    private function logExport(int $instanceId, string $type, string $from, string $to, string $filename, int $count): void
    {
        $this->db->insert('datev_exports', [
            'instances_id' => $instanceId,
            'export_type'  => $type,
            'period_from'  => $from,
            'period_to'    => $to,
            'filename'     => $filename,
            'record_count' => $count,
            'created_by'   => $_SESSION['users_userid'] ?? 0,
        ]);
    }

    private function csvField(string $value): string
    {
        return '"' . str_replace('"', '""', $value) . '"';
    }
}
