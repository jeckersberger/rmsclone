<?php
/**
 * AVV (Auftragsverarbeitungsvertrag) Vorlage generieren
 *
 * Erstellt eine AVV-Vorlage gemaess Art. 28 DSGVO als PDF.
 * Der AVV regelt die Verarbeitung personenbezogener Daten
 * im Auftrag eines Verantwortlichen.
 */

use Dompdf\Dompdf;
use Dompdf\Options;

class AvvService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * AVV-Vorlage als PDF generieren
     */
    public function generatePdf(int $instanceId): string
    {
        $this->db->where('instances_id', $instanceId);
        $business = $this->db->getOne('instances');

        $html = $this->renderHtml($business);

        $dompdf = new Dompdf((new Options())->set('isRemoteEnabled', false));
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        return $dompdf->output();
    }

    private function renderHtml(array $business): string
    {
        $company = htmlspecialchars($business['instances_name'] ?? '[Firmenname]');
        $address = htmlspecialchars(($business['instances_address1'] ?? '') . ', ' . ($business['instances_postcode'] ?? '') . ' ' . ($business['instances_town'] ?? ''));
        $date = date('d.m.Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<style>
body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9.5pt; line-height: 1.5; color: #333; margin: 20mm; }
h1 { font-size: 16pt; text-align: center; color: #1a1a1a; }
h2 { font-size: 12pt; color: #2c3e50; margin-top: 6mm; border-bottom: 1px solid #ccc; padding-bottom: 1mm; }
h3 { font-size: 10pt; }
.parties { background: #f8f9fa; padding: 4mm; margin: 4mm 0; }
.field { border-bottom: 1px dotted #999; display: inline-block; min-width: 200px; padding: 1mm 2mm; color: #666; }
.signature { margin-top: 15mm; display: flex; justify-content: space-between; }
.sig-block { width: 45%; border-top: 1px solid #333; padding-top: 2mm; text-align: center; font-size: 8pt; }
ol { counter-reset: section; list-style: none; padding-left: 0; }
ol > li { counter-increment: section; margin-bottom: 3mm; }
ol > li::before { content: "(" counter(section) ") "; font-weight: bold; }
.footer { margin-top: 10mm; font-size: 7pt; color: #999; text-align: center; }
</style>
</head>
<body>
<h1>Vertrag zur Auftragsverarbeitung<br><small style="font-size:10pt;">(Art. 28 DSGVO)</small></h1>

<div class="parties">
<p><strong>Auftraggeber (Verantwortlicher):</strong><br>
<span class="field">[Name des Auftraggebers / Kunden]</span><br>
<span class="field">[Adresse]</span></p>

<p><strong>Auftragnehmer (Auftragsverarbeiter):</strong><br>
{$company}<br>
{$address}</p>
</div>

<h2>§ 1 Gegenstand und Dauer</h2>
<ol>
<li>Der Auftragnehmer verarbeitet personenbezogene Daten im Auftrag des Auftraggebers. Gegenstand der Verarbeitung ist die Nutzung des Projekt- und Rechnungsmanagementsystems.</li>
<li>Die Dauer der Verarbeitung entspricht der Laufzeit des Nutzungsvertrages.</li>
<li>Art und Zweck der Verarbeitung: Verwaltung von Kundendaten, Projekten, Rechnungen und Equipment-Buchungen.</li>
</ol>

<h2>§ 2 Art der personenbezogenen Daten</h2>
<ol>
<li>Kontaktdaten: Name, Adresse, E-Mail, Telefon</li>
<li>Vertragsdaten: Angebote, Rechnungen, Lieferscheine</li>
<li>Nutzungsdaten: Login-Zeiten, IP-Adressen (fuer Sicherheitszwecke)</li>
<li>Finanzdaten: Zahlungsinformationen, IBAN (sofern hinterlegt)</li>
</ol>

<h2>§ 3 Kategorien betroffener Personen</h2>
<ol>
<li>Kunden und Ansprechpartner des Auftraggebers</li>
<li>Mitarbeiter des Auftraggebers (als Systemnutzer)</li>
</ol>

<h2>§ 4 Pflichten des Auftragnehmers</h2>
<ol>
<li>Der Auftragnehmer verarbeitet die Daten nur auf dokumentierte Weisung des Auftraggebers.</li>
<li>Der Auftragnehmer stellt sicher, dass die zur Verarbeitung befugten Personen zur Vertraulichkeit verpflichtet sind.</li>
<li>Der Auftragnehmer trifft die erforderlichen technischen und organisatorischen Massnahmen (Art. 32 DSGVO).</li>
<li>Der Auftragnehmer unterstuetzt den Auftraggeber bei der Erfuellung der Betroffenenrechte (Art. 15-22 DSGVO).</li>
<li>Nach Beendigung der Verarbeitung werden alle Daten geloescht oder zurueckgegeben, sofern keine gesetzliche Aufbewahrungspflicht besteht.</li>
</ol>

<h2>§ 5 Technische und organisatorische Massnahmen</h2>
<ol>
<li><strong>Zutrittskontrolle:</strong> Serverstandort in gesichertem Rechenzentrum</li>
<li><strong>Zugangskontrolle:</strong> Passwortschutz, Account-Lockout, Rate-Limiting</li>
<li><strong>Zugriffskontrolle:</strong> Rollenbasiertes Berechtigungssystem</li>
<li><strong>Uebertragungskontrolle:</strong> TLS-Verschluesselung fuer alle Verbindungen</li>
<li><strong>Eingabekontrolle:</strong> Audit-Logging aller Aenderungen</li>
<li><strong>Verfuegbarkeitskontrolle:</strong> Regelmaessige Backups</li>
<li><strong>Trennungskontrolle:</strong> Mandantentrennung ueber instances_id</li>
</ol>

<h2>§ 6 Unterauftragsverarbeiter</h2>
<ol>
<li>Der Auftragnehmer setzt derzeit folgende Unterauftragsverarbeiter ein:<br>
<span class="field">[Hosting-Anbieter eintragen]</span><br>
<span class="field">[E-Mail-Provider eintragen, falls zutreffend]</span></li>
<li>Aenderungen werden dem Auftraggeber vorab mitgeteilt (Art. 28 Abs. 2 DSGVO).</li>
</ol>

<h2>§ 7 Kontrollrechte</h2>
<ol>
<li>Der Auftraggeber ist berechtigt, die Einhaltung dieses Vertrages zu ueberpruefen.</li>
<li>Der Auftragnehmer stellt dem Auftraggeber alle erforderlichen Informationen zur Verfuegung.</li>
</ol>

<h2>§ 8 Meldepflichten</h2>
<ol>
<li>Der Auftragnehmer informiert den Auftraggeber unverzueglich ueber Verletzungen des Schutzes personenbezogener Daten (Art. 33 DSGVO).</li>
</ol>

<div style="margin-top:15mm;">
<table style="width:100%;border:none;">
<tr>
<td style="width:45%;border:none;padding-top:15mm;border-top:1px solid #333;text-align:center;font-size:8pt;">
Ort, Datum, Unterschrift Auftraggeber
</td>
<td style="width:10%;border:none;"></td>
<td style="width:45%;border:none;padding-top:15mm;border-top:1px solid #333;text-align:center;font-size:8pt;">
Ort, Datum, Unterschrift Auftragnehmer
</td>
</tr>
</table>
</div>

<div class="footer">
AVV-Vorlage erstellt am {$date} | {$company} | Diese Vorlage ersetzt keine individuelle Rechtsberatung.
</div>
</body>
</html>
HTML;
    }
}
