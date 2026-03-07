<?php
/**
 * Bank-agnostischer Kontoauszug-Import
 *
 * Unterstuetzte Formate:
 * - MT940 (SWIFT, alle Banken via jejik/mt940)
 * - CAMT.053 (ISO 20022 XML, nativ geparst)
 * - CSV (frei konfigurierbares Spalten-Mapping)
 *
 * Features:
 * - Automatisches Matching von Zahlungseingaengen zu offenen Rechnungen
 * - Duplikaterkennung via Hash
 * - Teilzahlungen
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
    //  BANKKONTEN VERWALTEN
    // ═══════════════════════════════════════════════

    public function getAccounts(int $instanceId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('deleted', 0);
        $this->db->orderBy('is_default', 'DESC');
        $this->db->orderBy('account_name', 'ASC');
        return $this->db->get('bank_accounts') ?: [];
    }

    public function getAccount(int $instanceId, int $accountId): ?array
    {
        $this->db->where('id', $accountId);
        $this->db->where('instances_id', $instanceId);
        $this->db->where('deleted', 0);
        return $this->db->getOne('bank_accounts') ?: null;
    }

    public function createAccount(int $instanceId, array $data): int
    {
        if (!empty($data['is_default'])) {
            $this->db->where('instances_id', $instanceId);
            $this->db->update('bank_accounts', ['is_default' => 0]);
        }

        $this->db->insert('bank_accounts', [
            'instances_id' => $instanceId,
            'account_name' => $data['account_name'],
            'iban'         => $data['iban'] ?? null,
            'bic'          => $data['bic'] ?? null,
            'bank_name'    => $data['bank_name'] ?? null,
            'currency'     => $data['currency'] ?? 'EUR',
            'is_default'   => !empty($data['is_default']) ? 1 : 0,
        ]);

        return $this->db->getInsertId();
    }

    public function updateAccount(int $instanceId, int $accountId, array $data): bool
    {
        if (!empty($data['is_default'])) {
            $this->db->where('instances_id', $instanceId);
            $this->db->update('bank_accounts', ['is_default' => 0]);
        }

        $update = [];
        foreach (['account_name', 'iban', 'bic', 'bank_name', 'currency', 'is_default'] as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }

        $this->db->where('id', $accountId);
        $this->db->where('instances_id', $instanceId);
        return $this->db->update('bank_accounts', $update);
    }

    public function deleteAccount(int $instanceId, int $accountId): bool
    {
        $this->db->where('id', $accountId);
        $this->db->where('instances_id', $instanceId);
        return $this->db->update('bank_accounts', ['deleted' => 1]);
    }

    // ═══════════════════════════════════════════════
    //  DATEI-IMPORT
    // ═══════════════════════════════════════════════

    /**
     * Importiert eine Kontoauszugsdatei und gibt die Session-ID zurueck
     *
     * @param int    $instanceId
     * @param string $filePath   Pfad zur hochgeladenen Datei
     * @param string $filename   Original-Dateiname
     * @param string $format     'mt940', 'camt053', 'csv'
     * @param int    $userId
     * @param int|null $bankAccountId
     * @param array  $csvMapping  Nur fuer CSV: Spalten-Mapping
     * @return array ['session_id' => int, 'transactions' => int, 'matched' => int, 'duplicates' => int]
     */
    public function importFile(
        int $instanceId,
        string $filePath,
        string $filename,
        string $format,
        int $userId,
        ?int $bankAccountId = null,
        array $csvMapping = []
    ): array {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException("Datei konnte nicht gelesen werden.");
        }

        // Parse je nach Format
        switch ($format) {
            case 'mt940':
                $parsed = $this->parseMT940($content);
                break;
            case 'camt053':
                $parsed = $this->parseCAMT053($content);
                break;
            case 'csv':
                $parsed = $this->parseCSV($content, $csvMapping);
                break;
            default:
                throw new \InvalidArgumentException("Unbekanntes Format: {$format}");
        }

        // Session anlegen
        $this->db->insert('bank_import_sessions', [
            'instances_id'    => $instanceId,
            'bank_accounts_id' => $bankAccountId,
            'format'          => $format,
            'filename'        => $filename,
            'statement_date'  => $parsed['statement_date'],
            'opening_balance' => $parsed['opening_balance'],
            'closing_balance' => $parsed['closing_balance'],
            'transaction_count' => count($parsed['transactions']),
            'imported_by'     => $userId,
        ]);
        $sessionId = $this->db->getInsertId();

        // Transaktionen einfuegen
        $duplicates = 0;
        $inserted = 0;
        foreach ($parsed['transactions'] as $tx) {
            $hash = $this->computeDuplicateHash($tx);

            // Duplikatpruefung
            $this->db->where('instances_id', $instanceId);
            $this->db->where('duplicate_hash', $hash);
            if ($this->db->getOne('bank_transactions')) {
                $duplicates++;
                continue;
            }

            $this->db->insert('bank_transactions', [
                'instances_id'           => $instanceId,
                'bank_import_sessions_id' => $sessionId,
                'transaction_date'       => $tx['date'],
                'value_date'             => $tx['value_date'],
                'amount'                 => $tx['amount'],
                'currency'               => $tx['currency'] ?? 'EUR',
                'counterpart_name'       => $tx['counterpart_name'],
                'counterpart_iban'       => $tx['counterpart_iban'],
                'reference'              => $tx['reference'],
                'booking_text'           => $tx['booking_text'],
                'end_to_end_id'          => $tx['end_to_end_id'],
                'mandate_reference'      => $tx['mandate_reference'],
                'duplicate_hash'         => $hash,
            ]);
            $inserted++;
        }

        // Auto-Matching durchfuehren
        $matched = $this->autoMatch($instanceId, $sessionId);

        // Session-Zaehler aktualisieren
        $this->db->where('id', $sessionId);
        $this->db->update('bank_import_sessions', [
            'transaction_count' => $inserted,
            'matched_count'     => $matched,
        ]);

        return [
            'session_id'   => $sessionId,
            'transactions' => $inserted,
            'matched'      => $matched,
            'duplicates'   => $duplicates,
        ];
    }

    // ═══════════════════════════════════════════════
    //  MT940 PARSER (via jejik/mt940)
    // ═══════════════════════════════════════════════

    private function parseMT940(string $content): array
    {
        $reader = new \Jejik\MT940\Reader();
        // Alle verfuegbaren Bank-Parser aktivieren
        $reader->addParsers($reader->getDefaultParsers());

        $statements = $reader->getStatements($content);

        $transactions = [];
        $statementDate = null;
        $openingBalance = null;
        $closingBalance = null;

        foreach ($statements as $stmt) {
            if ($stmt->getOpeningBalance()) {
                $openingBalance = $openingBalance ?? $stmt->getOpeningBalance()->getAmount();
                $statementDate = $statementDate ?? ($stmt->getOpeningBalance()->getDate() ? $stmt->getOpeningBalance()->getDate()->format('Y-m-d') : null);
            }
            if ($stmt->getClosingBalance()) {
                $closingBalance = $stmt->getClosingBalance()->getAmount();
            }

            foreach ($stmt->getTransactions() as $tx) {
                $contraAccount = $tx->getContraAccount();
                $transactions[] = [
                    'date'               => $tx->getBookDate() ? $tx->getBookDate()->format('Y-m-d') : ($tx->getValueDate() ? $tx->getValueDate()->format('Y-m-d') : date('Y-m-d')),
                    'value_date'         => $tx->getValueDate() ? $tx->getValueDate()->format('Y-m-d') : null,
                    'amount'             => $tx->getAmount(),
                    'currency'           => 'EUR',
                    'counterpart_name'   => $tx->getAccountHolder() ?? ($contraAccount ? $contraAccount->getName() : null),
                    'counterpart_iban'   => $tx->getIBAN() ?? ($contraAccount ? $contraAccount->getNumber() : null),
                    'reference'          => $tx->getSvwz() ?? $tx->getDescription(),
                    'booking_text'       => $tx->getTxText() ?? $tx->getCode(),
                    'end_to_end_id'      => $tx->getEref(),
                    'mandate_reference'  => $tx->getMref(),
                ];
            }
        }

        return [
            'statement_date'  => $statementDate,
            'opening_balance' => $openingBalance,
            'closing_balance' => $closingBalance,
            'transactions'    => $transactions,
        ];
    }

    // ═══════════════════════════════════════════════
    //  CAMT.053 PARSER (nativ, kein moneyphp-Upgrade noetig)
    // ═══════════════════════════════════════════════

    private function parseCAMT053(string $content): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        if ($xml === false) {
            throw new \RuntimeException("Ungueltige CAMT.053 XML-Datei.");
        }

        // Namespace handling - CAMT.053 kann verschiedene Versionen haben
        $namespaces = $xml->getNamespaces(true);
        $ns = reset($namespaces) ?: '';

        // Registriere Default-Namespace
        if ($ns) {
            $xml->registerXPathNamespace('camt', $ns);
            $prefix = 'camt:';
        } else {
            $prefix = '';
        }

        $transactions = [];
        $statementDate = null;
        $openingBalance = null;
        $closingBalance = null;

        // BkToCstmrStmt/Stmt (Bank-to-Customer Statement)
        $stmts = $xml->xpath("//{$prefix}Stmt") ?: $xml->xpath("//{$prefix}BkToCstmrStmt/{$prefix}Stmt") ?: [];

        foreach ($stmts as $stmt) {
            if ($ns) $stmt->registerXPathNamespace('camt', $ns);

            // Balances
            $bals = $stmt->xpath("{$prefix}Bal") ?: [];
            foreach ($bals as $bal) {
                if ($ns) $bal->registerXPathNamespace('camt', $ns);
                $tpNodes = $bal->xpath("{$prefix}Tp/{$prefix}CdOrPrtry/{$prefix}Cd");
                $tp = $tpNodes ? (string)$tpNodes[0] : '';
                $amtNodes = $bal->xpath("{$prefix}Amt");
                $amt = $amtNodes ? (float)(string)$amtNodes[0] : 0;
                $cdtDbtNodes = $bal->xpath("{$prefix}CdtDbtInd");
                $cdtDbt = $cdtDbtNodes ? (string)$cdtDbtNodes[0] : '';
                if ($cdtDbt === 'DBIT') $amt = -$amt;

                $dtNodes = $bal->xpath("{$prefix}Dt/{$prefix}Dt");
                $dt = $dtNodes ? (string)$dtNodes[0] : null;

                if ($tp === 'OPBD' || $tp === 'PRCD') {
                    $openingBalance = $openingBalance ?? $amt;
                    $statementDate = $statementDate ?? $dt;
                } elseif ($tp === 'CLBD' || $tp === 'CLAV') {
                    $closingBalance = $amt;
                }
            }

            // Entries
            $entries = $stmt->xpath("{$prefix}Ntry") ?: [];
            foreach ($entries as $ntry) {
                if ($ns) $ntry->registerXPathNamespace('camt', $ns);

                $amtNodes = $ntry->xpath("{$prefix}Amt");
                $amount = $amtNodes ? (float)(string)$amtNodes[0] : 0;
                $currencyAttr = $amtNodes && $amtNodes[0]->attributes() ? (string)$amtNodes[0]->attributes()->Ccy : 'EUR';

                $cdtDbtNodes = $ntry->xpath("{$prefix}CdtDbtInd");
                $cdtDbt = $cdtDbtNodes ? (string)$cdtDbtNodes[0] : '';
                if ($cdtDbt === 'DBIT') $amount = -$amount;

                // Buchungsdatum
                $bookDtNodes = $ntry->xpath("{$prefix}BookgDt/{$prefix}Dt");
                $bookDate = $bookDtNodes ? (string)$bookDtNodes[0] : null;
                // Valuta
                $valDtNodes = $ntry->xpath("{$prefix}ValDt/{$prefix}Dt");
                $valDate = $valDtNodes ? (string)$valDtNodes[0] : null;

                // Buchungstext
                $addInfoNodes = $ntry->xpath("{$prefix}AddtlNtryInf");
                $bookingText = $addInfoNodes ? (string)$addInfoNodes[0] : null;

                // Details aus NtryDtls/TxDtls
                $txDtls = $ntry->xpath("{$prefix}NtryDtls/{$prefix}TxDtls");
                $counterpartName = null;
                $counterpartIban = null;
                $reference = null;
                $endToEndId = null;
                $mandateRef = null;

                if ($txDtls) {
                    $td = $txDtls[0];
                    if ($ns) $td->registerXPathNamespace('camt', $ns);

                    // Gegenkonto - fuer Eingaenge Dbtr, fuer Ausgaenge Cdtr
                    if ($cdtDbt === 'CRDT') {
                        // Eingang: Debtor ist der Zahlende
                        $nameNodes = $td->xpath("{$prefix}RltdPties/{$prefix}Dbtr/{$prefix}Nm")
                            ?: $td->xpath("{$prefix}RltdPties/{$prefix}Dbtr/{$prefix}Pty/{$prefix}Nm");
                        $ibanNodes = $td->xpath("{$prefix}RltdPties/{$prefix}DbtrAcct/{$prefix}Id/{$prefix}IBAN");
                    } else {
                        // Ausgang: Creditor ist der Empfaenger
                        $nameNodes = $td->xpath("{$prefix}RltdPties/{$prefix}Cdtr/{$prefix}Nm")
                            ?: $td->xpath("{$prefix}RltdPties/{$prefix}Cdtr/{$prefix}Pty/{$prefix}Nm");
                        $ibanNodes = $td->xpath("{$prefix}RltdPties/{$prefix}CdtrAcct/{$prefix}Id/{$prefix}IBAN");
                    }
                    $counterpartName = $nameNodes ? (string)$nameNodes[0] : null;
                    $counterpartIban = $ibanNodes ? (string)$ibanNodes[0] : null;

                    // Verwendungszweck
                    $ustrdNodes = $td->xpath("{$prefix}RmtInf/{$prefix}Ustrd");
                    $reference = $ustrdNodes ? (string)$ustrdNodes[0] : null;

                    // End-to-End-ID
                    $e2eNodes = $td->xpath("{$prefix}Refs/{$prefix}EndToEndId");
                    $endToEndId = $e2eNodes ? (string)$e2eNodes[0] : null;
                    if ($endToEndId === 'NOTPROVIDED') $endToEndId = null;

                    // Mandatsreferenz
                    $mndtNodes = $td->xpath("{$prefix}Refs/{$prefix}MndtId");
                    $mandateRef = $mndtNodes ? (string)$mndtNodes[0] : null;
                }

                $transactions[] = [
                    'date'              => $bookDate ?? $valDate ?? date('Y-m-d'),
                    'value_date'        => $valDate,
                    'amount'            => $amount,
                    'currency'          => $currencyAttr,
                    'counterpart_name'  => $counterpartName,
                    'counterpart_iban'  => $counterpartIban,
                    'reference'         => $reference,
                    'booking_text'      => $bookingText ? mb_substr($bookingText, 0, 100) : null,
                    'end_to_end_id'     => $endToEndId,
                    'mandate_reference' => $mandateRef,
                ];
            }
        }

        return [
            'statement_date'  => $statementDate,
            'opening_balance' => $openingBalance,
            'closing_balance' => $closingBalance,
            'transactions'    => $transactions,
        ];
    }

    // ═══════════════════════════════════════════════
    //  CSV PARSER (frei konfigurierbares Spalten-Mapping)
    // ═══════════════════════════════════════════════

    /**
     * @param array $mapping Spalten-Mapping, z.B.:
     *   ['date' => 0, 'amount' => 3, 'reference' => 5, 'counterpart_name' => 2,
     *    'delimiter' => ';', 'skip_rows' => 1, 'date_format' => 'd.m.Y', 'decimal_separator' => ',']
     */
    private function parseCSV(string $content, array $mapping): array
    {
        $delimiter = $mapping['delimiter'] ?? ';';
        $skipRows = (int)($mapping['skip_rows'] ?? 1);
        $dateFormat = $mapping['date_format'] ?? 'd.m.Y';
        $decimalSep = $mapping['decimal_separator'] ?? ',';
        $encoding = $mapping['encoding'] ?? 'auto';

        // Encoding-Erkennung
        if ($encoding === 'auto') {
            $detected = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'ISO-8859-15', 'Windows-1252'], true);
            if ($detected && $detected !== 'UTF-8') {
                $content = mb_convert_encoding($content, 'UTF-8', $detected);
            }
        } elseif ($encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }

        $lines = str_getcsv_lines($content, $delimiter);
        $transactions = [];

        for ($i = $skipRows; $i < count($lines); $i++) {
            $row = $lines[$i];
            if (count($row) < 2) continue;

            // Datum parsen
            $dateCol = $mapping['date'] ?? 0;
            $rawDate = trim($row[$dateCol] ?? '');
            $dateObj = \DateTime::createFromFormat($dateFormat, $rawDate);
            $date = $dateObj ? $dateObj->format('Y-m-d') : null;
            if (!$date) continue;

            // Betrag parsen
            $amountCol = $mapping['amount'] ?? 3;
            $rawAmount = trim($row[$amountCol] ?? '0');
            $rawAmount = str_replace('.', '', $rawAmount); // Tausenderpunkte entfernen
            $rawAmount = str_replace($decimalSep, '.', $rawAmount);
            $amount = (float)$rawAmount;

            // Haben/Soll-Spalte (optional)
            if (isset($mapping['credit_debit'])) {
                $cdCol = $mapping['credit_debit'];
                $cd = strtoupper(trim($row[$cdCol] ?? ''));
                if (in_array($cd, ['S', 'SOLL', 'D', 'DBIT'])) {
                    $amount = -abs($amount);
                } else {
                    $amount = abs($amount);
                }
            }

            $transactions[] = [
                'date'              => $date,
                'value_date'        => isset($mapping['value_date']) ? $this->parseCsvDate($row[$mapping['value_date']] ?? '', $dateFormat) : null,
                'amount'            => $amount,
                'currency'          => isset($mapping['currency']) ? trim($row[$mapping['currency']] ?? 'EUR') : 'EUR',
                'counterpart_name'  => isset($mapping['counterpart_name']) ? trim($row[$mapping['counterpart_name']] ?? '') : null,
                'counterpart_iban'  => isset($mapping['counterpart_iban']) ? trim($row[$mapping['counterpart_iban']] ?? '') : null,
                'reference'         => isset($mapping['reference']) ? trim($row[$mapping['reference']] ?? '') : null,
                'booking_text'      => isset($mapping['booking_text']) ? mb_substr(trim($row[$mapping['booking_text']] ?? ''), 0, 100) : null,
                'end_to_end_id'     => null,
                'mandate_reference' => null,
            ];
        }

        return [
            'statement_date'  => !empty($transactions) ? $transactions[0]['date'] : null,
            'opening_balance' => null,
            'closing_balance' => null,
            'transactions'    => $transactions,
        ];
    }

    private function parseCsvDate(string $raw, string $format): ?string
    {
        $raw = trim($raw);
        if ($raw === '') return null;
        $dt = \DateTime::createFromFormat($format, $raw);
        return $dt ? $dt->format('Y-m-d') : null;
    }

    // ═══════════════════════════════════════════════
    //  AUTO-MATCHING
    // ═══════════════════════════════════════════════

    /**
     * Versucht Zahlungseingaenge automatisch offenen Rechnungen zuzuordnen.
     * Matching-Strategien (in Prioritaetsreihenfolge):
     * 1. Rechnungsnummer im Verwendungszweck (hohe Konfidenz)
     * 2. End-to-End-ID = Rechnungsnummer (hohe Konfidenz)
     * 3. Exakter Betrag + Kundenname (mittlere Konfidenz)
     */
    private function autoMatch(int $instanceId, int $sessionId): int
    {
        // Alle ungematchten Eingaenge der Session holen
        $this->db->where('instances_id', $instanceId);
        $this->db->where('bank_import_sessions_id', $sessionId);
        $this->db->where('match_status', 'unmatched');
        $this->db->where('amount', 0, '>');
        $txs = $this->db->get('bank_transactions') ?: [];

        // Alle offenen Rechnungen holen
        $this->db->where('instances_id', $instanceId);
        $this->db->where('doc_type', 'invoice');
        $this->db->where('status', ['sent', 'overdue', 'reminded'], 'IN');
        $this->db->join('projects p', 'document_lifecycle.projects_id=p.projects_id', 'LEFT');
        $this->db->join('clients c', 'p.clients_id=c.clients_id', 'LEFT');
        $invoices = $this->db->get('document_lifecycle', null, [
            'document_lifecycle.*', 'c.clients_name'
        ]) ?: [];

        if (empty($invoices) || empty($txs)) return 0;

        $matched = 0;
        foreach ($txs as $tx) {
            $bestMatch = null;
            $bestConfidence = 0;
            $bestMethod = null;

            $ref = strtolower($tx['reference'] ?? '');
            $e2e = strtolower($tx['end_to_end_id'] ?? '');
            $txAmount = (float)$tx['amount'];

            foreach ($invoices as $inv) {
                $docNum = strtolower($inv['doc_number']);
                $grossAmount = (float)$inv['gross_amount'];
                $paidAmount = (float)($inv['paid_amount'] ?? 0);
                $remaining = round($grossAmount - $paidAmount, 2);

                if ($remaining <= 0) continue;

                // Strategie 1: Rechnungsnummer im Verwendungszweck
                if ($docNum && $ref && strpos($ref, $docNum) !== false) {
                    $confidence = 95;
                    if (abs($txAmount - $remaining) < 0.01) $confidence = 99;
                    if ($confidence > $bestConfidence) {
                        $bestMatch = $inv;
                        $bestConfidence = $confidence;
                        $bestMethod = 'auto_reference';
                    }
                    continue;
                }

                // Strategie 2: End-to-End-ID
                if ($docNum && $e2e && ($e2e === $docNum || strpos($e2e, $docNum) !== false)) {
                    $confidence = 95;
                    if (abs($txAmount - $remaining) < 0.01) $confidence = 99;
                    if ($confidence > $bestConfidence) {
                        $bestMatch = $inv;
                        $bestConfidence = $confidence;
                        $bestMethod = 'auto_e2e';
                    }
                    continue;
                }

                // Strategie 3: Exakter Betrag + Kundenname
                if (abs($txAmount - $remaining) < 0.01) {
                    $clientName = strtolower($inv['clients_name'] ?? '');
                    $counterpart = strtolower($tx['counterpart_name'] ?? '');

                    if ($clientName && $counterpart) {
                        // Fuzzy-Name-Check: einer enthaelt den anderen
                        $nameParts = explode(' ', $clientName);
                        $nameMatch = false;
                        foreach ($nameParts as $part) {
                            if (strlen($part) >= 3 && strpos($counterpart, $part) !== false) {
                                $nameMatch = true;
                                break;
                            }
                        }

                        if ($nameMatch) {
                            $confidence = 75;
                            if ($confidence > $bestConfidence) {
                                $bestMatch = $inv;
                                $bestConfidence = $confidence;
                                $bestMethod = 'auto_amount';
                            }
                        }
                    }
                }
            }

            if ($bestMatch && $bestConfidence >= 60) {
                $this->db->where('id', $tx['id']);
                $this->db->update('bank_transactions', [
                    'match_status'          => 'suggested',
                    'document_lifecycle_id' => $bestMatch['id'],
                    'match_confidence'      => $bestConfidence,
                    'match_method'          => $bestMethod,
                ]);
                $matched++;
            }
        }

        return $matched;
    }

    // ═══════════════════════════════════════════════
    //  MANUELLES MATCHING & BESTAETIUNG
    // ═══════════════════════════════════════════════

    /**
     * Manuell eine Transaktion einer Rechnung zuordnen
     */
    public function manualMatch(int $instanceId, int $transactionId, int $docLifecycleId, int $userId): bool
    {
        // Transaktion pruefen
        $this->db->where('id', $transactionId);
        $this->db->where('instances_id', $instanceId);
        $tx = $this->db->getOne('bank_transactions');
        if (!$tx) return false;

        // Rechnung pruefen
        $this->db->where('id', $docLifecycleId);
        $this->db->where('instances_id', $instanceId);
        $inv = $this->db->getOne('document_lifecycle');
        if (!$inv || $inv['doc_type'] !== 'invoice') return false;

        $this->db->where('id', $transactionId);
        return $this->db->update('bank_transactions', [
            'match_status'          => 'suggested',
            'document_lifecycle_id' => $docLifecycleId,
            'match_confidence'      => 100,
            'match_method'          => 'manual',
            'matched_by'            => $userId,
            'matched_at'            => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Transaktion ignorieren (z.B. Gebuehren, irrelevante Buchungen)
     */
    public function ignoreTransaction(int $instanceId, int $transactionId, int $userId, ?string $note = null): bool
    {
        $this->db->where('id', $transactionId);
        $this->db->where('instances_id', $instanceId);
        $tx = $this->db->getOne('bank_transactions');
        if (!$tx) return false;

        $this->db->where('id', $transactionId);
        return $this->db->update('bank_transactions', [
            'match_status' => 'ignored',
            'matched_by'   => $userId,
            'matched_at'   => date('Y-m-d H:i:s'),
            'notes'        => $note,
        ]);
    }

    /**
     * Einzelne Zuordnung bestaetigen und Zahlung verbuchen
     */
    public function confirmMatch(int $instanceId, int $transactionId, int $userId): bool
    {
        $this->db->where('id', $transactionId);
        $this->db->where('instances_id', $instanceId);
        $this->db->where('match_status', ['suggested', 'unmatched'], 'IN');
        $tx = $this->db->getOne('bank_transactions');
        if (!$tx || !$tx['document_lifecycle_id']) return false;

        // Zahlung verbuchen
        $lifecycle = new DocumentLifecycleService($this->db);
        $amount = (float)$tx['amount'];
        $reference = "Bank-Import: " . ($tx['reference'] ?? $tx['counterpart_name'] ?? 'Kontoauszug');

        // Pruefen ob Rechnung existiert und zur Instanz gehoert
        $this->db->where('id', $tx['document_lifecycle_id']);
        $this->db->where('instances_id', $instanceId);
        $inv = $this->db->getOne('document_lifecycle');
        if (!$inv) return false;

        $previousPaid = (float)($inv['paid_amount'] ?? 0);
        $totalPaid = round($previousPaid + $amount, 2);
        $grossAmount = (float)$inv['gross_amount'];
        $remaining = round($grossAmount - $totalPaid, 2);

        // Rechnung aktualisieren
        $updateData = ['paid_amount' => $totalPaid];
        if ($remaining <= 0.01) {
            $updateData['status'] = 'paid';
            $updateData['paid_date'] = $tx['transaction_date'];
        }

        $this->db->where('id', $inv['id']);
        $this->db->update('document_lifecycle', $updateData);

        // Status-History loggen
        if ($remaining <= 0.01) {
            $lifecycle->changeStatus((int)$inv['id'], 'paid', $userId, $reference, $instanceId);
        } else {
            $this->db->insert('document_status_history', [
                'document_lifecycle_id' => $inv['id'],
                'old_status'            => $inv['status'],
                'new_status'            => $inv['status'],
                'comment'               => "Teilzahlung via Bank-Import: " . number_format($amount, 2, ',', '.') . " EUR - Offen: " . number_format($remaining, 2, ',', '.') . " EUR",
                'changed_by'            => $userId,
            ]);
        }

        // Transaktion als bestaetigt markieren
        $this->db->where('id', $transactionId);
        $this->db->update('bank_transactions', [
            'match_status' => 'confirmed',
            'matched_by'   => $userId,
            'matched_at'   => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    /**
     * Alle vorgeschlagenen Matches einer Session auf einmal bestaetigen
     */
    public function confirmAllSuggested(int $instanceId, int $sessionId, int $userId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('bank_import_sessions_id', $sessionId);
        $this->db->where('match_status', 'suggested');
        $txs = $this->db->get('bank_transactions') ?: [];

        $confirmed = 0;
        $failed = 0;
        foreach ($txs as $tx) {
            if ($this->confirmMatch($instanceId, (int)$tx['id'], $userId)) {
                $confirmed++;
            } else {
                $failed++;
            }
        }

        // Session als bestaetigt markieren wenn alles erledigt
        $this->db->where('instances_id', $instanceId);
        $this->db->where('bank_import_sessions_id', $sessionId);
        $this->db->where('match_status', 'unmatched');
        $remaining = $this->db->getValue('bank_transactions', 'count(*)');

        if ((int)$remaining === 0) {
            $this->db->where('id', $sessionId);
            $this->db->update('bank_import_sessions', [
                'confirmed'    => 1,
                'confirmed_by' => $userId,
                'confirmed_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return ['confirmed' => $confirmed, 'failed' => $failed];
    }

    // ═══════════════════════════════════════════════
    //  ABFRAGEN
    // ═══════════════════════════════════════════════

    /**
     * Import-Sessions einer Instanz auflisten
     */
    public function getSessions(int $instanceId, int $limit = 50): array
    {
        $this->db->where('bis.instances_id', $instanceId);
        $this->db->join('bank_accounts ba', 'bis.bank_accounts_id=ba.id', 'LEFT');
        $this->db->join('users u', 'bis.imported_by=u.users_userid', 'LEFT');
        $this->db->orderBy('bis.created_at', 'DESC');
        return $this->db->get('bank_import_sessions bis', $limit, [
            'bis.*', 'ba.account_name', 'ba.iban as account_iban',
            'u.users_name1', 'u.users_name2'
        ]) ?: [];
    }

    /**
     * Transaktionen einer Session holen
     */
    public function getSessionTransactions(int $instanceId, int $sessionId): array
    {
        $this->db->where('bt.instances_id', $instanceId);
        $this->db->where('bt.bank_import_sessions_id', $sessionId);
        $this->db->join('document_lifecycle dl', 'bt.document_lifecycle_id=dl.id', 'LEFT');
        $this->db->join('projects p', 'dl.projects_id=p.projects_id', 'LEFT');
        $this->db->join('clients c', 'p.clients_id=c.clients_id', 'LEFT');
        $this->db->orderBy('bt.transaction_date', 'ASC');
        return $this->db->get('bank_transactions bt', null, [
            'bt.*',
            'dl.doc_number', 'dl.gross_amount as invoice_gross', 'dl.paid_amount as invoice_paid', 'dl.status as invoice_status',
            'p.projects_name',
            'c.clients_name as invoice_client'
        ]) ?: [];
    }

    /**
     * Offene Rechnungen fuer manuelles Matching suchen
     */
    public function searchInvoicesForMatch(int $instanceId, string $query = '', ?float $amount = null): array
    {
        $this->db->where('dl.instances_id', $instanceId);
        $this->db->where('dl.doc_type', 'invoice');
        $this->db->where('dl.status', ['sent', 'overdue', 'reminded'], 'IN');
        $this->db->join('projects p', 'dl.projects_id=p.projects_id', 'LEFT');
        $this->db->join('clients c', 'p.clients_id=c.clients_id', 'LEFT');

        if ($query) {
            $this->db->where('(dl.doc_number LIKE ? OR c.clients_name LIKE ? OR p.projects_name LIKE ?)',
                ["%{$query}%", "%{$query}%", "%{$query}%"]);
        }

        $this->db->orderBy('dl.due_date', 'ASC');
        $invoices = $this->db->get('document_lifecycle dl', 20, [
            'dl.id', 'dl.doc_number', 'dl.gross_amount', 'dl.paid_amount', 'dl.due_date', 'dl.status',
            'p.projects_name', 'c.clients_name'
        ]) ?: [];

        // Restbetrag berechnen
        foreach ($invoices as &$inv) {
            $inv['remaining'] = round((float)$inv['gross_amount'] - (float)($inv['paid_amount'] ?? 0), 2);
        }
        unset($inv);

        // Bei Betrag-Suche: sortiere nach naechstem Match
        if ($amount !== null && $amount > 0) {
            usort($invoices, function ($a, $b) use ($amount) {
                return abs($a['remaining'] - $amount) <=> abs($b['remaining'] - $amount);
            });
        }

        return $invoices;
    }

    /**
     * Statistik ueber eine Import-Session
     */
    public function getSessionStats(int $instanceId, int $sessionId): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('bank_import_sessions_id', $sessionId);
        $txs = $this->db->get('bank_transactions') ?: [];

        $stats = [
            'total'      => count($txs),
            'unmatched'  => 0,
            'suggested'  => 0,
            'confirmed'  => 0,
            'ignored'    => 0,
            'income'     => 0,
            'expense'    => 0,
        ];

        foreach ($txs as $tx) {
            $stats[$tx['match_status']]++;
            if ((float)$tx['amount'] > 0) {
                $stats['income'] += (float)$tx['amount'];
            } else {
                $stats['expense'] += abs((float)$tx['amount']);
            }
        }

        $stats['income'] = round($stats['income'], 2);
        $stats['expense'] = round($stats['expense'], 2);

        return $stats;
    }

    // ═══════════════════════════════════════════════
    //  HILFSFUNKTIONEN
    // ═══════════════════════════════════════════════

    private function computeDuplicateHash(array $tx): string
    {
        $data = implode('|', [
            $tx['date'] ?? '',
            $tx['value_date'] ?? '',
            (string)$tx['amount'],
            $tx['counterpart_iban'] ?? '',
            $tx['reference'] ?? '',
            $tx['end_to_end_id'] ?? '',
        ]);
        return hash('sha256', $data);
    }
}

/**
 * CSV-Zeilen parsen (PHP str_getcsv arbeitet nur mit einzelnen Zeilen)
 */
function str_getcsv_lines(string $content, string $delimiter = ','): array
{
    $lines = [];
    $rows = explode("\n", str_replace("\r\n", "\n", $content));
    foreach ($rows as $row) {
        $row = trim($row);
        if ($row === '') continue;
        $lines[] = str_getcsv($row, $delimiter);
    }
    return $lines;
}
