<?php
/**
 * FinTS/HBCI Bankanbindung
 *
 * Ermoeglicht direkten Kontoabruf ueber das FinTS-Protokoll (frueheres HBCI).
 * Unterstuetzt alle deutschen Banken mit FinTS-Server (~3000 Institute).
 *
 * Abhaengigkeit: nemiah/php-fints (composer require nemiah/php-fints)
 *
 * Ablauf fuer TAN-pflichtige Aktionen:
 * 1. Client ruft initAction() auf → bekommt ggf. TAN-Challenge zurueck
 * 2. User gibt TAN ein
 * 3. Client ruft submitTan() auf → Aktion wird ausgefuehrt
 *
 * Fuer PIN/TAN-freie Aktionen (selten) laeuft alles in einem Schritt.
 */
class FinTSService
{
    private $db;

    /** @var string Produkt-Registrierung bei Deutsche Kreditwirtschaft (frei fuer Testzwecke) */
    private string $productId = '0';

    public function __construct($db)
    {
        $this->db = $db;
    }

    // ═══════════════════════════════════════════════
    //  KONTO-KONFIGURATION
    // ═══════════════════════════════════════════════

    /**
     * FinTS-Zugangsdaten fuer ein Bankkonto speichern
     */
    public function configureAccount(int $instanceId, int $accountId, array $data): bool
    {
        $allowed = ['fints_url', 'fints_port', 'fints_version', 'fints_username',
                     'fints_blz', 'fints_account_number', 'fints_enabled'];

        $update = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }

        if (empty($update)) return false;

