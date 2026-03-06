<?php
/**
 * ZUGFeRD / Factur-X / XRechnung XML-Generator
 *
 * Erzeugt XML nach dem ZUGFeRD 2.1 / Factur-X EN 16931 Standard
 * (UN/CEFACT Cross Industry Invoice - CII).
 *
 * Profil: COMFORT (ausreichend fuer die meisten deutschen KMU).
 *
 * Referenzen:
 *  - ZUGFeRD 2.1: https://www.ferd-net.de/standards/zugferd-2.1/index.html
 *  - UN/CEFACT CII D16B
 *  - EN 16931-1:2017
 */
class ZugferdService
{
    // ZUGFeRD 2.1 COMFORT Profile URN
    const PROFILE_COMFORT = 'urn:factur-x.eu:1p0:comfort';
    const PROFILE_BASIC   = 'urn:factur-x.eu:1p0:basic';

    /**
     * Generate ZUGFeRD XML for an invoice
     *
     * @param array $business Instance/seller business data
     * @param array $client   Buyer data
     * @param array $doc      Document data (number, date, due_date, etc.)
     * @param array $lines    Invoice line items
     * @param array $totals   Calculated totals
     * @param array $project  Project data (for service period)
     * @return string XML content
     */
    public static function generateInvoiceXml(
        array $business,
        array $client,
        array $doc,
        array $lines,
        array $totals,
        array $project = []
    ): string {
        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        // Root element: CrossIndustryInvoice
        $root = $xml->createElementNS(
            'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
            'rsm:CrossIndustryInvoice'
        );
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ram',
            'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:udt',
            'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:qdt',
            'urn:un:unece:uncefact:data:standard:QualifiedDataType:100');
        $xml->appendChild($root);

        // 1. ExchangedDocumentContext (Profile)
        $ctx = self::addElement($xml, $root, 'rsm:ExchangedDocumentContext');
        $guideline = self::addElement($xml, $ctx, 'ram:GuidelineSpecifiedDocumentContextParameter');
        self::addElement($xml, $guideline, 'ram:ID', self::PROFILE_COMFORT);

        // 2. ExchangedDocument (Invoice metadata)
        $exDoc = self::addElement($xml, $root, 'rsm:ExchangedDocument');
        self::addElement($xml, $exDoc, 'ram:ID', $doc['number'] ?? '');
        // TypeCode: 380 = Commercial Invoice, 381 = Credit Note, 384 = Corrected Invoice
        $typeCode = '380';
        if (($doc['type'] ?? '') === 'credit_note') $typeCode = '381';
        self::addElement($xml, $exDoc, 'ram:TypeCode', $typeCode);

        $issueDate = self::addElement($xml, $exDoc, 'ram:IssueDateTime');
        $dateStr = self::addElement($xml, $issueDate, 'udt:DateTimeString',
            self::formatDate($doc['date'] ?? new \DateTime()));
        $dateStr->setAttribute('format', '102');

        // 3. SupplyChainTradeTransaction
        $transaction = self::addElement($xml, $root, 'rsm:SupplyChainTradeTransaction');

        // 3.1 Line Items
        $lineNumber = 0;
        foreach ($lines as $line) {
            if (($line['kind'] ?? '') === 'set' && isset($line['components'])) {
                // For sets, add each component as a sub-line or add set as single line
                $lineNumber++;
                self::addLineItem($xml, $transaction, $line, $lineNumber, $totals, $doc);
            } else {
                $lineNumber++;
                self::addLineItem($xml, $transaction, $line, $lineNumber, $totals, $doc);
            }
        }

        // 3.2 ApplicableHeaderTradeAgreement (Seller + Buyer)
        $agreement = self::addElement($xml, $transaction, 'ram:ApplicableHeaderTradeAgreement');

        // Seller
        $seller = self::addElement($xml, $agreement, 'ram:SellerTradeParty');
        self::addElement($xml, $seller, 'ram:Name', $business['instances_name'] ?? '');

        // Seller postal address
        $sellerAddr = self::addElement($xml, $seller, 'ram:PostalTradeAddress');
        self::parseAndAddAddress($xml, $sellerAddr, $business['instances_address'] ?? '');
        self::addElement($xml, $sellerAddr, 'ram:CountryID', 'DE');

