<?php
/**
 * GoBD-Verfahrensdokumentation als PDF
 *
 * Generiert eine automatische Verfahrensdokumentation gemaess GoBD
 * (Grundsaetze zur ordnungsmaessigen Fuehrung und Aufbewahrung von
 * Buechern, Aufzeichnungen und Unterlagen in elektronischer Form).
 *
 * Die Verfahrensdokumentation beschreibt:
 * 1. Allgemeine Beschreibung (System, Zweck)
 * 2. Anwenderdokumentation (Bedienung)
 * 3. Technische Systemdokumentation (Architektur, Sicherheit)
 * 4. Betriebsdokumentation (Backup, Wartung)
 */

use Dompdf\Dompdf;
use Dompdf\Options;

class GobdDocumentationService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Verfahrensdokumentation als PDF generieren
     */
    public function generatePdf(int $instanceId): string
    {
        $this->db->where('instances_id', $instanceId);
        $business = $this->db->getOne('instances');

        // Statistiken sammeln
        $stats = $this->getSystemStats($instanceId);

        $html = $this->renderHtml($business, $stats);

        $dompdf = new Dompdf((new Options())->set('isRemoteEnabled', false));
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        return $dompdf->output();
    }

    private function getSystemStats(int $instanceId): array
    {
        // Anzahl Dokumente
        $this->db->where('instances_id', $instanceId);
        $docCount = $this->db->getValue('document_exports', 'count(*)');

        // Nummernkreise
        $this->db->where('instances_id', $instanceId);
        $sequences = $this->db->get('document_sequences') ?: [];

        // Benutzer
        $this->db->where('instances_id', $instanceId);
        $userCount = $this->db->getValue('usersInstances', 'count(*)');

        return [
            'document_count' => $docCount ?: 0,
            'sequences' => $sequences,
            'user_count' => $userCount ?: 0,
            'generated_at' => date('d.m.Y H:i'),
        ];
    }

    private function renderHtml(array $business, array $stats): string
    {
        $companyName = htmlspecialchars($business['instances_name'] ?? 'Unbekannt');
        $taxNumber = htmlspecialchars($business['instances_taxNumber'] ?? '-');
        $vatId = htmlspecialchars($business['instances_vatId'] ?? '-');
        $date = $stats['generated_at'];
        $docCount = $stats['document_count'];
        $userCount = $stats['user_count'];

        $seqHtml = '';
        foreach ($stats['sequences'] as $seq) {
            $typeLabel = ['invoice'=>'Rechnungen','quote'=>'Angebote','delivery_note'=>'Lieferscheine'][$seq['type']] ?? $seq['type'];
            $seqHtml .= "<tr><td>{$typeLabel}</td><td>{$seq['prefix']}XXXX{$seq['suffix']}</td>"
                . "<td>{$seq['current_number']}</td><td>" . ucfirst($seq['reset_period']) . "</td></tr>";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<style>
body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10pt; line-height: 1.5; color: #333; margin: 20mm; }
h1 { font-size: 18pt; color: #1a1a1a; border-bottom: 2px solid #333; padding-bottom: 5mm; }
h2 { font-size: 14pt; color: #2c3e50; margin-top: 8mm; border-bottom: 1px solid #ccc; padding-bottom: 2mm; }
h3 { font-size: 11pt; color: #34495e; margin-top: 5mm; }
table { width: 100%; border-collapse: collapse; margin: 3mm 0; font-size: 9pt; }
th, td { border: 1px solid #ddd; padding: 2mm 3mm; text-align: left; }
th { background: #f5f5f5; font-weight: bold; }
.meta { color: #666; font-size: 8pt; margin-bottom: 5mm; }
.footer { margin-top: 10mm; padding-top: 3mm; border-top: 1px solid #ccc; font-size: 8pt; color: #999; }
.highlight { background: #fff3cd; padding: 2mm 4mm; border-left: 3px solid #ffc107; margin: 3mm 0; }
ol, ul { margin: 2mm 0; padding-left: 8mm; }
li { margin-bottom: 1mm; }
</style>
</head>
<body>
<h1>Verfahrensdokumentation</h1>
<p class="meta">Erstellt am {$date} | {$companyName}</p>

<div class="highlight">
<strong>Hinweis:</strong> Diese Verfahrensdokumentation ist gemaess den GoBD (BMF-Schreiben vom 28.11.2019, BStBl I S. 1269) erforderlich und beschreibt die Verfahren zur Erstellung, Aufbewahrung und Archivierung von Geschaeftsdokumenten in diesem System.
</div>

<h2>1. Allgemeine Beschreibung</h2>
<h3>1.1 Unternehmen</h3>
<table>
<tr><th>Firma</th><td>{$companyName}</td></tr>
<tr><th>Steuernummer</th><td>{$taxNumber}</td></tr>
<tr><th>USt-IdNr.</th><td>{$vatId}</td></tr>
</table>

<h3>1.2 Systemzweck</h3>
<p>Das System dient der Erstellung, Verwaltung und Archivierung von Geschaeftsdokumenten (Rechnungen, Angebote, Lieferscheine) sowie der Projekt- und Kundenverwaltung. Es erfuellt die Anforderungen der GoBD an die ordnungsmaessige Buchfuehrung in elektronischer Form.</p>

<h3>1.3 Einsatzgebiet</h3>
<p>Vermietung von Equipment und Veranstaltungstechnik, Projektmanagement, Rechnungsstellung.</p>

<h2>2. Anwenderdokumentation</h2>
<h3>2.1 Dokumentenerstellung</h3>
<ol>
<li><strong>Angebotserstellung:</strong> Ueber Projektansicht → Dokumente → "Angebot erstellen". Das System vergibt automatisch eine fortlaufende Angebotsnummer (Format: AN-JJJJ-NNNN).</li>
<li><strong>Rechnungserstellung:</strong> Ueber Projektansicht → Dokumente → "Rechnung erstellen" oder durch Konvertierung eines Angebots. Fortlaufende Rechnungsnummer (Format: RE-JJJJ-NNNN).</li>
<li><strong>Lieferscheinerstellung:</strong> Ueber Projektansicht → Dokumente → "Lieferschein erstellen". Fortlaufende Nummer (Format: LS-JJJJ-NNNN).</li>
</ol>

<h3>2.2 Unveraenderbarkeit (GoBD §146 AO)</h3>
<p>Nach Erstellung einer Rechnung ist diese <strong>unveraenderbar</strong>. Das System verhindert technisch die erneute Generierung einer Rechnung fuer dasselbe Projekt. Aenderungen sind ausschliesslich ueber Stornorechnungen (Gutschriften) und Neuerstellung moeglich.</p>

<h3>2.3 Dokumentenlebenszyklus</h3>
<p>Jedes Dokument durchlaeuft definierte Status:</p>
<ul>
<li><strong>Entwurf</strong> → <strong>Erstellt</strong> → <strong>Versendet</strong> → <strong>Angenommen/Abgelehnt</strong></li>
<li>Rechnungen: → <strong>Bezahlt</strong> oder → <strong>Ueberfaellig</strong> → <strong>Gemahnt</strong></li>
<li>Alle Statusaenderungen werden mit Zeitstempel und Benutzer-ID protokolliert.</li>
</ul>

<h2>3. Technische Systemdokumentation</h2>
<h3>3.1 Systemarchitektur</h3>
<table>
<tr><th>Komponente</th><th>Technologie</th></tr>
<tr><td>Anwendung</td><td>PHP 8.x, Twig Template Engine</td></tr>
<tr><td>Datenbank</td><td>MySQL/MariaDB (utf8mb4)</td></tr>
<tr><td>PDF-Generierung</td><td>Dompdf (PDF/A-3b fuer Rechnungen)</td></tr>
<tr><td>E-Rechnung</td><td>ZUGFeRD 2.1 / Factur-X (COMFORT Profil)</td></tr>
<tr><td>Dateispeicherung</td><td>Lokales Dateisystem / S3-kompatibel</td></tr>
</table>

<h3>3.2 Nummernkreise</h3>
<table>
<tr><th>Dokumenttyp</th><th>Format</th><th>Aktueller Stand</th><th>Reset</th></tr>
{$seqHtml}
</table>
<p>Nummern werden atomar vergeben (Row-Level Locking) und im <code>document_sequence_log</code> protokolliert. Stornierte Nummern werden nicht geloescht, sondern als <code>void</code> markiert mit Begruendung.</p>

<h3>3.3 Datensicherheit</h3>
<ul>
<li><strong>Zugriffskontrolle:</strong> Rollenbasiertes Berechtigungssystem mit granularen Rechten</li>
<li><strong>Authentifizierung:</strong> Session-basiert mit HttpOnly, Secure, SameSite=Strict Cookies</li>
<li><strong>CSRF-Schutz:</strong> Token-basierter Schutz fuer alle schreibenden API-Aufrufe</li>
<li><strong>Rate-Limiting:</strong> Schutz gegen Brute-Force-Angriffe (Login, API)</li>
<li><strong>Account-Lockout:</strong> Automatische Sperre nach 15 Fehlversuchen</li>
</ul>

<h3>3.4 Datenintegritaet</h3>
<ul>
<li><strong>Snapshot-Archivierung:</strong> Jedes generierte Dokument wird mit allen Berechnungsdaten (Positionen, Betraege, Steuern) als JSON-Snapshot gespeichert.</li>
<li><strong>PDF + XML:</strong> Rechnungen werden als PDF/A-3b mit eingebettetem ZUGFeRD-XML gespeichert.</li>
<li><strong>Audit-Trail:</strong> Ersteller, Zeitstempel und Aenderungshistorie werden fuer jedes Dokument protokolliert.</li>
</ul>

<h2>4. Betriebsdokumentation</h2>
<h3>4.1 Aufbewahrungsfristen</h3>
<table>
<tr><th>Dokumenttyp</th><th>Frist</th><th>Rechtsgrundlage</th></tr>
<tr><td>Rechnungen (ein- und ausgehend)</td><td>10 Jahre</td><td>§ 147 Abs. 1 Nr. 1 AO</td></tr>
<tr><td>Angebote / Auftragsbestaetigungen</td><td>6 Jahre</td><td>§ 147 Abs. 1 Nr. 2 AO</td></tr>
<tr><td>Lieferscheine</td><td>6 Jahre</td><td>§ 147 Abs. 1 Nr. 4a AO</td></tr>
<tr><td>Buchungsbelege</td><td>10 Jahre</td><td>§ 147 Abs. 1 Nr. 4 AO</td></tr>
</table>
<p>Das System setzt automatisch eine Aufbewahrungsfrist von 10 Jahren fuer alle Dokumente. Nach Ablauf wird der Status auf <code>retention_expired</code> gesetzt. Eine Loeschung erfolgt nur nach manueller Pruefung.</p>

<h3>4.2 Automatisierte Pruefungen</h3>
<ul>
<li><strong>Monatlich:</strong> Archivierungspruefung (gobd-archive-check.php) — Aufbewahrungsfristen setzen, Luecken in Nummernkreisen pruefen</li>
<li><strong>Taeglich:</strong> Ueberfaellige Rechnungen markieren (overdue-invoices.php)</li>
<li><strong>Monatlich:</strong> KUR-Umsatzgrenzen pruefen (kur-threshold-check.php)</li>
</ul>

<h3>4.3 Systemstatistiken</h3>
<table>
<tr><th>Kennzahl</th><th>Wert</th></tr>
<tr><td>Gespeicherte Dokumente</td><td>{$docCount}</td></tr>
<tr><td>Registrierte Benutzer</td><td>{$userCount}</td></tr>
<tr><td>Dokumentation erstellt am</td><td>{$date}</td></tr>
</table>

<div class="footer">
<p>Diese Verfahrensdokumentation wurde automatisch generiert und entspricht dem Stand vom {$date}.<br>
Sie ist bei Aenderungen am System zu aktualisieren (§ 147 Abs. 6 AO).</p>
<p><strong>{$companyName}</strong> | Steuernummer: {$taxNumber} | USt-IdNr.: {$vatId}</p>
</div>
</body>
</html>
HTML;
    }

    /**
     * PDF generieren und als Datei speichern
     */
    public function generateAndStore(int $instanceId, int $userId): array
    {
        $pdf = $this->generatePdf($instanceId);
        $docNumber = 'VD-' . date('Y-m-d');

        $fileInfo = S3Files::storeProjectFile($this->db, $instanceId, 0, 30, [
            'name' => 'Verfahrensdokumentation_' . date('Y-m-d') . '.pdf',
            'content' => $pdf,
            'extension' => 'pdf',
        ]);

        // Protokollieren
        $this->db->insert('dsgvo_reports', [
            'instances_id' => $instanceId,
            'report_year' => (int)date('Y'),
            'report_type' => 'avv',
            's3files_id' => $fileInfo['s3files_id'] ?? null,
            'generated_by' => $userId,
            'details_json' => json_encode(['type' => 'verfahrensdokumentation', 'date' => date('Y-m-d')]),
        ]);

        return $fileInfo;
    }
}
