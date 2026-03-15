<?php
/**
 * Zahlungseingaenge mit Bankdaten abgleichen (MT940/CAMT Import)
 *
 * Unterstuetzte Formate:
 * - MT940 (SWIFT, Standard deutscher Bankkontoauszug)
 * - CAMT.053 (ISO 20022 XML)
 * - CSV (Semikolon-getrennt: Datum;Name;IBAN;Verwendungszweck;Betrag)
 *
 * Features:
 * - Automatisches Matching von Zahlungseingaengen zu offenen Rechnungen
 * - Rechnungsnummer im Verwendungszweck (RE-YYYY-NNNN)
 * - Betragsabgleich
 * - Kunden-IBAN Abgleich
 * - Manuelles Zuordnen / Ignorieren
 */
class BankImportService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    // ═══════════════════════════════════════════════
    //  MT940 IMPORT
    // ═══════════════════════════════════════════════

    /**
     * MT940-Format (Standard deutscher Bankkontoauszug) importieren.
     *
     * Parsed:
     * - :20: Transaction Reference
     * - :60F: Opening Balance
     * - :61: Statement Line (Datum, Betrag, Referenz)
     * - :86: Information to Account Owner (Gegenpartei, IBAN, Verwendungszweck)
     * - :62F: Closing Balance
     *
     * @param int    $instanceId
     * @param string $fileContent  Roher Dateiinhalt der MT940-Datei
     * @return array Import-Ergebnis mit 'batch_id', 'imported', 'matched'
     */
    public function importMt940(int $instanceId, string $fileContent): array
    {
        $batchId = 'MT940-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
        $transactions = $this->parseMt940($fileContent);

        $imported = 0;
        foreach ($transactions as $tx) {
            $this->db->insert('bank_transactions', [
                'instances_id'     => $instanceId,
                'transaction_date' => $tx['date'],
                'value_date'       => $tx['value_date'],
                'amount'           => $tx['amount'],
                'currency'         => $tx['currency'] ?? 'EUR',
                'sender_name'      => $tx['sender_name'],
                'sender_iban'      => $tx['sender_iban'],
                'reference'        => $tx['reference'],
                'booking_text'     => $tx['booking_text'],
                'import_batch'     => $batchId,
            ]);
            $imported++;
        }

        $matched = $this->autoMatch($instanceId, $batchId);

        // Import-Log schreiben
        $this->logImport($instanceId, 'mt940-import.sta', 'mt940', $imported, $matched);

        return [
            'batch_id' => $batchId,
            'imported' => $imported,
            'matched'  => $matched,
        ];
    }

    /**
     * MT940-Dateiinhalt parsen.
     *
     * Felder:
     * - :20: Transaction Reference Number
     * - :60F: Opening Balance (C/DYYMMDDEUR1234,56)
     * - :61: Statement Line (YYMMDD[YYMMDD]C/D Amount Reference)
     * - :86: Information to Account Owner (Auftraggeber, IBAN, Verwendungszweck)
     * - :62F: Closing Balance
     */
    private function parseMt940(string $content): array
    {
        $transactions = [];
        $lines = explode("\n", str_replace("\r\n", "\n", $content));

        $currentTx = null;
        $in86 = false;
        $info86 = '';

        foreach ($lines as $line) {
            $line = rtrim($line);

            // :20: Transaction Reference
            if (preg_match('/^:20:(.+)$/', $line, $m)) {
                // Neue Anweisung; bei Bedarf koennte die Referenz genutzt werden
                continue;
            }

            // :60F: Opening Balance (informativ, nicht weiter verwendet)
            if (preg_match('/^:60F:/', $line)) {
                continue;
            }

            // :61: Statement Line
            if (preg_match('/^:61:(\d{6})(\d{4})?([CD]R?)(\d+,\d+)(.*)$/', $line, $m)) {
                // Vorherige Transaktion abschliessen
                if ($currentTx !== null) {
                    $currentTx = $this->finalizeMt940Tx($currentTx, $info86);
                    $transactions[] = $currentTx;
                }

                $dateStr = $m[1]; // YYMMDD
                $valDateStr = $m[2] ?: null; // MMDD
                $cdInd = $m[3]; // C = Credit, D = Debit
                $amountStr = str_replace(',', '.', $m[4]);
                $amount = (float)$amountStr;

                // Debit = negativ
                if (strpos($cdInd, 'D') !== false) {
                    $amount = -$amount;
                }

                // Datum parsen (YYMMDD)
                $year = (int)substr($dateStr, 0, 2);
                $year = $year > 70 ? 1900 + $year : 2000 + $year;
                $month = substr($dateStr, 2, 2);
                $day = substr($dateStr, 4, 2);
                $date = sprintf('%04d-%s-%s', $year, $month, $day);

                // Valutadatum (MMDD, selbes Jahr)
                $valueDate = null;
                if ($valDateStr) {
                    $vMonth = substr($valDateStr, 0, 2);
                    $vDay = substr($valDateStr, 2, 2);
                    $valueDate = sprintf('%04d-%s-%s', $year, $vMonth, $vDay);
                }

                $refPart = trim($m[5] ?? '');

                $currentTx = [
                    'date'         => $date,
                    'value_date'   => $valueDate ?? $date,
                    'amount'       => $amount,
                    'currency'     => 'EUR',
                    'sender_name'  => null,
                    'sender_iban'  => null,
                    'reference'    => $refPart,
                    'booking_text' => null,
                ];
                $in86 = false;
                $info86 = '';
                continue;
            }

            // :86: Information to Account Owner
            if (preg_match('/^:86:(.*)$/', $line, $m)) {
                $in86 = true;
                $info86 = $m[1];
                continue;
            }

            // :62F: Closing Balance
            if (preg_match('/^:62F:/', $line)) {
                // Letzte Transaktion abschliessen
                if ($currentTx !== null) {
                    $currentTx = $this->finalizeMt940Tx($currentTx, $info86);
                    $transactions[] = $currentTx;
                    $currentTx = null;
                }
                $in86 = false;
                continue;
            }

            // Fortsetzung von :86: (mehrzeilig)
            if ($in86 && !preg_match('/^:\d{2}\w?:/', $line)) {
                $info86 .= $line;
                continue;
            }

            // Neues Feld beendet :86:
            if ($in86 && preg_match('/^:\d{2}\w?:/', $line)) {
                $in86 = false;
            }
        }

        // Letzte Transaktion abschliessen falls noch offen
        if ($currentTx !== null) {
            $currentTx = $this->finalizeMt940Tx($currentTx, $info86);
            $transactions[] = $currentTx;
        }

        return $transactions;
    }

    /**
     * MT940 :86:-Feld auswerten und Transaktionsdaten anreichern.
     * Typische Unterfelder: ?20-?29 Verwendungszweck, ?30 BLZ, ?31 Konto, ?32-?33 Name
     */
    private function finalizeMt940Tx(array $tx, string $info86): array
    {
        if (empty($info86)) return $tx;

        // Strukturierte :86:-Felder parsen (?00 bis ?63)
        $fields = [];
        if (preg_match_all('/\?(\d{2})([^?]*)/', $info86, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $fields[$match[1]] = trim($match[2]);
            }
        }

        // Buchungstext aus ?00
        if (isset($fields['00'])) {
            $tx['booking_text'] = mb_substr($fields['00'], 0, 255);
        }

        // Verwendungszweck aus ?20 bis ?29
        $purpose = '';
        for ($i = 20; $i <= 29; $i++) {
            $key = str_pad($i, 2, '0', STR_PAD_LEFT);
            if (isset($fields[$key])) {
                $purpose .= $fields[$key] . ' ';
            }
        }
        if ($purpose) {
            $tx['reference'] = trim($purpose);
        }

        // Name des Auftraggebers aus ?32 und ?33
        $name = '';
        if (isset($fields['32'])) $name .= $fields['32'];
        if (isset($fields['33'])) $name .= ' ' . $fields['33'];
        if ($name) {
            $tx['sender_name'] = trim($name);
        }

        // IBAN aus ?31 (oder IBAN-Pattern im Freitext suchen)
        if (isset($fields['31'])) {
            $val = $fields['31'];
            if (preg_match('/^[A-Z]{2}\d{2}/', $val)) {
                $tx['sender_iban'] = $val;
            }
        }

        // Fallback: IBAN im gesamten :86:-Text suchen
        if (!$tx['sender_iban'] && preg_match('/([A-Z]{2}\d{2}[A-Z0-9]{4}\d{7,25})/', $info86, $ibanMatch)) {
            $tx['sender_iban'] = $ibanMatch[1];
        }

        return $tx;
    }

    // ═══════════════════════════════════════════════
    //  CAMT.053 IMPORT
    // ═══════════════════════════════════════════════

    /**
     * CAMT.053 XML (ISO 20022) importieren.
     *
     * Namespace: urn:iso:std:iso:20022:tech:xsd:camt.053.001.02
     * Extrahiert: BkToCstmrStmt/Stmt/Ntry
     * Pro Eintrag: BookgDt, ValDt, Amt, CdtDbtInd, RmtInf/Ustrd, DbtrNm/CdtrNm, DbtrAcct/CdtrAcct IBAN
     *
     * @param int    $instanceId
     * @param string $fileContent  Roher XML-Inhalt
     * @return array Import-Ergebnis mit 'batch_id', 'imported', 'matched'
     */
    public function importCamt053(int $instanceId, string $fileContent): array
    {
        $batchId = 'CAMT-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
        $transactions = $this->parseCamt053($fileContent);

        $imported = 0;
        foreach ($transactions as $tx) {
            $this->db->insert('bank_transactions', [
                'instances_id'     => $instanceId,
                'transaction_date' => $tx['date'],
                'value_date'       => $tx['value_date'],
                'amount'           => $tx['amount'],
                'currency'         => $tx['currency'] ?? 'EUR',
                'sender_name'      => $tx['sender_name'],
                'sender_iban'      => $tx['sender_iban'],
                'reference'        => $tx['reference'],
                'booking_text'     => $tx['booking_text'],
                'import_batch'     => $batchId,
            ]);
            $imported++;
        }

        $matched = $this->autoMatch($instanceId, $batchId);

        $this->logImport($instanceId, 'camt053-import.xml', 'camt053', $imported, $matched);

        return [
            'batch_id' => $batchId,
            'imported' => $imported,
            'matched'  => $matched,
        ];
    }

    /**
     * CAMT.053 XML parsen.
     *
     * Namespace: urn:iso:std:iso:20022:tech:xsd:camt.053.001.02
     * Pfad: BkToCstmrStmt/Stmt/Ntry
     */
    private function parseCamt053(string $content): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        if ($xml === false) {
            throw new \RuntimeException("Ungueltige CAMT.053 XML-Datei.");
        }

        // Namespace handling - verschiedene CAMT.053 Versionen unterstuetzen
        $namespaces = $xml->getNamespaces(true);
        $ns = '';
        foreach ($namespaces as $nsUri) {
            if (strpos($nsUri, 'camt.053') !== false) {
                $ns = $nsUri;
                break;
            }
        }
        if (!$ns) {
            $ns = reset($namespaces) ?: '';
        }

        if ($ns) {
            $xml->registerXPathNamespace('camt', $ns);
            $prefix = 'camt:';
        } else {
            $prefix = '';
        }

        $transactions = [];

        // BkToCstmrStmt/Stmt
        $stmts = $xml->xpath("//{$prefix}BkToCstmrStmt/{$prefix}Stmt")
            ?: $xml->xpath("//{$prefix}Stmt")
            ?: [];

        foreach ($stmts as $stmt) {
            if ($ns) $stmt->registerXPathNamespace('camt', $ns);

            // Ntry (Entries)
            $entries = $stmt->xpath("{$prefix}Ntry") ?: [];

            foreach ($entries as $ntry) {
                if ($ns) $ntry->registerXPathNamespace('camt', $ns);

                // Betrag
                $amtNodes = $ntry->xpath("{$prefix}Amt");
                $amount = $amtNodes ? (float)(string)$amtNodes[0] : 0;
                $currencyAttr = $amtNodes && $amtNodes[0]->attributes()
                    ? (string)$amtNodes[0]->attributes()->Ccy
                    : 'EUR';

                // Credit/Debit
                $cdtDbtNodes = $ntry->xpath("{$prefix}CdtDbtInd");
                $cdtDbt = $cdtDbtNodes ? (string)$cdtDbtNodes[0] : '';
                if ($cdtDbt === 'DBIT') {
                    $amount = -$amount;
                }

                // BookgDt (Buchungsdatum)
                $bookDtNodes = $ntry->xpath("{$prefix}BookgDt/{$prefix}Dt");
                $bookDate = $bookDtNodes ? (string)$bookDtNodes[0] : null;

                // ValDt (Valutadatum)
                $valDtNodes = $ntry->xpath("{$prefix}ValDt/{$prefix}Dt");
                $valDate = $valDtNodes ? (string)$valDtNodes[0] : null;

                // Buchungstext
                $addInfoNodes = $ntry->xpath("{$prefix}AddtlNtryInf");
                $bookingText = $addInfoNodes ? (string)$addInfoNodes[0] : null;

                // Details aus NtryDtls/TxDtls
                $txDtls = $ntry->xpath("{$prefix}NtryDtls/{$prefix}TxDtls");
                $senderName = null;
                $senderIban = null;
                $reference = null;

                if ($txDtls) {
                    $td = $txDtls[0];
                    if ($ns) $td->registerXPathNamespace('camt', $ns);

                    // Gegenkonto - fuer Eingaenge (CRDT): Debtor ist der Zahlende
                    if ($cdtDbt === 'CRDT') {
                        $nameNodes = $td->xpath("{$prefix}RltdPties/{$prefix}Dbtr/{$prefix}Nm")
                            ?: $td->xpath("{$prefix}RltdPties/{$prefix}Dbtr/{$prefix}Pty/{$prefix}Nm");
                        $ibanNodes = $td->xpath("{$prefix}RltdPties/{$prefix}DbtrAcct/{$prefix}Id/{$prefix}IBAN");
                    } else {
                        // Ausgang (DBIT): Creditor ist der Empfaenger
                        $nameNodes = $td->xpath("{$prefix}RltdPties/{$prefix}Cdtr/{$prefix}Nm")
                            ?: $td->xpath("{$prefix}RltdPties/{$prefix}Cdtr/{$prefix}Pty/{$prefix}Nm");
                        $ibanNodes = $td->xpath("{$prefix}RltdPties/{$prefix}CdtrAcct/{$prefix}Id/{$prefix}IBAN");
                    }
                    $senderName = $nameNodes ? (string)$nameNodes[0] : null;
                    $senderIban = $ibanNodes ? (string)$ibanNodes[0] : null;

                    // RmtInf/Ustrd (Verwendungszweck)
                    $ustrdNodes = $td->xpath("{$prefix}RmtInf/{$prefix}Ustrd");
                    $reference = $ustrdNodes ? (string)$ustrdNodes[0] : null;
                }

                $transactions[] = [
                    'date'         => $bookDate ?? $valDate ?? date('Y-m-d'),
                    'value_date'   => $valDate,
                    'amount'       => $amount,
                    'currency'     => $currencyAttr,
                    'sender_name'  => $senderName,
                    'sender_iban'  => $senderIban,
                    'reference'    => $reference,
                    'booking_text' => $bookingText ? mb_substr($bookingText, 0, 255) : null,
                ];
            }
        }

        return $transactions;
    }

    // ═══════════════════════════════════════════════
    //  CSV IMPORT
    // ═══════════════════════════════════════════════

    /**
     * Generisches CSV importieren (Semikolon-getrennt).
     * Erwartetes Format: Datum;Name;IBAN;Verwendungszweck;Betrag
     *
     * @param int    $instanceId
     * @param string $fileContent  Roher CSV-Inhalt
     * @return array Import-Ergebnis mit 'batch_id', 'imported', 'matched'
     */
    public function importCsv(int $instanceId, string $fileContent): array
    {
        $batchId = 'CSV-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
        $transactions = $this->parseCsv($fileContent);

        $imported = 0;
        foreach ($transactions as $tx) {
            $this->db->insert('bank_transactions', [
                'instances_id'     => $instanceId,
                'transaction_date' => $tx['date'],
                'value_date'       => $tx['value_date'],
                'amount'           => $tx['amount'],
                'currency'         => $tx['currency'] ?? 'EUR',
                'sender_name'      => $tx['sender_name'],
                'sender_iban'      => $tx['sender_iban'],
                'reference'        => $tx['reference'],
                'booking_text'     => $tx['booking_text'],
                'import_batch'     => $batchId,
            ]);
            $imported++;
        }

        $matched = $this->autoMatch($instanceId, $batchId);

        $this->logImport($instanceId, 'csv-import.csv', 'csv', $imported, $matched);

        return [
            'batch_id' => $batchId,
            'imported' => $imported,
            'matched'  => $matched,
        ];
    }

    /**
     * CSV parsen. Semikolon-getrennt: Datum;Name;IBAN;Verwendungszweck;Betrag
     * Erste Zeile ist Header und wird uebersprungen.
     */
    private function parseCsv(string $content): array
    {
        // Encoding-Erkennung
        $detected = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'ISO-8859-15', 'Windows-1252'], true);
        if ($detected && $detected !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $detected);
        }

        $rows = explode("\n", str_replace("\r\n", "\n", $content));
        $transactions = [];

        // Erste Zeile (Header) ueberspringen
        for ($i = 1; $i < count($rows); $i++) {
            $row = trim($rows[$i]);
            if ($row === '') continue;

            $cols = str_getcsv($row, ';');
            if (count($cols) < 5) continue;

            // Datum parsen (TT.MM.JJJJ oder JJJJ-MM-TT)
            $rawDate = trim($cols[0]);
            $dateObj = \DateTime::createFromFormat('d.m.Y', $rawDate);
            if (!$dateObj) {
                $dateObj = \DateTime::createFromFormat('Y-m-d', $rawDate);
            }
            if (!$dateObj) continue;
            $date = $dateObj->format('Y-m-d');

            // Betrag parsen (deutsches Format: 1.234,56)
            $rawAmount = trim($cols[4]);
            $rawAmount = str_replace('.', '', $rawAmount);   // Tausenderpunkte entfernen
            $rawAmount = str_replace(',', '.', $rawAmount);  // Dezimalkomma zu Punkt
            $amount = (float)$rawAmount;

            $transactions[] = [
                'date'         => $date,
                'value_date'   => $date,
                'amount'       => $amount,
                'currency'     => 'EUR',
                'sender_name'  => trim($cols[1]) ?: null,
                'sender_iban'  => trim($cols[2]) ?: null,
                'reference'    => trim($cols[3]) ?: null,
                'booking_text' => null,
            ];
        }

        return $transactions;
    }

    // ═══════════════════════════════════════════════
    //  AUTO-MATCHING
    // ═══════════════════════════════════════════════

    /**
     * Automatisches Matching von Transaktionen zu Rechnungen.
     *
     * Matching-Strategien:
     * 1. Rechnungsnummer im Verwendungszweck (Regex fuer RE-YYYY-NNNN Pattern)
     * 2. Betragsabgleich (exakter Betrag einer offenen Rechnung)
     * 3. Kunden-IBAN Abgleich
     *
     * @param int    $instanceId
     * @param string $batchId  Import-Batch-ID
     * @return int   Anzahl der gematchten Transaktionen
     */
    public function autoMatch(int $instanceId, string $batchId): int
    {
        // Alle ungematchten Eingaenge (positiver Betrag) des Batches holen
        $this->db->where('instances_id', $instanceId);
        $this->db->where('import_batch', $batchId);
        $this->db->where('match_status', 'unmatched');
        $this->db->where('amount', 0, '>');
        $txs = $this->db->get('bank_transactions') ?: [];

        if (empty($txs)) return 0;

        // Alle offenen Rechnungen holen
        $openInvoices = $this->getOpenInvoices($instanceId);
        if (empty($openInvoices)) return 0;

        $matched = 0;

        foreach ($txs as $tx) {
            $matchedDocId = null;
            $matchMethod = null;
            $ref = $tx['reference'] ?? '';
            $txAmount = (float)$tx['amount'];

            // Strategie 1: Rechnungsnummer im Verwendungszweck (RE-YYYY-NNNN)
            if ($ref && preg_match('/RE-(\d{4})-(\d{4,})/', $ref, $m)) {
                $invoiceNumber = $m[0]; // z.B. RE-2026-0042
                foreach ($openInvoices as $inv) {
                    if (stripos($inv['document_exports_number'] ?? '', $invoiceNumber) !== false) {
                        $matchedDocId = (int)$inv['document_exports_id'];
                        $matchMethod = 'auto_matched';
                        break;
                    }
                }
            }

            // Fallback: Beliebige Rechnungsnummer im Verwendungszweck suchen
            if (!$matchedDocId && $ref) {
                foreach ($openInvoices as $inv) {
                    $docNum = $inv['document_exports_number'] ?? '';
                    if ($docNum && stripos($ref, $docNum) !== false) {
                        $matchedDocId = (int)$inv['document_exports_id'];
                        $matchMethod = 'auto_matched';
                        break;
                    }
                }
            }

            // Strategie 2: Betragsabgleich (exakter Betrag)
            if (!$matchedDocId && $txAmount > 0) {
                foreach ($openInvoices as $inv) {
                    $invAmount = (float)($inv['document_exports_gross'] ?? 0);
                    $paidAmount = (float)($inv['paid_amount'] ?? 0);
                    $remaining = round($invAmount - $paidAmount, 2);

                    if ($remaining > 0 && abs($txAmount - $remaining) < 0.01) {
                        // Strategie 3: Kunden-IBAN Abgleich zur Bestaetigung
                        $senderIban = $tx['sender_iban'] ?? '';
                        $clientIban = $inv['client_iban'] ?? '';

                        if ($senderIban && $clientIban && $this->normalizeIban($senderIban) === $this->normalizeIban($clientIban)) {
                            $matchedDocId = (int)$inv['document_exports_id'];
                            $matchMethod = 'auto_matched';
                            break;
                        }

                        // Betragsabgleich + Namensabgleich als Fallback
                        $senderName = strtolower($tx['sender_name'] ?? '');
                        $clientName = strtolower($inv['clients_name'] ?? '');
                        if ($senderName && $clientName) {
                            $nameParts = explode(' ', $clientName);
                            foreach ($nameParts as $part) {
                                if (strlen($part) >= 3 && strpos($senderName, $part) !== false) {
                                    $matchedDocId = (int)$inv['document_exports_id'];
                                    $matchMethod = 'auto_matched';
                                    break 2;
                                }
                            }
                        }
                    }
                }
            }

            // Strategie 3 standalone: IBAN-Match + Betragsabgleich
            if (!$matchedDocId) {
                $senderIban = $tx['sender_iban'] ?? '';
                if ($senderIban) {
                    $normalizedSenderIban = $this->normalizeIban($senderIban);
                    foreach ($openInvoices as $inv) {
                        $clientIban = $inv['client_iban'] ?? '';
                        if ($clientIban && $normalizedSenderIban === $this->normalizeIban($clientIban)) {
                            $invAmount = (float)($inv['document_exports_gross'] ?? 0);
                            $paidAmount = (float)($inv['paid_amount'] ?? 0);
                            $remaining = round($invAmount - $paidAmount, 2);
                            if ($remaining > 0 && abs($txAmount - $remaining) < 0.01) {
                                $matchedDocId = (int)$inv['document_exports_id'];
                                $matchMethod = 'auto_matched';
                                break;
                            }
                        }
                    }
                }
            }

            // Match gefunden: Transaktion aktualisieren
            if ($matchedDocId) {
                $this->db->where('id', $tx['id']);
                $this->db->update('bank_transactions', [
                    'matched_document_id' => $matchedDocId,
                    'match_status'        => 'auto_matched',
                ]);

                // Zahlungsstatus der Rechnung aktualisieren
                $this->updatePaymentStatus($matchedDocId, $txAmount, $tx['reference'] ?? '');

                $matched++;
            }
        }

        return $matched;
    }

    // ═══════════════════════════════════════════════
    //  MANUELLES MATCHING
    // ═══════════════════════════════════════════════

    /**
     * Transaktion manuell einer Rechnung zuordnen.
     *
     * @param int $transactionId  bank_transactions.id
     * @param int $documentId     document_exports.document_exports_id
     * @return bool
     */
    public function manualMatch(int $transactionId, int $documentId): bool
    {
        $this->db->where('id', $transactionId);
        $tx = $this->db->getOne('bank_transactions');
        if (!$tx) return false;

        $this->db->where('id', $transactionId);
        $result = $this->db->update('bank_transactions', [
            'matched_document_id' => $documentId,
            'match_status'        => 'manual_matched',
        ]);

        if ($result) {
            $this->updatePaymentStatus($documentId, (float)$tx['amount'], $tx['reference'] ?? '');
        }

        return $result;
    }

    // ═══════════════════════════════════════════════
    //  IGNORIEREN
    // ═══════════════════════════════════════════════

    /**
     * Transaktion als ignoriert markieren (z.B. Gebuehren, interne Buchungen).
     *
     * @param int $transactionId  bank_transactions.id
     * @return bool
     */
    public function ignoreTransaction(int $transactionId): bool
    {
        $this->db->where('id', $transactionId);
        $tx = $this->db->getOne('bank_transactions');
        if (!$tx) return false;

        $this->db->where('id', $transactionId);
        return $this->db->update('bank_transactions', [
            'match_status' => 'ignored',
        ]);
    }

    // ═══════════════════════════════════════════════
    //  ABFRAGEN
    // ═══════════════════════════════════════════════

    /**
     * Alle nicht zugeordneten Transaktionen einer Instanz auflisten.
     *
     * @param int $instanceId
     * @return array
     */
    public function getUnmatched(int $instanceId): array
    {
        $this->db->where('bt.instances_id', $instanceId);
        $this->db->where('bt.match_status', 'unmatched');
        $this->db->orderBy('bt.transaction_date', 'DESC');
        return $this->db->get('bank_transactions bt') ?: [];
    }

    /**
     * Transaktionen mit optionalen Filtern auflisten.
     *
     * @param int    $instanceId
     * @param string $status      Filter nach match_status (optional)
     * @param string $batchId     Filter nach import_batch (optional)
     * @param int    $limit
     * @return array
     */
    public function getTransactions(int $instanceId, ?string $status = null, ?string $batchId = null, int $limit = 100): array
    {
        $this->db->where('bt.instances_id', $instanceId);

        if ($status) {
            $this->db->where('bt.match_status', $status);
        }
        if ($batchId) {
            $this->db->where('bt.import_batch', $batchId);
        }

        $this->db->join('document_exports de', 'bt.matched_document_id=de.document_exports_id', 'LEFT');
        $this->db->orderBy('bt.transaction_date', 'DESC');

        return $this->db->get('bank_transactions bt', $limit, [
            'bt.*',
            'de.document_exports_number',
            'de.document_exports_gross',
        ]) ?: [];
    }

    /**
     * Import-Verlauf einer Instanz auflisten.
     *
     * @param int $instanceId
     * @param int $limit
     * @return array
     */
    public function getImportLog(int $instanceId, int $limit = 50): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->orderBy('imported_at', 'DESC');
        return $this->db->get('bank_import_log', $limit) ?: [];
    }

    /**
     * Offene Rechnungen fuer manuelles Matching suchen.
     *
     * @param int    $instanceId
     * @param string $query  Suchbegriff (Rechnungsnummer, Kundenname)
     * @return array
     */
    public function searchInvoices(int $instanceId, string $query = ''): array
    {
        $this->db->where('de.instances_id', $instanceId);
        $this->db->where('de.document_exports_status', ['sent', 'overdue'], 'IN');

        if ($query) {
            $this->db->where('(de.document_exports_number LIKE ? OR c.clients_name LIKE ?)',
                ["%{$query}%", "%{$query}%"]);
        }

        $this->db->join('projects p', 'de.projects_id=p.projects_id', 'LEFT');
        $this->db->join('clients c', 'p.clients_id=c.clients_id', 'LEFT');
        $this->db->orderBy('de.document_exports_date', 'DESC');

        return $this->db->get('document_exports de', 20, [
            'de.document_exports_id',
            'de.document_exports_number',
            'de.document_exports_gross',
            'de.document_exports_status',
            'c.clients_name',
            'p.projects_name',
        ]) ?: [];
    }

    // ═══════════════════════════════════════════════
    //  HILFSFUNKTIONEN
    // ═══════════════════════════════════════════════

    /**
     * Offene Rechnungen (mit Kunden-IBAN) fuer Auto-Matching laden.
     */
    private function getOpenInvoices(int $instanceId): array
    {
        $this->db->where('de.instances_id', $instanceId);
        $this->db->where('de.document_exports_status', ['sent', 'overdue'], 'IN');
        $this->db->join('projects p', 'de.projects_id=p.projects_id', 'LEFT');
        $this->db->join('clients c', 'p.clients_id=c.clients_id', 'LEFT');

        return $this->db->get('document_exports de', null, [
            'de.document_exports_id',
            'de.document_exports_number',
            'de.document_exports_gross',
            'de.paid_amount',
            'c.clients_name',
            'c.clients_iban as client_iban',
        ]) ?: [];
    }

    /**
     * IBAN normalisieren (Leerzeichen entfernen, Grossbuchstaben).
     */
    private function normalizeIban(string $iban): string
    {
        return strtoupper(preg_replace('/\s+/', '', $iban));
    }

    /**
     * Zahlungsstatus einer Rechnung aktualisieren.
     * Nutzt PaymentTrackingService wenn vorhanden, sonst document_exports direkt.
     */
    private function updatePaymentStatus(int $documentId, float $amount, string $reference): void
    {
        // PaymentTrackingService bevorzugen
        $paymentServiceFile = __DIR__ . '/PaymentTrackingService.php';
        if (file_exists($paymentServiceFile)) {
            require_once $paymentServiceFile;
            $paymentService = new PaymentTrackingService($this->db);
            try {
                $paymentService->recordPayment(
                    $documentId,
                    $amount,
                    date('Y-m-d'),
                    'bank_transfer',
                    $reference
                );
                return;
            } catch (\Exception $e) {
                // Fallback zu direktem Update
            }
        }

        // Fallback: document_exports direkt aktualisieren
        $this->db->where('document_exports_id', $documentId);
        $invoice = $this->db->getOne('document_exports');
        if (!$invoice) return;

        $previousPaid = (float)($invoice['paid_amount'] ?? 0);
        $totalPaid = round($previousPaid + $amount, 2);
        $gross = (float)($invoice['document_exports_gross'] ?? 0);
        $remaining = round($gross - $totalPaid, 2);

        $updateData = ['paid_amount' => $totalPaid];
        if ($remaining <= 0.01) {
            $updateData['document_exports_status'] = 'paid';
        }

        $this->db->where('document_exports_id', $documentId);
        $this->db->update('document_exports', $updateData);
    }

    /**
     * Import-Vorgang protokollieren.
     */
    private function logImport(int $instanceId, string $filename, string $format, int $imported, int $matched): void
    {
        $this->db->insert('bank_import_log', [
            'instances_id'     => $instanceId,
            'filename'         => $filename,
            'format'           => $format,
            'records_imported' => $imported,
            'records_matched'  => $matched,
            'imported_at'      => date('Y-m-d H:i:s'),
        ]);
    }
}