        // Seller tax registration
        if (!empty($business['instances_vatId'])) {
            $sellerTax = self::addElement($xml, $seller, 'ram:SpecifiedTaxRegistration');
            $taxId = self::addElement($xml, $sellerTax, 'ram:ID', $business['instances_vatId']);
            $taxId->setAttribute('schemeID', 'VA'); // VA = VAT ID
        }
        if (!empty($business['instances_taxNumber'])) {
            $sellerTax2 = self::addElement($xml, $seller, 'ram:SpecifiedTaxRegistration');
            $taxId2 = self::addElement($xml, $sellerTax2, 'ram:ID', $business['instances_taxNumber']);
            $taxId2->setAttribute('schemeID', 'FC'); // FC = Tax number
        }

        // Buyer
        $buyer = self::addElement($xml, $agreement, 'ram:BuyerTradeParty');
        self::addElement($xml, $buyer, 'ram:Name', $client['clients_name'] ?? '');

        // Buyer postal address
        if (!empty($client['clients_address'])) {
            $buyerAddr = self::addElement($xml, $buyer, 'ram:PostalTradeAddress');
            self::parseAndAddAddress($xml, $buyerAddr, $client['clients_address']);
            self::addElement($xml, $buyerAddr, 'ram:CountryID', 'DE');
        }

        // Buyer tax registration (if available)
        if (!empty($client['clients_vatId'])) {
            $buyerTax = self::addElement($xml, $buyer, 'ram:SpecifiedTaxRegistration');
            $buyerTaxId = self::addElement($xml, $buyerTax, 'ram:ID', $client['clients_vatId']);
            $buyerTaxId->setAttribute('schemeID', 'VA');
        }

        // Buyer reference / customer number
        if (!empty($client['clients_customerNumber'])) {
            self::addElement($xml, $agreement, 'ram:BuyerReference', $client['clients_customerNumber']);
        }

        // 3.3 ApplicableHeaderTradeDelivery
        $delivery = self::addElement($xml, $transaction, 'ram:ApplicableHeaderTradeDelivery');

        // Delivery date / service period
        if (!empty($project['projects_dates_use_end']) || !empty($doc['service_period_end'])) {
            $actualDelivery = self::addElement($xml, $delivery, 'ram:ActualDeliverySupplyChainEvent');
            $occDt = self::addElement($xml, $actualDelivery, 'ram:OccurrenceDateTime');
            $endDate = $doc['service_period_end'] ?? $project['projects_dates_use_end'] ?? new \DateTime();
            $dateStr2 = self::addElement($xml, $occDt, 'udt:DateTimeString', self::formatDate($endDate));
            $dateStr2->setAttribute('format', '102');
        }

        // Service period (BillingSpecifiedPeriod)
        if (!empty($doc['service_period_start']) && !empty($doc['service_period_end'])) {
            $billingPeriod = self::addElement($xml, $delivery, 'ram:BillingSpecifiedPeriod');
            $startDt = self::addElement($xml, $billingPeriod, 'ram:StartDateTime');
            $s = self::addElement($xml, $startDt, 'udt:DateTimeString', self::formatDate($doc['service_period_start']));
            $s->setAttribute('format', '102');
            $endDt = self::addElement($xml, $billingPeriod, 'ram:EndDateTime');
            $e = self::addElement($xml, $endDt, 'udt:DateTimeString', self::formatDate($doc['service_period_end']));
            $e->setAttribute('format', '102');
        }

        // 3.4 ApplicableHeaderTradeSettlement
        $settlement = self::addElement($xml, $transaction, 'ram:ApplicableHeaderTradeSettlement');
        self::addElement($xml, $settlement, 'ram:InvoiceCurrencyCode', 'EUR');

        // Payment means (wire transfer)
        if (!empty($business['instances_bankIban'])) {
            $paymentMeans = self::addElement($xml, $settlement, 'ram:SpecifiedTradeSettlementPaymentMeans');
            self::addElement($xml, $paymentMeans, 'ram:TypeCode', '58'); // 58 = SEPA credit transfer
            $payeeAccount = self::addElement($xml, $paymentMeans, 'ram:PayeePartyCreditorFinancialAccount');
            self::addElement($xml, $payeeAccount, 'ram:IBANID', $business['instances_bankIban']);
            if (!empty($business['instances_bankBic'])) {
                $payeeInst = self::addElement($xml, $paymentMeans, 'ram:PayeeSpecifiedCreditorFinancialInstitution');
                self::addElement($xml, $payeeInst, 'ram:BICID', $business['instances_bankBic']);
            }
        }

