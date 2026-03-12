<?php
/**
 * Dokument-Qualitaetscheck: Prueft Angebote/Rechnungen auf Vollstaendigkeit,
 * Konsistenz und typische Fehler vor dem Versand.
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

require_once __DIR__ . '/../../services/ClaudeService.php';

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$claude = new ClaudeService($DBLIB, $instanceId);
if (!$claude->isFeatureEnabled('document_check')) {
    finish(false, ["code" => "DISABLED", "message" => "Dokument-Qualitaetscheck ist deaktiviert."]);
}

$projectId = (int)($_POST['project_id'] ?? 0);
$docType = trim($_POST['doc_type'] ?? ''); // invoice, quote, delivery_note, credit_note
if (!$projectId || !$docType) finish(false, ["code" => "INVALID", "message" => "Projekt-ID und Dokumenttyp erforderlich."]);

// Load project with client
$DBLIB->where('projects_id', $projectId);
$DBLIB->where('instances_id', $instanceId);
$DBLIB->join('clients', 'projects.clients_id=clients.clients_id', 'LEFT');
$project = $DBLIB->getOne('projects', ['projects.*', 'clients.clients_name', 'clients.clients_email',
    'clients.clients_address', 'clients.clients_vatId', 'clients.clients_reverseCharge']);
if (!$project) finish(false, ["code" => "NOT_FOUND"]);

// Get assigned assets with pricing
$sql = "SELECT at.assetTypes_name, a.assets_tag,
               COALESCE(aa.assetsAssignments_customPrice, at.assetTypes_dayRate) as day_rate,
               aa.assetsAssignments_discount as discount,
               DATEDIFF(p.projects_dateEnd, p.projects_dateStart) as days
        FROM assetsAssignments aa
        JOIN assets a ON aa.assets_id = a.assets_id
        JOIN assetTypes at ON a.assetTypes_id = at.assetTypes_id
        JOIN projects p ON aa.projects_id = p.projects_id
        WHERE aa.projects_id = ? AND aa.assetsAssignments_deleted = 0";
$assets = $DBLIB->rawQuery($sql, [$projectId]) ?: [];

// Get existing documents for this project
$DBLIB->where('projects_id', $projectId);
$DBLIB->where('instances_id', $instanceId);
$existingDocs = $DBLIB->get('document_lifecycle', null, ['doc_type', 'doc_number', 'status', 'gross_amount']) ?: [];

// Load business settings
$DBLIB->where('instances_id', $instanceId);
$instance = $DBLIB->getOne('instances', ['instances_name', 'instances_companyName', 'instances_vatId',
    'instances_address', 'instances_email', 'instances_bankIban']);

$docLabels = ['invoice' => 'Rechnung', 'quote' => 'Angebot', 'delivery_note' => 'Lieferschein', 'credit_note' => 'Gutschrift'];

$context = "Dokumenttyp: " . ($docLabels[$docType] ?? $docType) . "\n\n";
$context .= "Projekt: {$project['projects_name']}\n";
$context .= "Zeitraum: {$project['projects_dateStart']} bis {$project['projects_dateEnd']}\n";
$context .= "Rabatt: {$project['projects_defaultDiscount']}%\n\n";

$context .= "Kunde:\n";
$context .= "- Name: {$project['clients_name']}\n";
$context .= "- E-Mail: {$project['clients_email']}\n";
$context .= "- Adresse: {$project['clients_address']}\n";
$context .= "- USt-IdNr: " . ($project['clients_vatId'] ?: 'nicht hinterlegt') . "\n";
$context .= "- Reverse Charge: " . ($project['clients_reverseCharge'] ? 'ja' : 'nein') . "\n\n";

$context .= "Unternehmensdaten:\n";
$context .= "- Firma: " . ($instance['instances_companyName'] ?: $instance['instances_name']) . "\n";
$context .= "- USt-IdNr: " . ($instance['instances_vatId'] ?: 'nicht hinterlegt') . "\n";
$context .= "- IBAN: " . ($instance['instances_bankIban'] ? 'vorhanden' : 'FEHLT') . "\n\n";

$context .= "Positionen (" . count($assets) . "):\n";
$total = 0;
foreach ($assets as $a) {
    $lineTotal = $a['day_rate'] * max(1, $a['days']);
    if ($a['discount'] > 0) $lineTotal *= (1 - $a['discount'] / 100);
    $total += $lineTotal;
    $context .= "- {$a['assetTypes_name']} ({$a['assets_tag']}): {$a['day_rate']} EUR/Tag x {$a['days']} Tage";
    if ($a['discount'] > 0) $context .= " ({$a['discount']}% Rabatt)";
    $context .= " = " . round($lineTotal, 2) . " EUR\n";
}
$context .= "Gesamt netto: " . round($total, 2) . " EUR\n\n";

$context .= "Bereits vorhandene Dokumente:\n";
foreach ($existingDocs as $d) {
    $context .= "- {$d['doc_type']} {$d['doc_number']}: {$d['status']}, {$d['gross_amount']} EUR\n";
}

$systemPrompt = <<<'PROMPT'
Du bist ein Dokumenten-Pruefer fuer ein deutsches Equipment-Verleihunternehmen.
Pruefe das Dokument auf Vollstaendigkeit, Fehler und rechtliche Anforderungen.

Pruefe insbesondere:
1. Pflichtangaben (Name, Adresse, Steuernummer, Datum, fortlaufende Nummer)
2. Korrekte MwSt-Behandlung (Reverse Charge bei EU-Kunden mit USt-IdNr)
3. Positionen vorhanden und plausibel
4. Bankverbindung angegeben (bei Rechnungen)
5. Zeitraum und Preise konsistent
6. Bereits existierende Dokumente (Duplikate, fehlende Vorgaenger)

Gib die Antwort als JSON zurueck:
{
  "status": "ok|warnung|fehler",
  "score": 1-100,
  "issues": [
    {"severity": "fehler|warnung|hinweis", "message": "Beschreibung des Problems", "fix": "Loesungsvorschlag"}
  ],
  "checklist": {
    "pflichtangaben": true,
    "mwst_korrekt": true,
    "positionen_vorhanden": true,
    "bankverbindung": true,
    "preise_plausibel": true
  },
  "summary": "Zusammenfassung in 1-2 Saetzen"
}
Antworte NUR mit JSON.
PROMPT;

$response = $claude->ask('document_check', $systemPrompt, $context);
$text = ClaudeService::extractText($response);

$check = json_decode($text, true);
if (!$check && preg_match('/\{[\s\S]*\}/', $text, $m)) {
    $check = json_decode($m[0], true);
}

finish(true, null, [
    'project' => $project['projects_name'],
    'doc_type' => $docType,
    'check' => $check ?: ['summary' => $text],
]);
