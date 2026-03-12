<?php
/**
 * SEPA-Lastschrift-Mandatsverwaltung
 *
 * Verwaltung von SEPA-Mandaten und Erzeugung von SEPA-XML-Dateien (pain.008.001.02)
 * fuer den Bankeinzug offener Rechnungen.
 */
class SepaService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Neues SEPA-Mandat anlegen
     *
     * @param int   $clientId    Kunden-ID
     * @param int   $instanceId  Instanz-ID
     * @param array $data        Mandatsdaten (iban, bic, account_holder, mandate_type, signed_at)
     * @return array|null        Angelegtes Mandat oder null bei Fehler
     */
    public function createMandate(int $clientId, int $instanceId, array $data): ?array
    {
        $reference = $this->generateMandateReference($instanceId);
        if (!$reference) return null;

        $iban = strtoupper(preg_replace('/\s+/', '', $data['iban'] ?? ''));
        if (!$this->validateIban($iban)) return null;

        $bic = !empty($data['bic']) ? strtoupper(preg_replace('/\s+/', '', $data['bic'])) : null;
        $accountHolder = trim($data['account_holder'] ?? '');
        if (empty($accountHolder)) return null;

        $mandateType = in_array($data['mandate_type'] ?? '', ['CORE', 'B2B']) ? $data['mandate_type'] : 'CORE';
        $signedAt = !empty($data['signed_at']) ? $data['signed_at'] : date('Y-m-d H:i:s');

        $this->db->insert('sepa_mandates', [
            'clients_id'       => $clientId,
            'instances_id'     => $instanceId,
            'mandate_reference'=> $reference,
            'mandate_date'     => date('Y-m-d'),
            'iban'             => $iban,
            'bic'              => $bic,
            'account_holder'   => $accountHolder,
            'mandate_type'     => $mandateType,
            'status'           => 'active',
            'signed_at'        => $signedAt,
        ]);

        $mandateId = $this->db->getInsertId();
        if (!$mandateId) return null;

        // clients_sepaMandate Flag setzen
        $this->db->where('clients_id', $clientId);
        $this->db->update('clients', ['clients_sepaMandate' => 1]);

        $this->db->where('id', $mandateId);
        return $this->db->getOne('sepa_mandates');
    }

    /**
     * Mandat widerrufen
     *
     * @param int $mandateId   Mandat-ID
     * @param int $instanceId  Instanz-ID (Sicherheitspruefung)
     * @return bool
     */
    public function revokeMandate(int $mandateId, int $instanceId): bool
    {
        $this->db->where('id', $mandateId);
        $this->db->where('instances_id', $instanceId);
        $this->db->where('status', 'active');
        $mandate = $this->db->getOne('sepa_mandates');
        if (!$mandate) return false;

        $this->db->where('id', $mandateId);
        $result = $this->db->update('sepa_mandates', [
            'status'     => 'revoked',
            'revoked_at' => date('Y-m-d H:i:s'),
        ]);

        if ($result) {
            // Pruefen ob Kunde noch aktive Mandate hat
            $this->db->where('clients_id', $mandate['clients_id']);
            $this->db->where('instances_id', $instanceId);
            $this->db->where('status', 'active');
            $activeCount = $this->db->getValue('sepa_mandates', 'count(*)');
            if ((int)$activeCount === 0) {
                $this->db->where('clients_id', $mandate['clients_id']);
                $this->db->update('clients', ['clients_sepaMandate' => 0]);
            }
        }

        return $result;
    }

    /**
     * Mandate eines Kunden abrufen
     *
     * @param int      $clientId    Kunden-ID
     * @param int|null $instanceId  Instanz-ID (optional)
     * @return array
     */
    public function getMandatesByClient(int $clientId, ?int $instanceId = null): array
    {
        $this->db->where('sm.clients_id', $clientId);
        if ($instanceId) {
            $this->db->where('sm.instances_id', $instanceId);
        }
        $this->db->join('clients c', 'sm.clients_id=c.clients_id', 'LEFT');
        $this->db->orderBy('sm.created_at', 'DESC');
        return $this->db->get('sepa_mandates sm', null, [
            'sm.*', 'c.clients_name'
        ]) ?: [];
    }

    /**
     * Alle Mandate einer Instanz abrufen
     *
     * @param int    $instanceId  Instanz-ID
     * @param string|null $status Optionaler Statusfilter
     * @return array
     */
    public function getMandatesByInstance(int $instanceId, ?string $status = null): array
    {
        $this->db->where('sm.instances_id', $instanceId);
        if ($status) {
            $this->db->where('sm.status', $status);
        }
        $this->db->join('clients c', 'sm.clients_id=c.clients_id', 'LEFT');
        $this->db->orderBy('sm.created_at', 'DESC');
        return $this->db->get('sepa_mandates sm', null, [
            'sm.*', 'c.clients_name'
        ]) ?: [];
    }

    /**
     * Einzelnes Mandat abrufen
     */
    public function getMandate(int $mandateId, int $instanceId): ?array
    {
        $this->db->where('id', $mandateId);
        $this->db->where('instances_id', $instanceId);
        return $this->db->getOne('sepa_mandates') ?: null;
    }

    /**
     * SEPA-XML (pain.008.001.02) fuer Sammeleinzug erzeugen
     *
     * @param array $invoiceIds  Array von Rechnungs-IDs (document_lifecycle)
     * @param int   $instanceId  Instanz-ID
     * @return string|null       XML-String oder null bei Fehler
     */
    public function generateSepaXml(array $invoiceIds, int $instanceId): ?string
    {
        if (empty($invoiceIds)) return null;

        // Instanzdaten laden (Glaeubiger)
        $this->db->where('instances_id', $instanceId);
        $instance = $this->db->getOne('instances');
        if (!$instance) return null;

        // Rechnungen mit Mandaten laden
        $placeholders = implode(',', array_fill(0, count($invoiceIds), '?'));
        $sql = "SELECT dl.*, p.clients_id, c.clients_name,
                       sm.id as mandate_id, sm.mandate_reference, sm.iban, sm.bic,
                       sm.account_holder, sm.mandate_type, sm.mandate_date, sm.status as mandate_status
                FROM document_lifecycle dl
                JOIN projects p ON dl.projects_id = p.projects_id
                JOIN clients c ON p.clients_id = c.clients_id
                LEFT JOIN sepa_mandates sm ON c.clients_id = sm.clients_id
                    AND sm.instances_id = ? AND sm.status = 'active'
                WHERE dl.id IN ($placeholders)
                AND dl.instances_id = ?";
        $params = array_merge([$instanceId], $invoiceIds, [$instanceId]);
        $invoices = $this->db->rawQuery($sql, $params) ?: [];

        if (empty($invoices)) return null;

        // Nur Rechnungen mit aktivem Mandat
        $validInvoices = array_filter($invoices, function($inv) {
            return !empty($inv['mandate_id']) && $inv['mandate_status'] === 'active';
        });
        if (empty($validInvoices)) return null;

        return $this->buildPain008Xml($validInvoices, $instance);
    }

    /**
     * SEPA-XML fuer Einzeleinzug erzeugen
     *
     * @param int    $mandateId  Mandat-ID
     * @param float  $amount     Einzugsbetrag
     * @param string $purpose    Verwendungszweck
     * @param int    $instanceId Instanz-ID
     * @return string|null       XML-String oder null bei Fehler
     */
    public function exportSepaXml(int $mandateId, float $amount, string $purpose, int $instanceId): ?string
    {
        if ($amount <= 0) return null;

        $this->db->where('instances_id', $instanceId);
        $instance = $this->db->getOne('instances');
        if (!$instance) return null;

        $this->db->where('sm.id', $mandateId);
        $this->db->where('sm.instances_id', $instanceId);
        $this->db->where('sm.status', 'active');
        $this->db->join('clients c', 'sm.clients_id=c.clients_id', 'LEFT');
        $mandate = $this->db->getOne('sepa_mandates sm', ['sm.*', 'c.clients_name']);
        if (!$mandate) return null;

        $singleInvoice = [[
            'gross_amount'      => $amount,
            'doc_number'        => $purpose,
            'mandate_reference' => $mandate['mandate_reference'],
            'iban'              => $mandate['iban'],
            'bic'               => $mandate['bic'],
            'account_holder'    => $mandate['account_holder'],
            'mandate_type'      => $mandate['mandate_type'],
            'mandate_date'      => $mandate['mandate_date'],
            'clients_name'      => $mandate['clients_name'],
        ]];

        return $this->buildPain008Xml($singleInvoice, $instance);
    }

    /**
     * pain.008.001.02 XML erzeugen
     */
    private function buildPain008Xml(array $transactions, array $instance): string
    {
        $msgId = 'MSG-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4));
        $creationDateTime = date('Y-m-d\TH:i:s');
        $requestedCollectionDate = date('Y-m-d', strtotime('+5 days'));
        $numberOfTransactions = count($transactions);
        $controlSum = 0;
        foreach ($transactions as $tx) {
            $controlSum += (float)($tx['gross_amount'] ?? 0);
        }
        $controlSum = number_format($controlSum, 2, '.', '');

        $creditorName = htmlspecialchars($instance['instances_name'] ?? 'Unbekannt', ENT_XML1);
        $creditorIban = $instance['instances_iban'] ?? '';
        $creditorBic  = $instance['instances_bic'] ?? '';
        $creditorId   = $instance['instances_creditorId'] ?? $instance['instances_sepaCreditorId'] ?? '';

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pain.008.001.02" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">' . "\n";
        $xml .= '  <CstmrDrctDbtInitn>' . "\n";

        // Group Header
        $xml .= '    <GrpHdr>' . "\n";
        $xml .= '      <MsgId>' . htmlspecialchars($msgId, ENT_XML1) . '</MsgId>' . "\n";
        $xml .= '      <CreDtTm>' . $creationDateTime . '</CreDtTm>' . "\n";
        $xml .= '      <NbOfTxs>' . $numberOfTransactions . '</NbOfTxs>' . "\n";
        $xml .= '      <CtrlSum>' . $controlSum . '</CtrlSum>' . "\n";
        $xml .= '      <InitgPty>' . "\n";
        $xml .= '        <Nm>' . $creditorName . '</Nm>' . "\n";
        $xml .= '      </InitgPty>' . "\n";
        $xml .= '    </GrpHdr>' . "\n";

        // Payment Information
        $xml .= '    <PmtInf>' . "\n";
        $xml .= '      <PmtInfId>PMT-' . date('Ymd') . '-' . bin2hex(random_bytes(2)) . '</PmtInfId>' . "\n";
        $xml .= '      <PmtMtd>DD</PmtMtd>' . "\n";
        $xml .= '      <BtchBookg>true</BtchBookg>' . "\n";
        $xml .= '      <NbOfTxs>' . $numberOfTransactions . '</NbOfTxs>' . "\n";
        $xml .= '      <CtrlSum>' . $controlSum . '</CtrlSum>' . "\n";
        $xml .= '      <PmtTpInf>' . "\n";
        $xml .= '        <SvcLvl><Cd>SEPA</Cd></SvcLvl>' . "\n";
        $xml .= '        <LclInstrm><Cd>CORE</Cd></LclInstrm>' . "\n";
        $xml .= '        <SeqTp>RCUR</SeqTp>' . "\n";
        $xml .= '      </PmtTpInf>' . "\n";
        $xml .= '      <ReqdColltnDt>' . $requestedCollectionDate . '</ReqdColltnDt>' . "\n";
        $xml .= '      <Cdtr>' . "\n";
        $xml .= '        <Nm>' . $creditorName . '</Nm>' . "\n";
        $xml .= '      </Cdtr>' . "\n";
        $xml .= '      <CdtrAcct>' . "\n";
        $xml .= '        <Id><IBAN>' . htmlspecialchars($creditorIban, ENT_XML1) . '</IBAN></Id>' . "\n";
        $xml .= '      </CdtrAcct>' . "\n";
        $xml .= '      <CdtrAgt>' . "\n";
        $xml .= '        <FinInstnId><BIC>' . htmlspecialchars($creditorBic, ENT_XML1) . '</BIC></FinInstnId>' . "\n";
        $xml .= '      </CdtrAgt>' . "\n";
        $xml .= '      <CdtrSchmeId>' . "\n";
        $xml .= '        <Id><PrvtId><Othr>' . "\n";
        $xml .= '          <Id>' . htmlspecialchars($creditorId, ENT_XML1) . '</Id>' . "\n";
        $xml .= '          <SchmeNm><Prtry>SEPA</Prtry></SchmeNm>' . "\n";
        $xml .= '        </Othr></PrvtId></Id>' . "\n";
        $xml .= '      </CdtrSchmeId>' . "\n";

        // Individual Transactions
        foreach ($transactions as $tx) {
            $amount = number_format((float)($tx['gross_amount'] ?? 0), 2, '.', '');
            $endToEndId = 'E2E-' . date('Ymd') . '-' . bin2hex(random_bytes(4));
            $mandateRef = htmlspecialchars($tx['mandate_reference'] ?? '', ENT_XML1);
            $mandateDate = $tx['mandate_date'] ?? date('Y-m-d');
            $debtorName = htmlspecialchars($tx['account_holder'] ?? $tx['clients_name'] ?? '', ENT_XML1);
            $debtorIban = $tx['iban'] ?? '';
            $debtorBic  = $tx['bic'] ?? '';
            $purpose    = htmlspecialchars($tx['doc_number'] ?? '', ENT_XML1);

            $xml .= '      <DrctDbtTxInf>' . "\n";
            $xml .= '        <PmtId><EndToEndId>' . $endToEndId . '</EndToEndId></PmtId>' . "\n";
            $xml .= '        <InstdAmt Ccy="EUR">' . $amount . '</InstdAmt>' . "\n";
            $xml .= '        <DrctDbtTx>' . "\n";
            $xml .= '          <MndtRltdInf>' . "\n";
            $xml .= '            <MndtId>' . $mandateRef . '</MndtId>' . "\n";
            $xml .= '            <DtOfSgntr>' . $mandateDate . '</DtOfSgntr>' . "\n";
            $xml .= '          </MndtRltdInf>' . "\n";
            $xml .= '        </DrctDbtTx>' . "\n";
            $xml .= '        <DbtrAgt>' . "\n";
            $xml .= '          <FinInstnId>' . ($debtorBic ? '<BIC>' . htmlspecialchars($debtorBic, ENT_XML1) . '</BIC>' : '<Othr><Id>NOTPROVIDED</Id></Othr>') . '</FinInstnId>' . "\n";
            $xml .= '        </DbtrAgt>' . "\n";
            $xml .= '        <Dbtr><Nm>' . $debtorName . '</Nm></Dbtr>' . "\n";
            $xml .= '        <DbtrAcct><Id><IBAN>' . htmlspecialchars($debtorIban, ENT_XML1) . '</IBAN></Id></DbtrAcct>' . "\n";
            $xml .= '        <RmtInf><Ustrd>' . $purpose . '</Ustrd></RmtInf>' . "\n";
            $xml .= '      </DrctDbtTxInf>' . "\n";
        }

        $xml .= '    </PmtInf>' . "\n";
        $xml .= '  </CstmrDrctDbtInitn>' . "\n";
        $xml .= '</Document>' . "\n";

        return $xml;
    }

    /**
     * Eindeutige Mandatsreferenz erzeugen (Format: MNDT-YYYY-NNNN)
     */
    private function generateMandateReference(int $instanceId): string
    {
        $year = date('Y');
        $this->db->where('instances_id', $instanceId);
        $this->db->where('mandate_reference', "MNDT-$year-%", 'LIKE');
        $this->db->orderBy('mandate_reference', 'DESC');
        $last = $this->db->getOne('sepa_mandates', ['mandate_reference']);

        $nextNum = 1;
        if ($last && preg_match('/MNDT-\d{4}-(\d{4})$/', $last['mandate_reference'], $m)) {
            $nextNum = (int)$m[1] + 1;
        }

        return sprintf('MNDT-%s-%04d', $year, $nextNum);
    }

    /**
     * Einfache IBAN-Validierung (Laenge und Format)
     */
    private function validateIban(string $iban): bool
    {
        $iban = strtoupper(preg_replace('/\s+/', '', $iban));
        if (strlen($iban) < 15 || strlen($iban) > 34) return false;
        if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/', $iban)) return false;
        return true;
    }
}