        // Tax (ApplicableTradeTax)
        $tradeTax = self::addElement($xml, $settlement, 'ram:ApplicableTradeTax');
        self::addElement($xml, $tradeTax, 'ram:CalculatedAmount', self::formatAmount($totals['vat'] ?? 0));
        self::addElement($xml, $tradeTax, 'ram:TypeCode', 'VAT');
        if ($totals['kur'] ?? false) {
            // Kleinunternehmerregelung: exemption
            self::addElement($xml, $tradeTax, 'ram:ExemptionReason', 'Kein Ausweis von Umsatzsteuer, da Kleinunternehmer gemaess § 19 UStG.');
            self::addElement($xml, $tradeTax, 'ram:CategoryCode', 'E'); // E = Exempt
        } else {
            self::addElement($xml, $tradeTax, 'ram:CategoryCode', 'S'); // S = Standard rate
        }
        self::addElement($xml, $tradeTax, 'ram:BasisAmount', self::formatAmount($totals['net'] ?? 0));
        self::addElement($xml, $tradeTax, 'ram:RateApplicablePercent', self::formatAmount($totals['vat_rate'] ?? 0));

        // Payment terms
        $paymentTerms = self::addElement($xml, $settlement, 'ram:SpecifiedTradePaymentTerms');
        $paymentTermDays = $doc['payment_term_days'] ?? 14;
        $skontoRate = $doc['skonto_rate'] ?? 0;
        $skontoDays = $doc['skonto_days'] ?? 0;
        if ($skontoRate > 0 && $skontoDays > 0) {
            self::addElement($xml, $paymentTerms, 'ram:Description',
                "Zahlbar innerhalb von {$paymentTermDays} Tagen ohne Abzug. "
                . "Bei Zahlung innerhalb von {$skontoDays} Tagen {$skontoRate}% Skonto.");
        } else {
            self::addElement($xml, $paymentTerms, 'ram:Description',
                "Zahlbar innerhalb von {$paymentTermDays} Tagen ohne Abzug.");
        }
        if (!empty($doc['due_date'])) {
            $dueDateDt = self::addElement($xml, $paymentTerms, 'ram:DueDateDateTime');
            $d = self::addElement($xml, $dueDateDt, 'udt:DateTimeString', self::formatDate($doc['due_date']));
            $d->setAttribute('format', '102');
        }

        // Discount (if applicable)
        if (($totals['discount'] ?? 0) > 0) {
            $allowance = self::addElement($xml, $settlement, 'ram:SpecifiedTradeAllowanceCharge');
            self::addElement($xml, $allowance, 'ram:ChargeIndicator')
                ->appendChild($xml->createElementNS(
                    'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100',
                    'udt:Indicator', 'false'));
            self::addElement($xml, $allowance, 'ram:ActualAmount', self::formatAmount($totals['discount']));
            self::addElement($xml, $allowance, 'ram:Reason', 'Rabatt ' . ($totals['discount_pct'] ?? 0) . '%');
        }

        // Monetary summation
        $monetary = self::addElement($xml, $settlement, 'ram:SpecifiedTradeSettlementHeaderMonetarySummation');
        self::addElement($xml, $monetary, 'ram:LineTotalAmount', self::formatAmount($totals['subtotal'] ?? 0));
        self::addElement($xml, $monetary, 'ram:AllowanceTotalAmount', self::formatAmount($totals['discount'] ?? 0));
        self::addElement($xml, $monetary, 'ram:TaxBasisTotalAmount', self::formatAmount($totals['net'] ?? 0));

        $taxTotal = self::addElement($xml, $monetary, 'ram:TaxTotalAmount', self::formatAmount($totals['vat'] ?? 0));
        $taxTotal->setAttribute('currencyID', 'EUR');

        self::addElement($xml, $monetary, 'ram:GrandTotalAmount', self::formatAmount($totals['gross'] ?? 0));
        self::addElement($xml, $monetary, 'ram:DuePayableAmount', self::formatAmount($totals['gross'] ?? 0));

