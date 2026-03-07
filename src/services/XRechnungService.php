<?php
/**
 * XRechnung-Service (UBL 2.1 Format)
 *
 * Generiert XRechnung-konforme XML-Dateien im UBL (Universal Business Language) Format
 * fuer oeffentliche Auftraggeber in Deutschland.
 *
 * XRechnung ist seit 27.11.2020 Pflicht fuer Rechnungen an Bundesbehoerden
 * und seit Anfang 2025 auch fuer B2B Rechnungen nach dem Wachstumschancengesetz.
 *
 * Referenz: https://xeinkauf.de/xrechnung/
 * Standard: EN 16931 / CIUS-XRechnung
 */
class XRechnungService
{
    /**
     * XRechnung XML (UBL 2.1 Invoice) generieren
     *
     * @param array $business  Unternehmensdaten
     * @param array $client    Kundendaten
     * @param array $doc       Dokumentdaten (number, date, due_date, service_period_*)
     * @param array $lines     Rechnungspositionen
     * @param array $totals    Summen (net, vat, gross, kur)
     * @param array $project   Projektdaten
     * @return string XML-String
     */
    public static function generateInvoiceXml(
        array $business,
        array $client,
        array $doc,
        array $lines,
        array $totals,
        array $project
    ): string {
        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->setIndent(true);

        // Root: UBL Invoice
        $xml->startElementNs(null, 'Invoice', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $xml->writeAttributeNs('xmlns', 'cac', null, 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xml->writeAttributeNs('xmlns', 'cbc', null, 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

        // BT-24: Specification identifier (XRechnung CIUS)
        $xml->startElement('cbc:CustomizationID');
        $xml->text('urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0');
        $xml->endElement();

        // BT-23: Business process type
        $xml->startElement('cbc:ProfileID');
        $xml->text('urn:fdc:peppol.eu:2017:poacc:billing:01:1.0');
        $xml->endElement();

        // BT-1: Invoice number
        $xml->writeElement('cbc:ID', $doc['number']);

        // BT-2: Issue date
        $issueDate = ($doc['date'] instanceof \DateTimeInterface) ? $doc['date']->format('Y-m-d') : date('Y-m-d');
        $xml->writeElement('cbc:IssueDate', $issueDate);

        // BT-9: Due date
        if (!empty($doc['due_date'])) {
            $dueDate = ($doc['due_date'] instanceof \DateTimeInterface) ? $doc['due_date']->format('Y-m-d') : $doc['due_date'];
            $xml->writeElement('cbc:DueDate', $dueDate);
        }

        // BT-3: Invoice type code (380 = Commercial invoice)
        $xml->writeElement('cbc:InvoiceTypeCode', '380');

        // BT-22: Notes
        if (!empty($totals['kur_notice'])) {
            $xml->startElement('cbc:Note');
            $xml->text($totals['kur_notice']);
            $xml->endElement();
        }

        // BT-5: Invoice currency code
        $xml->writeElement('cbc:DocumentCurrencyCode', 'EUR');

        // BT-10: Buyer reference (Leitweg-ID)
        $buyerRef = $client['clients_leitwegId'] ?? $client['clients_buyerReference'] ?? '';
        if (!empty($buyerRef)) {
            $xml->writeElement('cbc:BuyerReference', $buyerRef);
        } else {
            // XRechnung erfordert BuyerReference - Fallback auf Kundennummer
            $xml->writeElement('cbc:BuyerReference', $client['clients_customerNumber'] ?? 'KEIN-LEITWEG');
        }

        // BG-14: Invoice period (Leistungszeitraum)
        if (!empty($doc['service_period_start']) && !empty($doc['service_period_end'])) {
            $xml->startElement('cac:InvoicePeriod');
            $spStart = ($doc['service_period_start'] instanceof \DateTimeInterface) ? $doc['service_period_start']->format('Y-m-d') : $doc['service_period_start'];
            $spEnd = ($doc['service_period_end'] instanceof \DateTimeInterface) ? $doc['service_period_end']->format('Y-m-d') : $doc['service_period_end'];
            $xml->writeElement('cbc:StartDate', $spStart);
            $xml->writeElement('cbc:EndDate', $spEnd);
            $xml->endElement();
        }

        // BG-4: Seller (Verkaeufer)
        $xml->startElement('cac:AccountingSupplierParty');
        $xml->startElement('cac:Party');

        // Seller name
        $xml->startElement('cac:PartyName');
        $xml->writeElement('cbc:Name', $business['instances_name'] ?? '');
        $xml->endElement();

        // Seller address
        $xml->startElement('cac:PostalAddress');
        $xml->writeElement('cbc:StreetName', $business['instances_address1'] ?? '');
        if (!empty($business['instances_address2'])) {
            $xml->writeElement('cbc:AdditionalStreetName', $business['instances_address2']);
        }
        $xml->writeElement('cbc:CityName', $business['instances_town'] ?? '');
        $xml->writeElement('cbc:PostalZone', $business['instances_postcode'] ?? '');
        $xml->startElement('cac:Country');
        $xml->writeElement('cbc:IdentificationCode', 'DE');
        $xml->endElement();
        $xml->endElement(); // PostalAddress

        // Seller tax registration
        if (!empty($business['instances_vatId'])) {
            $xml->startElement('cac:PartyTaxScheme');
            $xml->writeElement('cbc:CompanyID', $business['instances_vatId']);
            $xml->startElement('cac:TaxScheme');
            $xml->writeElement('cbc:ID', 'VAT');
            $xml->endElement();
            $xml->endElement();
        }

        // Seller legal entity
        $xml->startElement('cac:PartyLegalEntity');
        $xml->writeElement('cbc:RegistrationName', $business['instances_name'] ?? '');
        if (!empty($business['instances_taxNumber'])) {
            $xml->writeElement('cbc:CompanyID', $business['instances_taxNumber']);
        }
        $xml->endElement();

        // Seller contact
        $xml->startElement('cac:Contact');
        if (!empty($business['instances_phone'])) {
            $xml->writeElement('cbc:Telephone', $business['instances_phone']);
        }
        if (!empty($business['instances_email'])) {
            $xml->writeElement('cbc:ElectronicMail', $business['instances_email']);
        }
        $xml->endElement();

        $xml->endElement(); // Party
        $xml->endElement(); // AccountingSupplierParty

        // BG-7: Buyer (Kaeufer)
        $xml->startElement('cac:AccountingCustomerParty');
        $xml->startElement('cac:Party');

        $xml->startElement('cac:PartyName');
        $xml->writeElement('cbc:Name', $client['clients_name'] ?? '');
        $xml->endElement();

        $xml->startElement('cac:PostalAddress');
        $xml->writeElement('cbc:StreetName', $client['clients_address1'] ?? '');
        if (!empty($client['clients_address2'])) {
            $xml->writeElement('cbc:AdditionalStreetName', $client['clients_address2']);
        }
        $xml->writeElement('cbc:CityName', $client['clients_town'] ?? '');
        $xml->writeElement('cbc:PostalZone', $client['clients_postcode'] ?? '');
        $xml->startElement('cac:Country');
        $xml->writeElement('cbc:IdentificationCode', $client['clients_country'] ?? 'DE');
        $xml->endElement();
        $xml->endElement(); // PostalAddress

        // Buyer VAT ID
        if (!empty($client['clients_vatId'])) {
            $xml->startElement('cac:PartyTaxScheme');
            $xml->writeElement('cbc:CompanyID', $client['clients_vatId']);
            $xml->startElement('cac:TaxScheme');
            $xml->writeElement('cbc:ID', 'VAT');
            $xml->endElement();
            $xml->endElement();
        }

        $xml->startElement('cac:PartyLegalEntity');
        $xml->writeElement('cbc:RegistrationName', $client['clients_name'] ?? '');
        $xml->endElement();

        $xml->endElement(); // Party
        $xml->endElement(); // AccountingCustomerParty

        // BG-16: Payment means (Zahlungsart)
        $xml->startElement('cac:PaymentMeans');
        $xml->writeElement('cbc:PaymentMeansCode', '58'); // SEPA credit transfer
        if (!empty($business['instances_bankIban'])) {
            $xml->startElement('cac:PayeeFinancialAccount');
            $xml->writeElement('cbc:ID', $business['instances_bankIban']);
            if (!empty($business['instances_bankName'])) {
                $xml->writeElement('cbc:Name', $business['instances_bankName']);
            }
            if (!empty($business['instances_bankBic'])) {
                $xml->startElement('cac:FinancialInstitutionBranch');
                $xml->writeElement('cbc:ID', $business['instances_bankBic']);
                $xml->endElement();
            }
            $xml->endElement();
        }
        $xml->endElement();

        // BG-20: Payment terms
        if (!empty($doc['payment_term_days'])) {
            $xml->startElement('cac:PaymentTerms');
            $note = "Zahlbar innerhalb von {$doc['payment_term_days']} Tagen.";
            if (!empty($doc['skonto_rate']) && $doc['skonto_rate'] > 0 && !empty($doc['skonto_days'])) {
                $note .= " Bei Zahlung innerhalb von {$doc['skonto_days']} Tagen gewaehren wir {$doc['skonto_rate']}% Skonto.";
            }
            $xml->writeElement('cbc:Note', $note);
            $xml->endElement();
        }

        // BG-22: Tax total
        $xml->startElement('cac:TaxTotal');
        $xml->startElement('cbc:TaxAmount');
        $xml->writeAttribute('currencyID', 'EUR');
        $xml->text(number_format((float)($totals['vat'] ?? 0), 2, '.', ''));
        $xml->endElement();

        // Tax subtotal
        $xml->startElement('cac:TaxSubtotal');
        $xml->startElement('cbc:TaxableAmount');
        $xml->writeAttribute('currencyID', 'EUR');
        $xml->text(number_format((float)($totals['net'] ?? 0), 2, '.', ''));
        $xml->endElement();
        $xml->startElement('cbc:TaxAmount');
        $xml->writeAttribute('currencyID', 'EUR');
        $xml->text(number_format((float)($totals['vat'] ?? 0), 2, '.', ''));
        $xml->endElement();

        $xml->startElement('cac:TaxCategory');
        if (!empty($totals['kur'])) {
            $xml->writeElement('cbc:ID', 'E'); // Exempt
            $xml->writeElement('cbc:Percent', '0');
            $xml->writeElement('cbc:TaxExemptionReasonCode', 'vatex-eu-ae');
            $xml->writeElement('cbc:TaxExemptionReason', 'Kein Ausweis von Umsatzsteuer, da Kleinunternehmer gemaess § 19 UStG.');
        } else {
            $xml->writeElement('cbc:ID', 'S'); // Standard
            $xml->writeElement('cbc:Percent', number_format((float)($totals['vat_rate'] ?? 19), 2, '.', ''));
        }
        $xml->startElement('cac:TaxScheme');
        $xml->writeElement('cbc:ID', 'VAT');
        $xml->endElement();
        $xml->endElement(); // TaxCategory

        $xml->endElement(); // TaxSubtotal
        $xml->endElement(); // TaxTotal

        // BG-22: Legal monetary total
        $xml->startElement('cac:LegalMonetaryTotal');
        self::writeAmount($xml, 'cbc:LineExtensionAmount', $totals['subtotal'] ?? 0);
        self::writeAmount($xml, 'cbc:TaxExclusiveAmount', $totals['net'] ?? 0);
        self::writeAmount($xml, 'cbc:TaxInclusiveAmount', $totals['gross'] ?? 0);
        if (($totals['discount'] ?? 0) > 0) {
            self::writeAmount($xml, 'cbc:AllowanceTotalAmount', $totals['discount'] ?? 0);
        }
        self::writeAmount($xml, 'cbc:PayableAmount', $totals['gross'] ?? 0);
        $xml->endElement();

        // Invoice lines
        $lineNum = 1;
        foreach ($lines as $line) {
            if (($line['kind'] ?? '') === 'set' && isset($line['components'])) {
                // Set: Hauptzeile
                self::writeInvoiceLine($xml, $lineNum, $line, $totals);
                $lineNum++;
            } else {
                self::writeInvoiceLine($xml, $lineNum, $line, $totals);
                $lineNum++;
            }
        }

        $xml->endElement(); // Invoice
        $xml->endDocument();

        return $xml->outputMemory();
    }

    private static function writeInvoiceLine(\XMLWriter $xml, int $lineNum, array $line, array $totals): void
    {
        $xml->startElement('cac:InvoiceLine');
        $xml->writeElement('cbc:ID', (string)$lineNum);

        $xml->startElement('cbc:InvoicedQuantity');
        $xml->writeAttribute('unitCode', self::mapUnitCode($line['unit'] ?? 'Stueck'));
        $xml->text(number_format((float)($line['qty'] ?? 1), 2, '.', ''));
        $xml->endElement();

        self::writeAmount($xml, 'cbc:LineExtensionAmount', $line['total'] ?? 0);

        // Line item
        $xml->startElement('cac:Item');
        $xml->writeElement('cbc:Name', $line['name'] ?? 'Position');

        // Tax category for line
        $xml->startElement('cac:ClassifiedTaxCategory');
        if (!empty($totals['kur'])) {
            $xml->writeElement('cbc:ID', 'E');
            $xml->writeElement('cbc:Percent', '0');
        } else {
            $xml->writeElement('cbc:ID', 'S');
            $xml->writeElement('cbc:Percent', number_format((float)($totals['vat_rate'] ?? 19), 2, '.', ''));
        }
        $xml->startElement('cac:TaxScheme');
        $xml->writeElement('cbc:ID', 'VAT');
        $xml->endElement();
        $xml->endElement(); // ClassifiedTaxCategory

        $xml->endElement(); // Item

        // Price
        $xml->startElement('cac:Price');
        $qty = (float)($line['qty'] ?? 1);
        $unitPrice = $qty > 0 ? (float)($line['total'] ?? 0) / $qty : 0;
        self::writeAmount($xml, 'cbc:PriceAmount', $unitPrice);
        $xml->endElement();

        $xml->endElement(); // InvoiceLine
    }

    private static function writeAmount(\XMLWriter $xml, string $element, float $amount): void
    {
        $xml->startElement($element);
        $xml->writeAttribute('currencyID', 'EUR');
        $xml->text(number_format($amount, 2, '.', ''));
        $xml->endElement();
    }

    private static function mapUnitCode(string $unit): string
    {
        $map = [
            'Stueck' => 'C62', 'Stück' => 'C62', 'Stk' => 'C62',
            'Tag' => 'DAY', 'Tage' => 'DAY',
            'Stunde' => 'HUR', 'Stunden' => 'HUR', 'h' => 'HUR',
            'Set' => 'SET',
            'Pauschal' => 'C62', 'pausch.' => 'C62',
            'km' => 'KMT', 'Kilometer' => 'KMT',
            'm' => 'MTR', 'Meter' => 'MTR',
            'kg' => 'KGM', 'Kilogramm' => 'KGM',
            'Liter' => 'LTR', 'l' => 'LTR',
        ];
        return $map[$unit] ?? 'C62';
    }
}
