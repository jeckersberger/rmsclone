<?php
/**
 * ImapMailService - IMAP E-Mail Abruf und Verarbeitung
 *
 * Ruft E-Mails ueber IMAP ab und speichert sie in der Datenbank.
 * Konfiguration erfolgt pro Instance ueber die Config-Tabelle.
 * Unterstuetzt jeden IMAP-kompatiblen Provider (Hetzner, Gmail, etc.).
 */
class ImapMailService
{
    private $db;
    private $instanceId;
    private $connection = null;

    public function __construct($db, int $instanceId)
    {
        $this->db = $db;
        $this->instanceId = $instanceId;
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * IMAP-Verbindung herstellen
     */
    public function connect(string $server, int $port, string $encryption, string $username, string $password, string $folder = 'INBOX'): bool
    {
        $flags = '/imap';
        if ($encryption === 'SSL') {
            $flags .= '/ssl';
        } elseif ($encryption === 'TLS') {
            $flags .= '/tls';
        } else {
            $flags .= '/notls';
        }
        $flags .= '/novalidate-cert';

        $mailbox = '{' . $server . ':' . $port . $flags . '}' . $folder;

        $this->connection = @imap_open($mailbox, $username, $password, 0, 1);

        if (!$this->connection) {
            $error = imap_last_error();
            trigger_error('IMAP connect failed: ' . ($error ?: 'Unknown error'), E_USER_WARNING);
            return false;
        }

        return true;
    }

    /**
     * Verbindung trennen
     */
    public function disconnect(): void
    {
        if ($this->connection) {
            imap_close($this->connection);
            $this->connection = null;
        }
    }

    /**
     * Neue E-Mails abrufen (seit letztem Abruf)
     *
     * @param int $limit Max Anzahl E-Mails pro Abruf
     * @return array ['fetched' => int, 'errors' => int, 'messages' => string[]]
     */
    public function fetchNewEmails(int $limit = 50): array
    {
        if (!$this->connection) {
            return ['fetched' => 0, 'errors' => 1, 'messages' => ['Keine IMAP-Verbindung']];
        }

        $result = ['fetched' => 0, 'errors' => 0, 'messages' => []];

        // Letzten Abruf-Zeitpunkt ermitteln
        $this->db->where('instances_id', $this->instanceId);
        $this->db->orderBy('emailReceived_fetchedAt', 'DESC');
        $lastEmail = $this->db->getOne('emailReceived', ['emailReceived_date']);

        if ($lastEmail) {
            $since = date('d-M-Y', strtotime($lastEmail['emailReceived_date'] . ' -1 day'));
            $searchCriteria = 'SINCE "' . $since . '"';
        } else {
            // Erster Abruf: nur E-Mails der letzten 30 Tage
            $since = date('d-M-Y', strtotime('-30 days'));
            $searchCriteria = 'SINCE "' . $since . '"';
        }

        $emails = @imap_search($this->connection, $searchCriteria, SE_UID);

        if ($emails === false) {
            $result['messages'][] = 'Keine neuen E-Mails gefunden oder Suche fehlgeschlagen.';
            return $result;
        }

        // Nur die neuesten $limit E-Mails
        $emails = array_slice(array_reverse($emails), 0, $limit);

        foreach ($emails as $uid) {
            try {
                $saved = $this->processEmail($uid);
                if ($saved === true) {
                    $result['fetched']++;
                } elseif ($saved === null) {
                    // Bereits vorhanden, uebersprungen
                }
            } catch (\Exception $e) {
                $result['errors']++;
                $result['messages'][] = 'Fehler bei UID ' . $uid . ': ' . $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * Einzelne E-Mail verarbeiten und speichern
     *
     * @return bool|null true=gespeichert, null=bereits vorhanden
     */
    private function processEmail(int $uid): ?bool
    {
        $headerInfo = imap_headerinfo($this->connection, imap_msgno($this->connection, $uid));
        if (!$headerInfo) {
            throw new \Exception('Header nicht lesbar');
        }

        $messageId = isset($headerInfo->message_id) ? trim($headerInfo->message_id) : '';

        // Duplikat-Check
        if (!empty($messageId)) {
            $this->db->where('emailReceived_messageId', $messageId);
            $this->db->where('instances_id', $this->instanceId);
            $existing = $this->db->getOne('emailReceived', ['emailReceived_id']);
            if ($existing) {
                return null;
            }
        } else {
            $messageId = 'gen-' . $this->instanceId . '-' . $uid . '-' . time();
        }

        // Absender
        $fromEmail = '';
        $fromName = '';
        if (isset($headerInfo->from[0])) {
            $from = $headerInfo->from[0];
            $fromEmail = isset($from->mailbox, $from->host) ? $from->mailbox . '@' . $from->host : '';
            $fromName = isset($from->personal) ? $this->decodeMimeStr($from->personal) : '';
        }

        // Empfaenger
        $toEmail = '';
        if (isset($headerInfo->to[0])) {
            $to = $headerInfo->to[0];
            $toEmail = isset($to->mailbox, $to->host) ? $to->mailbox . '@' . $to->host : '';
        }

        // Betreff
        $subject = isset($headerInfo->subject) ? $this->decodeMimeStr($headerInfo->subject) : '';

        // Datum
        $date = isset($headerInfo->date) ? date('Y-m-d H:i:s', strtotime($headerInfo->date)) : date('Y-m-d H:i:s');

        // Body
        $body = $this->getBody($uid);

        // Anhaenge pruefen
        $structure = imap_fetchstructure($this->connection, $uid, FT_UID);
        $hasAttachments = $this->hasAttachments($structure);

        // Kunde automatisch zuordnen (anhand Absender-E-Mail)
        $clientId = null;
        if (!empty($fromEmail)) {
            $this->db->where('instances_id', $this->instanceId);
            $this->db->where('clients_email', $fromEmail);
            $client = $this->db->getOne('clients', ['clients_id']);
            if ($client) {
                $clientId = (int)$client['clients_id'];
            }
        }

        // In DB speichern
        $emailId = $this->db->insert('emailReceived', [
            'instances_id' => $this->instanceId,
            'emailReceived_messageId' => $messageId,
            'emailReceived_fromEmail' => $fromEmail,
            'emailReceived_fromName' => $fromName,
            'emailReceived_toEmail' => $toEmail,
            'emailReceived_subject' => mb_substr($subject, 0, 1000),
            'emailReceived_bodyText' => $body['text'],
            'emailReceived_bodyHtml' => $body['html'],
            'emailReceived_date' => $date,
            'emailReceived_fetchedAt' => date('Y-m-d H:i:s'),
            'emailReceived_isRead' => 0,
            'emailReceived_isProcessed' => 0,
            'emailReceived_folder' => 'INBOX',
            'emailReceived_hasAttachments' => $hasAttachments ? 1 : 0,
            'clients_id' => $clientId,
        ]);

        if (!$emailId) {
            throw new \Exception('DB insert fehlgeschlagen');
        }

        // Anhaenge speichern
        if ($hasAttachments) {
            $this->saveAttachments($uid, (int)$emailId, $structure);
        }

        return true;
    }

    /**
     * E-Mail Body extrahieren (Text und HTML)
     */
    private function getBody(int $uid): array
    {
        $result = ['text' => null, 'html' => null];
        $structure = imap_fetchstructure($this->connection, $uid, FT_UID);

        if (!$structure) return $result;

        if (empty($structure->parts)) {
            // Einfache E-Mail (kein Multipart)
            $body = imap_fetchbody($this->connection, $uid, '1', FT_UID);
            $body = $this->decodeBody($body, $structure->encoding ?? 0);
            $charset = $this->getCharset($structure);
            $body = $this->convertEncoding($body, $charset);

            if ($structure->subtype === 'HTML') {
                $result['html'] = $body;
            } else {
                $result['text'] = $body;
            }
        } else {
            // Multipart
            foreach ($structure->parts as $partNum => $part) {
                $this->extractBody($uid, $part, (string)($partNum + 1), $result);
            }
        }

        return $result;
    }

    /**
     * Rekursiv Body-Teile extrahieren
     */
    private function extractBody(int $uid, $part, string $partNumber, array &$result): void
    {
        if (isset($part->parts)) {
            foreach ($part->parts as $subNum => $subPart) {
                $this->extractBody($uid, $subPart, $partNumber . '.' . ($subNum + 1), $result);
            }
            return;
        }

        // Nur Text/HTML extrahieren, keine Anhaenge
        if (isset($part->disposition) && strtoupper($part->disposition) === 'ATTACHMENT') {
            return;
        }

        if ($part->type !== 0) return; // Nur type=0 (text)

        $body = imap_fetchbody($this->connection, $uid, $partNumber, FT_UID);
        $body = $this->decodeBody($body, $part->encoding ?? 0);
        $charset = $this->getCharset($part);
        $body = $this->convertEncoding($body, $charset);

        if (strtoupper($part->subtype) === 'HTML') {
            $result['html'] = $body;
        } elseif (strtoupper($part->subtype) === 'PLAIN') {
            $result['text'] = $body;
        }
    }

    /**
     * Pruefen ob E-Mail Anhaenge hat
     */
    private function hasAttachments($structure): bool
    {
        if (empty($structure->parts)) return false;

        foreach ($structure->parts as $part) {
            if (isset($part->disposition) && strtoupper($part->disposition) === 'ATTACHMENT') {
                return true;
            }
            if (isset($part->parts)) {
                foreach ($part->parts as $subPart) {
                    if (isset($subPart->disposition) && strtoupper($subPart->disposition) === 'ATTACHMENT') {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Anhaenge speichern
     */
    private function saveAttachments(int $uid, int $emailId, $structure): void
    {
        global $CONFIGCLASS;

        $storagePath = $CONFIGCLASS->get('LOCAL_STORAGE_PATH');
        $attachDir = $storagePath . '/email_attachments/' . $this->instanceId;

        if (!is_dir($attachDir)) {
            mkdir($attachDir, 0755, true);
        }

        if (empty($structure->parts)) return;

        foreach ($structure->parts as $partNum => $part) {
            $this->processAttachmentPart($uid, $emailId, $part, (string)($partNum + 1), $attachDir);
        }
    }

    /**
     * Einzelnen Anhang-Part verarbeiten
     */
    private function processAttachmentPart(int $uid, int $emailId, $part, string $partNumber, string $attachDir): void
    {
        // Rekursiv in Sub-Parts suchen
        if (isset($part->parts)) {
            foreach ($part->parts as $subNum => $subPart) {
                $this->processAttachmentPart($uid, $emailId, $subPart, $partNumber . '.' . ($subNum + 1), $attachDir);
            }
        }

        if (!isset($part->disposition) || strtoupper($part->disposition) !== 'ATTACHMENT') {
            return;
        }

        // Dateiname ermitteln
        $filename = 'unknown';
        if (isset($part->dparameters)) {
            foreach ($part->dparameters as $param) {
                if (strtoupper($param->attribute) === 'FILENAME') {
                    $filename = $this->decodeMimeStr($param->value);
                    break;
                }
            }
        }
        if ($filename === 'unknown' && isset($part->parameters)) {
            foreach ($part->parameters as $param) {
                if (strtoupper($param->attribute) === 'NAME') {
                    $filename = $this->decodeMimeStr($param->value);
                    break;
                }
            }
        }

        // Dateiname sanitizen
        $filename = preg_replace('/[^a-zA-Z0-9._\-\x{00C0}-\x{024F}]/u', '_', $filename);

        // Datei herunterladen
        $data = imap_fetchbody($this->connection, $uid, $partNumber, FT_UID);
        $data = $this->decodeBody($data, $part->encoding ?? 0);

        if (empty($data)) return;

        // Eindeutigen Dateinamen generieren
        $uniqueName = date('Ymd_His') . '_' . $emailId . '_' . $filename;
        $filePath = $attachDir . '/' . $uniqueName;

        if (file_put_contents($filePath, $data) === false) {
            trigger_error('Anhang konnte nicht gespeichert werden: ' . $filePath, E_USER_WARNING);
            return;
        }

        // MIME-Type
        $mimeTypes = [0 => 'text', 1 => 'multipart', 2 => 'message', 3 => 'application', 4 => 'audio', 5 => 'image', 6 => 'video', 7 => 'other'];
        $mimeType = ($mimeTypes[$part->type] ?? 'application') . '/' . strtolower($part->subtype ?? 'octet-stream');

        $this->db->insert('emailAttachments', [
            'emailReceived_id' => $emailId,
            'instances_id' => $this->instanceId,
            'emailAttachment_filename' => mb_substr($filename, 0, 500),
            'emailAttachment_mimeType' => $mimeType,
            'emailAttachment_size' => strlen($data),
            'emailAttachment_storagePath' => $filePath,
            'emailAttachment_savedAt' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Body dekodieren (Base64, Quoted-Printable, etc.)
     */
    private function decodeBody(string $body, int $encoding): string
    {
        switch ($encoding) {
            case 0: // 7BIT
            case 1: // 8BIT
                return $body;
            case 2: // BINARY
                return $body;
            case 3: // BASE64
                return base64_decode($body) ?: '';
            case 4: // QUOTED-PRINTABLE
                return quoted_printable_decode($body);
            case 5: // OTHER
            default:
                return $body;
        }
    }

    /**
     * Charset aus Part-Parametern lesen
     */
    private function getCharset($part): string
    {
        if (isset($part->parameters)) {
            foreach ($part->parameters as $param) {
                if (strtoupper($param->attribute) === 'CHARSET') {
                    return strtoupper($param->value);
                }
            }
        }
        return 'UTF-8';
    }

    /**
     * Zeichensatz nach UTF-8 konvertieren
     */
    private function convertEncoding(string $str, string $fromCharset): string
    {
        if ($fromCharset === 'UTF-8' || $fromCharset === 'UTF8') {
            return $str;
        }
        $converted = @mb_convert_encoding($str, 'UTF-8', $fromCharset);
        return $converted !== false ? $converted : $str;
    }

    /**
     * MIME-encodierten String dekodieren
     */
    private function decodeMimeStr(string $str): string
    {
        $elements = imap_mime_header_decode($str);
        $decoded = '';
        foreach ($elements as $element) {
            $charset = $element->charset;
            $text = $element->text;
            if ($charset !== 'default' && $charset !== 'UTF-8') {
                $text = @mb_convert_encoding($text, 'UTF-8', $charset) ?: $text;
            }
            $decoded .= $text;
        }
        return $decoded;
    }

    /**
     * Verbindung testen (fuer UI-Feedback)
     */
    public function testConnection(string $server, int $port, string $encryption, string $username, string $password): array
    {
        $connected = $this->connect($server, $port, $encryption, $username, $password);
        if (!$connected) {
            return ['success' => false, 'message' => 'Verbindung fehlgeschlagen: ' . (imap_last_error() ?: 'Unbekannter Fehler')];
        }

        $check = imap_check($this->connection);
        $info = [
            'success' => true,
            'message' => 'Verbindung erfolgreich',
            'mailbox' => $check->Mailbox ?? '',
            'totalMessages' => $check->Nmsgs ?? 0,
            'recentMessages' => $check->Recent ?? 0,
        ];

        $this->disconnect();
        return $info;
    }

    /**
     * E-Mail einem Projekt zuordnen
     */
    public function assignToProject(int $emailId, int $projectId, int $userId): bool
    {
        $this->db->where('emailReceived_id', $emailId);
        $this->db->where('instances_id', $this->instanceId);
        $email = $this->db->getOne('emailReceived', null, ['emailReceived_id']);

        if (!$email) {
            return false;
        }

        $this->db->where('emailReceived_id', $emailId);
        $this->db->update('emailReceived', [
            'projects_id' => $projectId,
            'assigned_by' => $userId,
            'assigned_at' => date('Y-m-d H:i:s')
        ]);

        return $this->db->affectedRows() > 0;
    }

    /**
     * Projektzuordnung entfernen
     */
    public function unassignFromProject(int $emailId): bool
    {
        $this->db->where('emailReceived_id', $emailId);
        $this->db->where('instances_id', $this->instanceId);
        $this->db->update('emailReceived', [
            'projects_id' => null,
            'assigned_by' => null,
            'assigned_at' => null
        ]);

        return $this->db->affectedRows() > 0;
    }

    /**
     * Alle E-Mails eines Projekts laden
     */
    public function getProjectEmails(int $projectId, int $limit = 50, int $offset = 0): array
    {
        $this->db->where('projects_id', $projectId);
        $this->db->where('instances_id', $this->instanceId);
        $this->db->orderBy('emailReceived_date', 'DESC');

        $emails = $this->db->get('emailReceived', [$offset, $limit], [
            'emailReceived_id', 'emailReceived_fromEmail', 'emailReceived_fromName', 'emailReceived_toEmail',
            'emailReceived_subject', 'emailReceived_date', 'emailReceived_isRead', 'emailReceived_hasAttachments',
            'assigned_by', 'assigned_at', 'clients_id'
        ]) ?: [];

        // Gesamtanzahl ermitteln
        $this->db->where('projects_id', $projectId);
        $this->db->where('instances_id', $this->instanceId);
        $total = $this->db->getValue('emailReceived', 'count(*)');

        return [
            'emails' => $emails,
            'total' => (int)$total
        ];
    }

    /**
     * E-Mails automatisch einem Projekt zuordnen basierend auf Client-E-Mail
     */
    public function autoAssignByClient(int $projectId): int
    {
        // Lade den Client des Projekts
        $this->db->join('clients c', 'p.clients_id=c.clients_id', 'LEFT');
        $this->db->where('p.projects_id', $projectId);
        $this->db->where('p.instances_id', $this->instanceId);
        $project = $this->db->getOne('projects p', null, ['c.clients_email', 'c.clients_id']);

        if (!$project || empty($project['clients_email'])) {
            return 0;
        }

        // Finde unzugeordnete E-Mails von/an diesen Client
        $clientEmail = $project['clients_email'];

        $emails = $this->db->rawQuery(
            "SELECT emailReceived_id FROM emailReceived
             WHERE instances_id = ?
             AND projects_id IS NULL
             AND (emailReceived_fromEmail = ? OR emailReceived_toEmail = ?)",
            [$this->instanceId, $clientEmail, $clientEmail]
        ) ?: [];

        $count = 0;
        foreach ($emails as $email) {
            $this->db->where('emailReceived_id', $email['emailReceived_id']);
            $this->db->update('emailReceived', [
                'projects_id' => $projectId,
                'assigned_at' => date('Y-m-d H:i:s')
            ]);
            $count++;
        }

        return $count;
    }
}
