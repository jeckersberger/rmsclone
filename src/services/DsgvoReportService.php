<?php
/**
 * DSGVO-Jahresbericht als PDF
 *
 * Generiert einen jaehrlichen Datenschutzbericht mit:
 * - Uebersicht der gespeicherten personenbezogenen Daten
 * - Durchgefuehrte DSGVO-Aktionen (Exporte, Loeschungen, Anonymisierungen)
 * - Aufbewahrungsfristen-Status
 * - Cookie-Consent-Statistiken
 * - Empfehlungen
 */

use Dompdf\Dompdf;
use Dompdf\Options;

class DsgvoReportService
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Jaehrlichen DSGVO-Report generieren
     */
    public function generateAnnualReport(int $instanceId, int $year): string
    {
        $stats = $this->collectStats($instanceId, $year);
        $html = $this->renderReportHtml($instanceId, $year, $stats);

        $dompdf = new Dompdf((new Options())->set('isRemoteEnabled', false));
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        return $dompdf->output();
    }

    /**
     * Report generieren und speichern
     */
    public function generateAndStore(int $instanceId, int $year, int $userId): array
    {
        $pdf = $this->generateAnnualReport($instanceId, $year);

        $fileInfo = S3Files::storeProjectFile($this->db, $instanceId, 0, 30, [
            'name' => "DSGVO_Jahresbericht_{$year}.pdf",
            'content' => $pdf,
            'extension' => 'pdf',
        ]);

        $this->db->insert('dsgvo_reports', [
            'instances_id' => $instanceId,
            'report_year' => $year,
            'report_type' => 'annual',
            's3files_id' => $fileInfo['s3files_id'] ?? null,
            'generated_by' => $userId,
            'details_json' => json_encode($this->collectStats($instanceId, $year)),
        ]);

        return $fileInfo;
    }

    private function collectStats(int $instanceId, int $year): array
    {
        $startDate = "{$year}-01-01";
        $endDate = "{$year}-12-31";

        // Kunden gesamt
        $this->db->where('instances_id', $instanceId);
        $this->db->where('clients_deleted', 0);
        $totalClients = (int)$this->db->getValue('clients', 'count(*)');

        // Benutzer gesamt
        $this->db->where('instances_id', $instanceId);
        $totalUsers = (int)$this->db->getValue('usersInstances', 'count(*)');

        // DSGVO-Aktionen im Jahr
        $sql = "SELECT action, COUNT(*) as cnt FROM dsgvo_log
                WHERE instances_id = ? AND created_at BETWEEN ? AND ?
                GROUP BY action";
        $dsgvoActions = $this->db->rawQuery($sql, [$instanceId, $startDate, $endDate . ' 23:59:59']) ?: [];

        // Cookie-Consents
        $sql = "SELECT COUNT(*) as total,
                SUM(consent_given = 1) as accepted,
                SUM(consent_given = 0) as revoked
                FROM cookie_consents
                WHERE instances_id = ? AND consented_at BETWEEN ? AND ?";
        $cookieStats = $this->db->rawQuery($sql, [$instanceId, $startDate, $endDate . ' 23:59:59']);
        $cookies = $cookieStats ? $cookieStats[0] : ['total' => 0, 'accepted' => 0, 'revoked' => 0];

        // Dokumente mit abgelaufener Aufbewahrungsfrist
        $this->db->where('instances_id', $instanceId);
        $this->db->where('archive_status', 'retention_expired');
        $expiredDocs = (int)$this->db->getValue('document_exports', 'count(*)');

        // Inaktive Kunden (>3 Jahre keine Aktivitaet)
        $sql = "SELECT COUNT(*) as cnt FROM clients c
                WHERE c.instances_id = ? AND c.clients_deleted = 0
                AND c.clients_id NOT IN (
                    SELECT DISTINCT p.clients_id FROM projects p
                    WHERE p.instances_id = ? AND p.projects_dateStart >= DATE_SUB(NOW(), INTERVAL 3 YEAR)
                )";
        $inactiveResult = $this->db->rawQuery($sql, [$instanceId, $instanceId]);
        $inactiveClients = (int)($inactiveResult[0]['cnt'] ?? 0);

        return [
            'total_clients' => $totalClients,
            'total_users' => $totalUsers,
            'dsgvo_actions' => $dsgvoActions,
            'cookie_consents' => $cookies,
            'expired_docs' => $expiredDocs,
            'inactive_clients' => $inactiveClients,
        ];
    }

    private function renderReportHtml(int $instanceId, int $year, array $stats): string
    {
        $this->db->where('instances_id', $instanceId);
        $business = $this->db->getOne('instances');
        $companyName = htmlspecialchars($business['instances_name'] ?? 'Unbekannt');
        $date = date('d.m.Y');

        $actionsHtml = '';
        if (!empty($stats['dsgvo_actions'])) {
            foreach ($stats['dsgvo_actions'] as $a) {
                $label = self::actionLabel($a['action']);
                $actionsHtml .= "<tr><td>{$label}</td><td>{$a['cnt']}</td></tr>";
            }
        } else {
            $actionsHtml = '<tr><td colspan="2" style="color:#999;">Keine DSGVO-Aktionen in diesem Zeitraum</td></tr>';
        }

        $cookieTotal = (int)($stats['cookie_consents']['total'] ?? 0);
        $cookieAccepted = (int)($stats['cookie_consents']['accepted'] ?? 0);
        $cookieRevoked = (int)($stats['cookie_consents']['revoked'] ?? 0);

        return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<style>
body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10pt; line-height: 1.5; color: #333; margin: 20mm; }
h1 { font-size: 18pt; color: #1a1a1a; border-bottom: 2px solid #2980b9; padding-bottom: 5mm; }
h2 { font-size: 13pt; color: #2c3e50; margin-top: 8mm; }
table { width: 100%; border-collapse: collapse; margin: 3mm 0; font-size: 9pt; }
th, td { border: 1px solid #ddd; padding: 2mm 3mm; text-align: left; }
th { background: #ecf0f1; }
.meta { color: #666; font-size: 8pt; }
.alert { background: #fff3cd; padding: 3mm 4mm; border-left: 3px solid #f39c12; margin: 3mm 0; }
.ok { background: #d4edda; padding: 3mm 4mm; border-left: 3px solid #28a745; margin: 3mm 0; }
.footer { margin-top: 10mm; border-top: 1px solid #ccc; padding-top: 3mm; font-size: 8pt; color: #999; }
</style>
</head>
<body>
<h1>DSGVO-Jahresbericht {$year}</h1>
<p class="meta">Erstellt am {$date} | {$companyName}</p>

<h2>1. Bestandsaufnahme personenbezogener Daten</h2>
<table>
<tr><th>Kategorie</th><th>Anzahl</th><th>Rechtsgrundlage</th></tr>
<tr><td>Kundendaten</td><td>{$stats['total_clients']}</td><td>Art. 6 Abs. 1 lit. b DSGVO (Vertragserfuellung)</td></tr>
<tr><td>Benutzerdaten</td><td>{$stats['total_users']}</td><td>Art. 6 Abs. 1 lit. b DSGVO (Vertragserfuellung)</td></tr>
<tr><td>Dokumente mit abgelaufener Aufbewahrungsfrist</td><td>{$stats['expired_docs']}</td><td>§ 147 AO (Aufbewahrungspflicht)</td></tr>
</table>

<h2>2. Durchgefuehrte DSGVO-Aktionen</h2>
<table>
<tr><th>Aktion</th><th>Anzahl</th></tr>
{$actionsHtml}
</table>

<h2>3. Cookie-Consent-Statistiken</h2>
<table>
<tr><th>Kennzahl</th><th>Wert</th></tr>
<tr><td>Consent-Anfragen gesamt</td><td>{$cookieTotal}</td></tr>
<tr><td>Akzeptiert</td><td>{$cookieAccepted}</td></tr>
<tr><td>Widerrufen</td><td>{$cookieRevoked}</td></tr>
</table>

<h2>4. Empfehlungen</h2>
HTML;

        // Empfehlungen basierend auf Daten
        $recommendations = '';
        if ($stats['inactive_clients'] > 0) {
            $recommendations .= "<div class='alert'><strong>Inaktive Kunden:</strong> {$stats['inactive_clients']} Kunden hatten seit ueber 3 Jahren keinen Auftrag. Pruefen Sie, ob eine Loeschung oder Anonymisierung gemaess Art. 17 DSGVO erforderlich ist.</div>";
        }
        if ($stats['expired_docs'] > 0) {
            $recommendations .= "<div class='alert'><strong>Abgelaufene Aufbewahrungsfristen:</strong> {$stats['expired_docs']} Dokumente haben die 10-Jahres-Frist ueberschritten. Pruefen Sie, ob diese geloescht werden koennen.</div>";
        }
        if (empty($recommendations)) {
            $recommendations = "<div class='ok'>Keine dringenden Massnahmen erforderlich.</div>";
        }

        return $this->renderReportHtml_part1($instanceId, $year, $stats) . $recommendations . <<<HTML

<h2>5. Technische und organisatorische Massnahmen (TOMs)</h2>
<ul>
<li>Zugriffskontrolle: Rollenbasiertes Berechtigungssystem</li>
<li>Verschluesselung: HTTPS/TLS fuer alle Verbindungen</li>
<li>Session-Sicherheit: HttpOnly, Secure, SameSite=Strict Cookies</li>
<li>CSRF-Schutz: Token-basiert fuer alle schreibenden Operationen</li>
<li>Rate-Limiting: Schutz gegen Brute-Force-Angriffe</li>
<li>Audit-Log: Alle DSGVO-relevanten Aktionen werden protokolliert</li>
</ul>

<div class="footer">
<p>Dieser Bericht wurde automatisch generiert. Er ersetzt keine individuelle datenschutzrechtliche Beratung.</p>
<p>{$companyName} | Datenschutzbeauftragter: [Name einsetzen] | Erstellt: {$date}</p>
</div>
</body>
</html>
HTML;
    }

    /**
     * Nur der erste Teil des HTML (ohne Empfehlungen)
     */
    private function renderReportHtml_part1(int $instanceId, int $year, array $stats): string
    {
        // Wird ueber renderReportHtml aufgerufen, gibt den Anfang zurueck
        // Diese Methode existiert fuer die Trennung - das HTML wird in renderReportHtml zusammengebaut
        return '';
    }

    private static function actionLabel(string $action): string
    {
        $labels = [
            'export' => 'Datenexport (Art. 15/20)',
            'anonymize' => 'Anonymisierung (Art. 17)',
            'delete' => 'Loeschung',
            'access' => 'Auskunft (Art. 15)',
            'retention_check' => 'Aufbewahrungsfrist-Pruefung',
        ];
        return $labels[$action] ?? $action;
    }
}