        return $xml->saveXML();
    }

    /**
     * Add a line item to the transaction
     */
    private static function addLineItem(\DOMDocument $xml, \DOMElement $parent, array $line, int $lineNumber, array $totals, array $doc): void
    {
        $item = self::addElement($xml, $parent, 'ram:IncludedSupplyChainTradeLineItem');

        // Line document
        $lineDoc = self::addElement($xml, $item, 'ram:AssociatedDocumentLineDocument');
        self::addElement($xml, $lineDoc, 'ram:LineID', (string)$lineNumber);

        // Product
        $product = self::addElement($xml, $item, 'ram:SpecifiedTradeProduct');
        self::addElement($xml, $product, 'ram:Name', $line['name'] ?? '');
        if (!empty($line['note'])) {
            self::addElement($xml, $product, 'ram:Description', $line['note']);
        }

        // Line agreement (pricing)
        $lineAgreement = self::addElement($xml, $item, 'ram:SpecifiedLineTradeAgreement');
        $netPrice = self::addElement($xml, $lineAgreement, 'ram:NetPriceProductTradePrice');

        // Calculate unit price
        $qty = (float)($line['qty'] ?? 1);
        $total = (float)($line['total'] ?? 0);
        $unitPrice = $qty > 0 ? round($total / $qty, 4) : $total;
        self::addElement($xml, $netPrice, 'ram:ChargeAmount', self::formatAmount($unitPrice));

        // Line delivery (quantity)
        $lineDelivery = self::addElement($xml, $item, 'ram:SpecifiedLineTradeDelivery');
        $billedQty = self::addElement($xml, $lineDelivery, 'ram:BilledQuantity', self::formatAmount($qty));
        // Unit code: C62 = unit, DAY = day, HUR = hour
        $unitCode = 'C62';
        $unit = strtolower($line['unit'] ?? '');
        if (in_array($unit, ['tag', 'tage', 'day', 'days'])) $unitCode = 'DAY';
        elseif (in_array($unit, ['stunde', 'stunden', 'hour', 'hours', 'h'])) $unitCode = 'HUR';
        elseif (in_array($unit, ['set', 'pauschal'])) $unitCode = 'C62';
        $billedQty->setAttribute('unitCode', $unitCode);

        // Line settlement (tax + total)
        $lineSettlement = self::addElement($xml, $item, 'ram:SpecifiedLineTradeSettlement');
        $lineTax = self::addElement($xml, $lineSettlement, 'ram:ApplicableTradeTax');
        self::addElement($xml, $lineTax, 'ram:TypeCode', 'VAT');
        if ($totals['kur'] ?? false) {
            self::addElement($xml, $lineTax, 'ram:CategoryCode', 'E');
        } else {
            self::addElement($xml, $lineTax, 'ram:CategoryCode', 'S');
        }
        self::addElement($xml, $lineTax, 'ram:RateApplicablePercent', self::formatAmount($totals['vat_rate'] ?? 0));

        $lineSummation = self::addElement($xml, $lineSettlement, 'ram:SpecifiedTradeSettlementLineMonetarySummation');
        self::addElement($xml, $lineSummation, 'ram:LineTotalAmount', self::formatAmount($total));
    }

    /**
     * Parse a free-text address into structured fields
     */
    private static function parseAndAddAddress(\DOMDocument $xml, \DOMElement $parent, string $address): void
    {
        $lines = array_filter(array_map('trim', preg_split('/[\r\n]+/', $address)));
        if (empty($lines)) return;

        // Try to parse: last line is often "PLZ Stadt"
        $lastLine = end($lines);
        $plzMatch = preg_match('/^(\d{4,5})\s+(.+)$/', $lastLine, $m);

        if ($plzMatch) {
            array_pop($lines);
            self::addElement($xml, $parent, 'ram:PostcodeCode', $m[1]);
            self::addElement($xml, $parent, 'ram:CityName', $m[2]);
        }

        // Remaining lines form the street address
        if (!empty($lines)) {
            self::addElement($xml, $parent, 'ram:LineOne', implode(', ', $lines));
        }
    }

    private static function addElement(\DOMDocument $xml, \DOMElement $parent, string $name, ?string $value = null): \DOMElement
    {
        $el = $xml->createElement($name, $value !== null ? htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8') : null);
        $parent->appendChild($el);
        return $el;
    }

    private static function formatDate($date): string
    {
        if ($date instanceof \DateTimeInterface) return $date->format('Ymd');
        if (is_string($date) && strlen($date) >= 10) return date('Ymd', strtotime($date));
        return date('Ymd');
    }

    private static function formatAmount($value): string
    {
        return number_format(round((float)$value, 2), 2, '.', '');
    }
}