        $this->db->where('id', $accountId);
        $this->db->where('instances_id', $instanceId);
        return $this->db->update('bank_accounts', $update);
    }

    /**
     * Bankenliste fuer Autocomplete (BLZ → FinTS-URL)
     * Gibt eine statische Liste der gaengigsten Banken zurueck.
     * Fuer eine vollstaendige Liste: https://www.hbci-zka.de/institute/institut_auswahl.htm
     */
    public function getBankDirectory(string $query = ''): array
    {
        $banks = self::COMMON_BANKS;

        if ($query) {
            $query = strtolower($query);
            $banks = array_filter($banks, function ($bank) use ($query) {
                return strpos(strtolower($bank['name']), $query) !== false
                    || strpos($bank['blz'], $query) !== false;
            });
        }

        return array_values($banks);
    }

    // ═══════════════════════════════════════════════
    //  AKTION INITIIEREN (Schritt 1: Login + ggf. TAN anfordern)
    // ═══════════════════════════════════════════════

    /**
     * Startet eine FinTS-Aktion (Kontoumsaetze abrufen, Saldo, etc.)
     *
     * @param int    $instanceId
     * @param int    $accountId   bank_accounts.id
     * @param string $pin         Online-Banking PIN (wird NICHT gespeichert)
     * @param string $action      'fetch_transactions', 'get_balance', 'get_accounts'
     * @param int    $userId
     * @param array  $params      Aktions-Parameter (z.B. date_from, date_to)
     * @return array ['session_id' => int, 'needs_tan' => bool, 'challenge' => ?string, 'challenge_image' => ?string, 'tan_mechanisms' => ?array]
     */
    public function initAction(
        int $instanceId,
        int $accountId,
        string $pin,
        string $action,
        int $userId,
        array $params = []
    ): array {
        // Kontodaten laden
        $account = $this->getAccountWithFinTS($instanceId, $accountId);
        if (!$account) {
            throw new \RuntimeException("Bankkonto nicht gefunden oder FinTS nicht konfiguriert.");
        }

        // FinTS-Verbindung aufbauen
        $fints = $this->createFinTSConnection($account, $pin);

        // TAN-Session anlegen
        $this->db->insert('fints_tan_sessions', [
            'instances_id'     => $instanceId,
            'bank_accounts_id' => $accountId,
            'user_id'          => $userId,
            'action'           => $action,
            'action_params'    => json_encode($params),
            'status'           => 'pending',
            'expires_at'       => date('Y-m-d H:i:s', time() + 300), // 5 Minuten
        ]);
        $sessionId = $this->db->getInsertId();

        try {
            // Login
            $login = $fints->login();

            if ($login->needsTan()) {
                // TAN wird benoetigt - Challenge speichern
                $tanRequest = $login->getTanRequest();
                $challengeText = $tanRequest ? $tanRequest->getChallenge() : 'Bitte TAN eingeben';
                $challengeImage = null;
                if ($tanRequest && method_exists($tanRequest, 'getChallengeHhdUc')) {
                    $hhd = $tanRequest->getChallengeHhdUc();
                    if ($hhd) $challengeImage = base64_encode($hhd);
                }

                // Dialog-State serialisieren fuer spaetere Wiederaufnahme
                $persistData = $fints->persist();

                // TAN-Mechanismen abfragen
                $tanMechanisms = [];
                $mechanisms = $fints->getTanMechanisms();
                foreach ($mechanisms as $id => $mech) {
                    $tanMechanisms[] = [
                        'id'   => $id,
                        'name' => $mech->getName(),
                    ];
                }

                // TAN-Medien abfragen
                $tanMedia = [];
                try {
                    $media = $fints->getTanMedia();
                    foreach ($media as $medium) {
                        $tanMedia[] = $medium->getName();
                    }
                } catch (\Exception $e) {
                    // Nicht alle Banken unterstuetzen TAN-Medien-Abfrage
                }

                $this->db->where('id', $sessionId);
                $this->db->update('fints_tan_sessions', [
                    'status'          => 'awaiting_tan',
                    'persist_data'    => $persistData,
                    'challenge_text'  => $challengeText,
                    'challenge_image' => $challengeImage,
                    'tan_mechanism'   => json_encode($tanMechanisms),
                ]);

                return [
                    'session_id'     => $sessionId,
                    'needs_tan'      => true,
                    'challenge'      => $challengeText,
                    'challenge_image' => $challengeImage,
                    'tan_mechanisms' => $tanMechanisms,
                    'tan_media'      => $tanMedia,
                ];
            }

            // Kein TAN noetig → Aktion direkt ausfuehren
            $result = $this->executeAction($fints, $account, $action, $params, $instanceId, $accountId, $userId);
            $fints->close();

            $this->db->where('id', $sessionId);
            $this->db->update('fints_tan_sessions', [
                'status'       => 'completed',
                'result_data'  => json_encode($result),
                'completed_at' => date('Y-m-d H:i:s'),
            ]);

            return [
                'session_id' => $sessionId,
                'needs_tan'  => false,
                'result'     => $result,
            ];

        } catch (\Exception $e) {
            $this->db->where('id', $sessionId);
            $this->db->update('fints_tan_sessions', [
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at'  => date('Y-m-d H:i:s'),
            ]);

            $this->logSync($instanceId, $accountId, $userId, $action, null, null, 0, 0, 0, null, false, $e->getMessage());

            throw $e;
        }
    }

    // ═══════════════════════════════════════════════
    //  TAN EINREICHEN (Schritt 2)
    // ═══════════════════════════════════════════════

    /**
     * TAN fuer eine laufende Session einreichen und Aktion ausfuehren
     */
    public function submitTan(
        int $instanceId,
        int $sessionId,
        string $tan,
        string $pin,
        int $userId
    ): array {
        // Session laden
        $this->db->where('id', $sessionId);
        $this->db->where('instances_id', $instanceId);
        $this->db->where('user_id', $userId);
        $this->db->where('status', 'awaiting_tan');
        $session = $this->db->getOne('fints_tan_sessions');

        if (!$session) {
            throw new \RuntimeException("Keine aktive TAN-Session gefunden.");
        }

        // Abgelaufen?
        if (strtotime($session['expires_at']) < time()) {
            $this->db->where('id', $sessionId);
            $this->db->update('fints_tan_sessions', ['status' => 'expired']);
            throw new \RuntimeException("TAN-Session abgelaufen. Bitte erneut starten.");
        }

        // Kontodaten laden
        $account = $this->getAccountWithFinTS($instanceId, (int)$session['bank_accounts_id']);
        if (!$account) {
            throw new \RuntimeException("Bankkonto nicht mehr verfuegbar.");
        }

        // FinTS-Verbindung aus gespeichertem State wiederherstellen
        $fints = $this->createFinTSConnection($account, $pin);

        try {
            $fints->restore($session['persist_data']);

            // TAN einreichen
            $response = $fints->submitTan($tan);

            if ($response->needsTan()) {
                // Weitere TAN noetig (z.B. bei Decoupled-TAN)
                $persistData = $fints->persist();
                $this->db->where('id', $sessionId);
                $this->db->update('fints_tan_sessions', [
                    'persist_data'   => $persistData,
                    'challenge_text' => 'Bitte weitere TAN eingeben',
                ]);

                return [
                    'session_id' => $sessionId,
                    'needs_tan'  => true,
                    'challenge'  => 'Bitte weitere TAN eingeben',
                ];
            }

            // Aktion ausfuehren
            $params = json_decode($session['action_params'] ?? '{}', true) ?: [];
            $result = $this->executeAction(
                $fints,
                $account,
                $session['action'],
                $params,
                $instanceId,
                (int)$session['bank_accounts_id'],
                $userId
            );

            $fints->close();

            $this->db->where('id', $sessionId);
            $this->db->update('fints_tan_sessions', [
                'status'       => 'completed',
                'result_data'  => json_encode($result),
                'completed_at' => date('Y-m-d H:i:s'),
            ]);

            return [
                'session_id' => $sessionId,
                'needs_tan'  => false,
                'result'     => $result,
            ];

        } catch (\Exception $e) {
            $this->db->where('id', $sessionId);
            $this->db->update('fints_tan_sessions', [
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at'  => date('Y-m-d H:i:s'),
            ]);

            throw $e;
        }
    }

    // ═══════════════════════════════════════════════
    //  AKTIONEN AUSFUEHREN
    // ═══════════════════════════════════════════════

    /**
     * Fuehrt die eigentliche FinTS-Aktion aus (nach erfolgreichem Login/TAN)
     */
    private function executeAction($fints, array $account, string $action, array $params, int $instanceId, int $accountId, int $userId): array
    {
        switch ($action) {
            case 'fetch_transactions':
                return $this->doFetchTransactions($fints, $account, $params, $instanceId, $accountId, $userId);

            case 'get_balance':
                return $this->doGetBalance($fints, $account);

            case 'get_accounts':
                return $this->doGetAccounts($fints);

            default:
                throw new \InvalidArgumentException("Unbekannte Aktion: {$action}");
        }
    }

    /**
     * Kontoumsaetze abrufen und in bank_transactions importieren
     */
    private function doFetchTransactions($fints, array $account, array $params, int $instanceId, int $accountId, int $userId): array
    {
        // Zeitraum bestimmen
        $dateFrom = !empty($params['date_from'])
            ? new \DateTime($params['date_from'])
            : ($account['fints_last_sync_date']
                ? new \DateTime($account['fints_last_sync_date'])
                : new \DateTime('-90 days'));

        $dateTo = !empty($params['date_to'])
            ? new \DateTime($params['date_to'])
            : new \DateTime('today');

        // SEPA-Konto finden
        $sepaAccount = $this->findSepaAccount($fints, $account);
        if (!$sepaAccount) {
            throw new \RuntimeException("SEPA-Konto konnte nicht identifiziert werden. Bitte Kontonummer/IBAN pruefen.");
        }

        // Umsaetze abrufen
        $statements = $fints->getStatementOfAccount($sepaAccount, $dateFrom, $dateTo);

        // In unsere Transaktionsstruktur konvertieren
        $transactions = [];
        foreach ($statements->getStatements() as $stmt) {
            foreach ($stmt->getTransactions() as $tx) {
                $amount = $tx->getAmount();
                if ($tx->getCreditDebit() === 'debit') {
                    $amount = -abs($amount);
                } else {
                    $amount = abs($amount);
                }

                $transactions[] = [
                    'date'              => $tx->getBookingDate() ? $tx->getBookingDate()->format('Y-m-d') : $tx->getValutaDate()->format('Y-m-d'),
                    'value_date'        => $tx->getValutaDate() ? $tx->getValutaDate()->format('Y-m-d') : null,
                    'amount'            => $amount,
                    'currency'          => $account['currency'] ?? 'EUR',
                    'counterpart_name'  => $tx->getName(),
                    'counterpart_iban'  => $tx->getAccountNumber(),
                    'reference'         => $tx->getDescription1() . ' ' . ($tx->getDescription2() ?? ''),
                    'booking_text'      => $tx->getBookingText(),
                    'end_to_end_id'     => $tx->getEndToEndId(),
                    'mandate_reference' => $tx->getMandateId(),
                ];
            }
        }

        // In BankImportService einspeisen
        require_once __DIR__ . '/BankImportService.php';
        $bankImport = new BankImportService($this->db);

        // Session anlegen
        $this->db->insert('bank_import_sessions', [
            'instances_id'     => $instanceId,
            'bank_accounts_id' => $accountId,
            'format'           => 'fints',
            'filename'         => 'FinTS-Abruf ' . $dateFrom->format('d.m.Y') . ' - ' . $dateTo->format('d.m.Y'),
            'statement_date'   => $dateTo->format('Y-m-d'),
            'transaction_count' => count($transactions),
            'imported_by'      => $userId,
        ]);
        $importSessionId = $this->db->getInsertId();

        // Transaktionen einfuegen mit Duplikaterkennung
        $inserted = 0;
        $duplicates = 0;
        foreach ($transactions as $tx) {
            $hash = $this->computeDuplicateHash($tx);

            // Duplikatpruefung
            $this->db->where('instances_id', $instanceId);
            $this->db->where('duplicate_hash', $hash);
            if ($this->db->getOne('bank_transactions')) {
                $duplicates++;
                continue;
            }

            $this->db->insert('bank_transactions', [
                'instances_id'            => $instanceId,
                'bank_import_sessions_id' => $importSessionId,
                'transaction_date'        => $tx['date'],
                'value_date'              => $tx['value_date'],
                'amount'                  => $tx['amount'],
                'currency'                => $tx['currency'],
                'counterpart_name'        => $tx['counterpart_name'],
                'counterpart_iban'        => $tx['counterpart_iban'],
                'reference'               => $tx['reference'],
                'booking_text'            => $tx['booking_text'],
                'end_to_end_id'           => $tx['end_to_end_id'],
                'mandate_reference'       => $tx['mandate_reference'],
                'duplicate_hash'          => $hash,
            ]);
            $inserted++;
        }

        // Auto-Matching via BankImportService (nutzt existierende Logik)
        $matched = $this->runAutoMatch($instanceId, $importSessionId);

        // Session aktualisieren
        $this->db->where('id', $importSessionId);
        $this->db->update('bank_import_sessions', [
            'transaction_count' => $inserted,
            'matched_count'     => $matched,
        ]);

        // Bankkonto Sync-Status aktualisieren
        $this->db->where('id', $accountId);
        $this->db->update('bank_accounts', [
            'fints_last_sync'      => date('Y-m-d H:i:s'),
            'fints_last_sync_date' => $dateTo->format('Y-m-d'),
        ]);

        // Sync-Log schreiben
        $this->logSync($instanceId, $accountId, $userId, 'fetch_transactions',
            $dateFrom->format('Y-m-d'), $dateTo->format('Y-m-d'),
            count($transactions), $inserted, $duplicates, $importSessionId, true, null);

        return [
            'import_session_id' => $importSessionId,
            'total_fetched'     => count($transactions),
            'new_transactions'  => $inserted,
            'duplicates'        => $duplicates,
            'auto_matched'      => $matched,
            'date_from'         => $dateFrom->format('Y-m-d'),
            'date_to'           => $dateTo->format('Y-m-d'),
        ];
    }

    /**
     * Kontostand abrufen
     */
    private function doGetBalance($fints, array $account): array
    {
        $sepaAccount = $this->findSepaAccount($fints, $account);
        if (!$sepaAccount) {
            throw new \RuntimeException("SEPA-Konto konnte nicht identifiziert werden.");
        }

        $balance = $fints->getBalance($sepaAccount);

        return [
            'balance'  => $balance->getAmount(),
            'currency' => $balance->getCurrency() ?? $account['currency'] ?? 'EUR',
            'date'     => $balance->getDate() ? $balance->getDate()->format('Y-m-d') : date('Y-m-d'),
        ];
    }

    /**
     * Verfuegbare Konten bei der Bank abrufen (fuer Ersteinrichtung)
     */
    private function doGetAccounts($fints): array
    {
        $sepaAccounts = $fints->getAccounts();
        $result = [];

        foreach ($sepaAccounts as $acc) {
            $result[] = [
                'iban'           => $acc->getIban(),
                'bic'            => $acc->getBic(),
                'account_number' => $acc->getAccountNumber(),
                'blz'            => $acc->getBlz(),
                'owner_name'     => $acc->getAccountOwnerName(),
                'product_name'   => $acc->getProductName(),
            ];
        }

        return ['accounts' => $result];
    }

    // ═══════════════════════════════════════════════
    //  STATUS & SYNC-LOG
    // ═══════════════════════════════════════════════

    /**
     * TAN-Session-Status abfragen
     */
    public function getSessionStatus(int $instanceId, int $sessionId, int $userId): ?array
    {
        $this->db->where('id', $sessionId);
        $this->db->where('instances_id', $instanceId);
        $this->db->where('user_id', $userId);
        $session = $this->db->getOne('fints_tan_sessions');

        if (!$session) return null;

        // Abgelaufen?
        if ($session['status'] === 'awaiting_tan' && strtotime($session['expires_at']) < time()) {
            $this->db->where('id', $sessionId);
            $this->db->update('fints_tan_sessions', ['status' => 'expired']);
            $session['status'] = 'expired';
        }

        return [
            'id'              => (int)$session['id'],
            'action'          => $session['action'],
            'status'          => $session['status'],
            'challenge_text'  => $session['challenge_text'],
            'challenge_image' => $session['challenge_image'],
            'error_message'   => $session['error_message'],
            'result'          => $session['result_data'] ? json_decode($session['result_data'], true) : null,
            'expires_at'      => $session['expires_at'],
            'created_at'      => $session['created_at'],
        ];
    }

    /**
     * Sync-History fuer ein Bankkonto
     */
    public function getSyncLog(int $instanceId, int $accountId, int $limit = 20): array
    {
        $this->db->where('instances_id', $instanceId);
        $this->db->where('bank_accounts_id', $accountId);
        $this->db->orderBy('created_at', 'DESC');
        return $this->db->get('fints_sync_log', $limit) ?: [];
    }

    /**
     * Abgelaufene TAN-Sessions aufraeumen (Cronjob)
     */
    public function cleanupExpiredSessions(): int
    {
        $this->db->where('status', 'awaiting_tan');
        $this->db->where('expires_at', date('Y-m-d H:i:s'), '<');
        $this->db->update('fints_tan_sessions', [
            'status'     => 'expired',
            'persist_data' => null, // Sensible Daten loeschen
        ]);
        $cleaned = $this->db->affectedRows();

        // Alte abgeschlossene Sessions loeschen (aelter als 24h)
        $this->db->where('status', ['completed', 'failed', 'expired'], 'IN');
        $this->db->where('created_at', date('Y-m-d H:i:s', time() - 86400), '<');
        $this->db->update('fints_tan_sessions', ['persist_data' => null]);

        return $cleaned;
    }

    // ═══════════════════════════════════════════════
    //  HILFSFUNKTIONEN
    // ═══════════════════════════════════════════════

    private function getAccountWithFinTS(int $instanceId, int $accountId): ?array
    {
        $this->db->where('id', $accountId);
        $this->db->where('instances_id', $instanceId);
        $this->db->where('deleted', 0);
        $account = $this->db->getOne('bank_accounts');

        if (!$account || empty($account['fints_url']) || empty($account['fints_username']) || empty($account['fints_blz'])) {
            return null;
        }

        return $account;
    }

    /**
     * FinTS-Verbindung erstellen
     * PIN wird nur im Memory gehalten und NICHT gespeichert.
     */
    private function createFinTSConnection(array $account, string $pin)
    {
        if (!class_exists('\Fhp\FinTs')) {
            throw new \RuntimeException("FinTS-Library nicht installiert. Bitte 'composer require nemiah/php-fints' ausfuehren.");
        }

        $options = new \Fhp\Options\FinTsOptions();
        $options->url = $account['fints_url'];
        $options->port = (int)($account['fints_port'] ?? 443);
        $options->bankCode = $account['fints_blz'];
        $options->productId = $this->productId;

        $credentials = \Fhp\Options\Credentials::create(
            $account['fints_username'],
            $pin
        );

        return \Fhp\FinTs::new($options, $credentials);
    }

    /**
     * SEPA-Konto aus der Liste der verfuegbaren Konten identifizieren
     */
    private function findSepaAccount($fints, array $account)
    {
        $sepaAccounts = $fints->getAccounts();
        $targetIban = strtoupper(str_replace(' ', '', $account['iban'] ?? ''));
        $targetNumber = $account['fints_account_number'] ?? '';

        foreach ($sepaAccounts as $sepa) {
            // Erst IBAN pruefen
            if ($targetIban && strtoupper(str_replace(' ', '', $sepa->getIban())) === $targetIban) {
                return $sepa;
            }
            // Dann Kontonummer
            if ($targetNumber && $sepa->getAccountNumber() === $targetNumber) {
                return $sepa;
            }
        }

        // Fallback: erstes Konto
        return !empty($sepaAccounts) ? $sepaAccounts[0] : null;
    }

    /**
     * Auto-Matching via BankImportService aufrufen
     */
    private function runAutoMatch(int $instanceId, int $sessionId): int
    {
        // BankImportService hat eine private autoMatch-Methode,
        // daher muessen wir das Matching hier replizieren
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
                $docNum = strtolower($inv['doc_number'] ?? '');
                $grossAmount = (float)$inv['gross_amount'];
                $paidAmount = (float)($inv['paid_amount'] ?? 0);
                $remaining = round($grossAmount - $paidAmount, 2);

                if ($remaining <= 0) continue;

                // Rechnungsnummer im Verwendungszweck
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

                // End-to-End-ID
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

                // Exakter Betrag + Kundenname
                if (abs($txAmount - $remaining) < 0.01) {
                    $clientName = strtolower($inv['clients_name'] ?? '');
                    $counterpart = strtolower($tx['counterpart_name'] ?? '');
                    if ($clientName && $counterpart) {
                        $nameParts = explode(' ', $clientName);
                        foreach ($nameParts as $part) {
                            if (strlen($part) >= 3 && strpos($counterpart, $part) !== false) {
                                $confidence = 75;
                                if ($confidence > $bestConfidence) {
                                    $bestMatch = $inv;
                                    $bestConfidence = $confidence;
                                    $bestMethod = 'auto_amount';
                                }
                                break;
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

    private function logSync(
        int $instanceId, int $accountId, int $userId, string $action,
        ?string $dateFrom, ?string $dateTo,
        int $fetched, int $new, int $duplicates,
        ?int $importSessionId, bool $success, ?string $error
    ): void {
        $this->db->insert('fints_sync_log', [
            'instances_id'           => $instanceId,
            'bank_accounts_id'       => $accountId,
            'user_id'                => $userId,
            'action'                 => $action,
            'date_from'              => $dateFrom,
            'date_to'                => $dateTo,
            'transactions_fetched'   => $fetched,
            'transactions_new'       => $new,
            'transactions_duplicate' => $duplicates,
            'bank_import_sessions_id' => $importSessionId,
            'success'                => $success ? 1 : 0,
            'error_message'          => $error,
        ]);
    }

    // ═══════════════════════════════════════════════
    //  BANKENLISTE (haeufigste deutsche Banken)
    // ═══════════════════════════════════════════════

    const COMMON_BANKS = [
        ['blz' => '10010010', 'name' => 'Postbank', 'url' => 'https://hbci.postbank.de/banking/hbci.do'],
        ['blz' => '10050000', 'name' => 'Berliner Sparkasse', 'url' => 'https://banking-be1.s-fints-pt-be.de/fints30'],
        ['blz' => '10070000', 'name' => 'Deutsche Bank Berlin', 'url' => 'https://fints.deutsche-bank.de/'],
        ['blz' => '10070024', 'name' => 'Deutsche Bank Privat- und Geschaeftskunden', 'url' => 'https://fints.deutsche-bank.de/'],
        ['blz' => '10090000', 'name' => 'Berliner Volksbank', 'url' => 'https://fints.bvb.de/fints30'],
        ['blz' => '20050550', 'name' => 'Hamburger Sparkasse (Haspa)', 'url' => 'https://banking-hb1.s-fints-pt-hb.de/fints30'],
        ['blz' => '20070000', 'name' => 'Deutsche Bank Hamburg', 'url' => 'https://fints.deutsche-bank.de/'],
        ['blz' => '25050000', 'name' => 'Nord/LB Hannover', 'url' => 'https://fints.nordlb.de/fints'],
        ['blz' => '30050000', 'name' => 'Stadtsparkasse Duesseldorf', 'url' => 'https://banking-rl1.s-fints-pt-rl.de/fints30'],
        ['blz' => '37010050', 'name' => 'Postbank Koeln', 'url' => 'https://hbci.postbank.de/banking/hbci.do'],
        ['blz' => '37050198', 'name' => 'Sparkasse KoelnBonn', 'url' => 'https://banking-rl1.s-fints-pt-rl.de/fints30'],
        ['blz' => '43060967', 'name' => 'GLS Gemeinschaftsbank', 'url' => 'https://fints.gls.de/fints/'],
        ['blz' => '50010517', 'name' => 'ING-DiBa', 'url' => 'https://fints.ing-diba.de/fints/'],
        ['blz' => '50050201', 'name' => 'Frankfurter Sparkasse', 'url' => 'https://banking-he1.s-fints-pt-he.de/fints30'],
        ['blz' => '50070010', 'name' => 'Deutsche Bank Frankfurt', 'url' => 'https://fints.deutsche-bank.de/'],
        ['blz' => '50070024', 'name' => 'Deutsche Bank PGK Frankfurt', 'url' => 'https://fints.deutsche-bank.de/'],
        ['blz' => '50090500', 'name' => 'Sparda-Bank Hessen', 'url' => 'https://banking.sparda-hessen.de/fints30'],
        ['blz' => '50310400', 'name' => 'Triodos Bank Deutschland', 'url' => 'https://fints.triodos.de/fints/'],
        ['blz' => '60050101', 'name' => 'Landesbank Baden-Wuerttemberg', 'url' => 'https://banking-bw1.s-fints-pt-bw.de/fints30'],
        ['blz' => '70020270', 'name' => 'HypoVereinsbank Muenchen', 'url' => 'https://hbci-01.hypovereinsbank.de/bank/hbci'],
        ['blz' => '70050000', 'name' => 'Bayerische Landesbank', 'url' => 'https://banking-by1.s-fints-pt-by.de/fints30'],
        ['blz' => '76030080', 'name' => 'Comdirect', 'url' => 'https://fints.comdirect.de/fints'],
        ['blz' => '50060400', 'name' => 'DKB Deutsche Kreditbank', 'url' => 'https://banking-dkb.s-fints-pt-dkb.de/fints30'],
        ['blz' => '12030000', 'name' => 'Deutsche Kreditbank Berlin', 'url' => 'https://banking-dkb.s-fints-pt-dkb.de/fints30'],
        ['blz' => '86050200', 'name' => 'Sparkasse Leipzig', 'url' => 'https://banking-sn1.s-fints-pt-sn.de/fints30'],
        ['blz' => '68050101', 'name' => 'Sparkasse Freiburg', 'url' => 'https://banking-bw2.s-fints-pt-bw.de/fints30'],
        ['blz' => '55050000', 'name' => 'Sparkasse Mainz', 'url' => 'https://banking-rp1.s-fints-pt-rp.de/fints30'],
    ];
}
