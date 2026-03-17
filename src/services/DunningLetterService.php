<?php
/**
 * Mahnbrief-PDF-Generierung
 *
 * Erzeugt professionelle Mahnbriefe als PDF (Dompdf) anhand der
 * dunning_letter_de.twig-Vorlage. Speichert das PDF ueber S3Files
 * und verknuepft es mit dem dunning_history-Eintrag.
 */

use Dompdf\Dompdf;
use Dompdf\Options;

class DunningLetterService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Erzeugt einen Mahnbrief als PDF fuer einen bestehenden Dunning-History-Eintrag.
     *
     * @param int $instanceId
     * @param int $dunningId  dunning_history.id
     * @param int $userId     Aktueller Benutzer
     * @return array           ['s3files_id'=>int, 'filename'=>string, 'dunning_id'=>int]
     */
    public function generateLetter(int $instanceId, int $dunningId, int $userId): ?array
    {
        // 1) Dunning-History laden
        $this->db->where('dh.id', $dunningId);
        $this->db->where('dh.instances_id', $instanceId);
        $this->db->join('dunning_levels dl', 'dh.dunning_level_id=dl.id', 'LEFT');
        $dunning = $this->db->getOne('dunning_history dh', null, [
            'dh.*', 'dl.name as level_name', 'dl.level', 'dl.fee as level_fee',
            'dl.interest_rate as level_interest_rate'
        ]);
        if (!$dunning) return null;

        // 2) Rechnung (document_lifecycle) laden
        $this->db->where('dl.id', $dunning['document_lifecycle_id']);
        $this->db->where('dl.instances_id', $instanceId);
        $this->db->join('projects p', 'dl.projects_id=p.projects_id', 'LEFT');
        $this->db->join('clients c', 'p.clients_id=c.clients_id', 'LEFT');
        $invoice = $this->db->getOne('document_lifecycle dl', null, [
            'dl.*',
            'p.projects_name', 'p.projects_id',
            'c.clients_name', 'c.clients_address', 'c.clients_email',
            'c.clients_vatId', 'c.clients_customerNumber'
        ]);
        if (!$invoice) return null;

        // 3) Geschaeftsdaten laden
        $business = BusinessRepo::getSettings($this->db, $instanceId);

        // 4) Zahlungsfrist berechnen (14 Tage ab Mahndatum)
        $paymentDeadline = date('d.m.Y', strtotime($dunning['dunning_date'] . ' +14 days'));

        // 5) Mahnstufen-spezifische Texte
        $levelTexts = $this->getLevelTexts((int)$dunning['dunning_level']);

        // 6) Logo als Data-URI
        $logoDataUri = null;
        if (!empty($business['instances_logo'])) {
            global $bCMS;
            if (isset($bCMS)) {
                $logoDataUri = $bCMS->s3DataUri($business['instances_logo']);
            }
        }

        // 7) Tage ueberfaellig berechnen
        $daysOverdue = (int)((time() - strtotime($invoice['due_date'])) / 86400);

        // 8) Template-Variablen zusammenstellen
        $templateVars = [
            'business'          => $business,
            'client'            => [
                'clients_name'           => $invoice['clients_name'],
                'clients_address'        => $invoice['clients_address'],
                'clients_email'          => $invoice['clients_email'],
                'clients_vatId'          => $invoice['clients_vatId'],
                'clients_customerNumber' => $invoice['clients_customerNumber'],
            ],
            'invoice'           => [
                'doc_number'    => $invoice['doc_number'],
                'doc_date'      => date('d.m.Y', strtotime($invoice['created_at'])),
                'due_date'      => date('d.m.Y', strtotime($invoice['due_date'])),
                'net_amount'    => $invoice['net_amount'],
                'gross_amount'  => $invoice['gross_amount'],
                'currency'      => $invoice['currency'] ?? 'EUR',
            ],
            'dunning'           => [
                'id'              => $dunning['id'],
                'level'           => (int)$dunning['dunning_level'],
                'level_name'      => $dunning['level_name'],
                'date'            => date('d.m.Y', strtotime($dunning['dunning_date'])),
                'fee_amount'      => (float)$dunning['fee_amount'],
                'interest_amount' => (float)$dunning['interest_amount'],
                'interest_rate'   => (float)($dunning['level_interest_rate'] ?? 0),
                'total_due'       => (float)$dunning['total_due'],
                'days_overdue'    => $daysOverdue,
            ],
            'payment_deadline'  => $paymentDeadline,
            'level_texts'       => $levelTexts,
            'logo'              => $logoDataUri,
        ];

        // 9) Twig rendern
        $twig = new \Twig\Environment(
            new \Twig\Loader\FilesystemLoader(__DIR__ . '/../templates'),
            ['cache' => false, 'autoescape' => false]
        );
        $twig->addFilter(new \Twig\TwigFilter('numberDe', function ($value, int $decimals = 2) {
            return number_format((float)$value, $decimals, ',', '.');
        }));
        $twig->addFilter(new \Twig\TwigFilter('dateDe', function ($datetime, string $format = 'd.m.Y') {
            if ($datetime instanceof \DateTimeInterface) return $datetime->format($format);
            if (is_string($datetime) && strlen($datetime) > 0) return date($format, strtotime($datetime));
            return '';
        }));

        $html = $twig->render('dunning_letter_de.twig', $templateVars);

        // 10) PDF erzeugen
        $dompdf = new Dompdf((new Options())->set('isRemoteEnabled', true));
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdf = $dompdf->output();

        // 11) PDF als Datei speichern
        $filename = 'Mahnung_Stufe' . $dunning['dunning_level'] . '_' . $invoice['doc_number'] . '.pdf';
        $projectId = (int)($invoice['projects_id'] ?? 0);
        $fileInfo = S3Files::storeProjectFile($this->db, $instanceId, $projectId, 23, [
            'name'      => $filename,
            'content'   => $pdf,
            'extension' => 'pdf',
        ]);

        $s3filesId = $fileInfo['s3files_id'] ?? null;

        // 12) dunning_history aktualisieren: PDF verknuepfen
        if ($s3filesId) {
            $this->db->where('id', $dunningId);
            $this->db->update('dunning_history', [
                'letter_s3files_id' => $s3filesId,
            ]);
        }

        return [
            'dunning_id' => $dunningId,
            's3files_id' => $s3filesId,
            'filename'   => $filename,
        ];
    }

    /**
     * Markiert einen Mahnbrief als gesendet.
     */
    public function markLetterSent(int $dunningId, string $sentToEmail): void
    {
        $this->db->where('id', $dunningId);
        $this->db->update('dunning_history', [
            'letter_sent_at' => date('Y-m-d H:i:s'),
            'sent_to_email'  => $sentToEmail,
            'sent_at'        => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Gibt mahnstufen-spezifische Texte zurueck.
     */
    private function getLevelTexts(int $level): array
    {
        $texts = [
            0 => [
                'title'    => 'Zahlungserinnerung',
                'greeting' => 'Sehr geehrte Damen und Herren,',
                'intro'    => 'bei der Pruefung unserer Konten haben wir festgestellt, dass die nachstehend aufgefuehrte Rechnung noch nicht beglichen wurde. Sicherlich handelt es sich um ein Versehen.',
                'body'     => 'Wir bitten Sie hoeflich, den offenen Betrag innerhalb der unten genannten Frist auf unser Konto zu ueberweisen.',
                'closing'  => 'Sollten Sie die Zahlung bereits veranlasst haben, betrachten Sie dieses Schreiben bitte als gegenstandslos.',
                'tone'     => 'freundlich',
            ],
            1 => [
                'title'    => '1. Mahnung',
                'greeting' => 'Sehr geehrte Damen und Herren,',
                'intro'    => 'trotz unserer Zahlungserinnerung konnten wir fuer die nachstehend aufgefuehrte Rechnung leider noch keinen Zahlungseingang feststellen.',
                'body'     => 'Wir bitten Sie dringend, den ausstehenden Betrag innerhalb der genannten Frist zu begleichen.',
                'closing'  => 'Bitte beachten Sie, dass bei weiterem Zahlungsverzug Mahngebuehren und Verzugszinsen anfallen koennen.',
                'tone'     => 'bestimmt',
            ],
            2 => [
                'title'    => '2. Mahnung',
                'greeting' => 'Sehr geehrte Damen und Herren,',
                'intro'    => 'leider mussten wir feststellen, dass unsere bisherigen Zahlungsaufforderungen ohne Ergebnis geblieben sind. Die nachstehende Rechnung ist weiterhin unbezahlt.',
                'body'     => 'Wir sehen uns daher gezwungen, Ihnen eine Mahngebuehr in Rechnung zu stellen. Bitte ueberweisen Sie den Gesamtbetrag umgehend.',
                'closing'  => 'Wir weisen darauf hin, dass wir bei weiterem Zahlungsverzug rechtliche Schritte einleiten muessen.',
                'tone'     => 'nachdruecklich',
            ],
            3 => [
                'title'    => 'Letzte Mahnung vor gerichtlichem Mahnverfahren',
                'greeting' => 'Sehr geehrte Damen und Herren,',
                'intro'    => 'trotz mehrfacher Aufforderung ist die nachstehende Rechnung nach wie vor nicht beglichen. Dies ist unsere letzte aussergerichtliche Mahnung.',
                'body'     => 'Wir fordern Sie hiermit letztmalig auf, den Gesamtbetrag einschliesslich aller Mahngebuehren und Verzugszinsen innerhalb der genannten Frist zu ueberweisen.',
                'closing'  => 'Sollte bis zum Ablauf der Frist kein Zahlungseingang erfolgen, werden wir ohne weitere Ankuendigung das gerichtliche Mahnverfahren einleiten und gegebenenfalls ein Inkassounternehmen beauftragen. Die dadurch entstehenden Kosten gehen zu Ihren Lasten.',
                'tone'     => 'letzte_warnung',
            ],
        ];

        return $texts[$level] ?? $texts[0];
    }
}
