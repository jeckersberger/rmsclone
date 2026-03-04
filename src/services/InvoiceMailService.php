<?php
/**
 * InvoiceMailService - Automatischer Rechnungsversand per E-Mail
 *
 * Versendet generierte PDF-Rechnungen als E-Mail-Anhang an Kunden.
 * Nutzt das vorhandene E-Mail-System (PHPMailer/SendGrid/Mailgun/Postmark).
 */
class InvoiceMailService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Rechnung per E-Mail versenden
     *
     * @param int    $instanceId  Instance-ID
     * @param int    $projectId   Projekt-ID
     * @param int    $s3fileId    S3-Datei-ID der PDF
     * @param string $docNumber   Rechnungsnummer
     * @param string $docType     Dokumenttyp (invoice, quote, delivery_note)
     * @param string $recipientEmail  E-Mail-Adresse des Empfängers
     * @param string $recipientName   Name des Empfängers
     * @param int    $userId      Absender-User-ID
     * @return array ['success' => bool, 'message' => string]
     */
    public function sendDocument(
        int $instanceId,
        int $projectId,
        int $s3fileId,
        string $docNumber,
        string $docType,
        string $recipientEmail,
        string $recipientName,
        int $userId
    ): array {
        global $CONFIG, $CONFIGCLASS, $TWIG, $bCMS;

        // Prüfe ob E-Mail aktiviert
        if ($CONFIGCLASS->get('EMAILS_ENABLED') !== 'Enabled') {
            return ['success' => false, 'message' => 'E-Mail ist nicht konfiguriert.'];
        }

        if (empty($recipientEmail) || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Keine gültige E-Mail-Adresse.'];
        }

        // Lade Business-Daten
        $this->db->where('instances_id', $instanceId);
        $instance = $this->db->getOne('instances');
        $businessName = $instance['instances_name'] ?? $CONFIG['PROJECT_NAME'];

        // Lade Projekt-Daten
        $this->db->where('projects_id', $projectId);
        $project = $this->db->getOne('projects');
        $projectName = $project['projects_name'] ?? '';

        // Typ-Labels
        $typeLabels = [
            'invoice'        => 'Rechnung',
            'quote'          => 'Angebot',
            'delivery_note'  => 'Lieferschein',
            'order_confirmation' => 'Auftragsbestätigung',
            'credit_note'    => 'Gutschrift',
            'cancellation'   => 'Storno',
        ];
        $typeLabel = $typeLabels[$docType] ?? 'Dokument';

        // E-Mail-Betreff
        $subject = "{$typeLabel} {$docNumber} - {$businessName}";

        // PDF-Datei laden
        $this->db->where('s3files_id', $s3fileId);
        $s3file = $this->db->getOne('s3files');
        if (!$s3file) {
            return ['success' => false, 'message' => 'PDF-Datei nicht gefunden.'];
        }

        // PDF-Daten holen (S3 oder lokal)
        $pdfData = $this->getPdfData($s3file);
        if (!$pdfData) {
            return ['success' => false, 'message' => 'PDF konnte nicht geladen werden.'];
        }

        $pdfFilename = "{$typeLabel}_{$docNumber}.pdf";

        // E-Mail-Body rendern
        $emailHtml = $TWIG->render('api/notifications/email/invoice_email.twig', [
            'business_name' => $businessName,
            'recipient_name' => $recipientName,
            'doc_type' => $typeLabel,
            'doc_number' => $docNumber,
            'project_name' => $projectName,
            'instance' => $instance,
        ]);

        // Sende über konfigurierten Provider
        $sent = $this->sendWithAttachment(
            $recipientEmail,
            $recipientName,
            $subject,
            $emailHtml,
            $pdfData,
            $pdfFilename
        );

        if ($sent) {
            // Log in document_lifecycle
            $this->db->where('instances_id', $instanceId);
            $this->db->where('doc_number', $docNumber);
            $doc = $this->db->getOne('document_lifecycle');
            if ($doc) {
                $this->db->where('id', $doc['id']);
                $this->db->update('document_lifecycle', [
                    'sent_at' => date('Y-m-d H:i:s'),
                    'sent_to_email' => $recipientEmail,
                    'status' => ($doc['status'] === 'draft') ? 'sent' : $doc['status'],
                ]);
            }

            // Log email
            $this->db->insert('emailSent', [
                'users_userid' => $userId,
                'emailSent_html' => $emailHtml,
                'emailSent_subject' => $subject,
                'emailSent_sent' => date('Y-m-d H:i:s'),
                'emailSent_fromEmail' => $CONFIGCLASS->get('EMAILS_FROMEMAIL'),
                'emailSent_fromName' => $businessName,
                'emailSent_toEmail' => $recipientEmail,
                'emailSent_toName' => $recipientName,
            ]);

            return ['success' => true, 'message' => "E-Mail an {$recipientEmail} versendet."];
        }

        return ['success' => false, 'message' => 'E-Mail konnte nicht versendet werden.'];
    }

    /**
     * Auto-Send: Versendet Rechnung automatisch wenn Kunde E-Mail hat
     */
    public function autoSendIfEmailAvailable(int $instanceId, int $projectId, int $s3fileId, string $docNumber, string $docType, int $userId): array
    {
        // Lade Kunden-E-Mail aus Projekt
        $this->db->join('clients c', 'p.clients_id=c.clients_id', 'LEFT');
        $this->db->where('p.projects_id', $projectId);
        $result = $this->db->getOne('projects p', ['c.clients_email', 'c.clients_name']);

        if (!$result || empty($result['clients_email'])) {
            return ['success' => false, 'message' => 'Kunde hat keine E-Mail-Adresse hinterlegt.'];
        }

        return $this->sendDocument(
            $instanceId, $projectId, $s3fileId, $docNumber, $docType,
            $result['clients_email'], $result['clients_name'], $userId
        );
    }

    private function sendWithAttachment(string $to, string $toName, string $subject, string $html, string $pdfData, string $pdfFilename): bool
    {
        global $CONFIG, $CONFIGCLASS;

        $provider = $CONFIGCLASS->get('EMAILS_PROVIDER');

        // PHPMailer (SMTP) kann direkt Anhänge
        // Für alle Provider nutzen wir PHPMailer als Fallback für Anhänge
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->SMTPDebug = \PHPMailer\PHPMailer\SMTP::DEBUG_OFF;
            $mail->CharSet = 'UTF-8';

            if ($provider === 'SMTP') {
                $mail->isSMTP();
                $mail->Host = $CONFIGCLASS->get('EMAILS_SMTP_SERVER');
                $mail->Port = $CONFIGCLASS->get('EMAILS_SMTP_PORT');
                if ($CONFIGCLASS->get('EMAILS_SMTP_USERNAME') && $CONFIGCLASS->get('EMAILS_SMTP_PASSWORD')) {
                    $mail->SMTPAuth = true;
                    $mail->Username = $CONFIGCLASS->get('EMAILS_SMTP_USERNAME');
                    $mail->Password = $CONFIGCLASS->get('EMAILS_SMTP_PASSWORD');
                } else {
                    $mail->SMTPAuth = false;
                }
                $enc = $CONFIGCLASS->get('EMAILS_SMTP_ENCRYPTION');
                if ($enc === 'None') $mail->SMTPSecure = false;
                elseif ($enc === 'TLS') $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                else $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } else {
                // Für SendGrid/Mailgun/Postmark: nutze auch SMTP wenn konfiguriert,
                // sonst PHP mail()
                $mail->isMail();
            }

            $mail->setFrom($CONFIGCLASS->get('EMAILS_FROMEMAIL'), $CONFIG['PROJECT_NAME']);
            $mail->addAddress($to, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;

            // PDF als Anhang
            $mail->addStringAttachment($pdfData, $pdfFilename, 'base64', 'application/pdf');

            return $mail->send();
        } catch (\Exception $e) {
            trigger_error('InvoiceMailService: ' . $e->getMessage(), E_USER_WARNING);
            return false;
        }
    }

    private function getPdfData(array $s3file): ?string
    {
        global $bCMS;
        try {
            if (isset($s3file['s3files_id']) && $s3file['s3files_id']) {
                $localPath = $bCMS->localFilePath($s3file['s3files_id']);
                if ($localPath && file_exists($localPath)) {
                    $data = file_get_contents($localPath);
                    if ($data !== false) return $data;
                }
            }
        } catch (\Exception $e) {}

        return null;
    }
}
