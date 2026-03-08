<?php
/**
 * Kunden-Risikobewertung: Analysiert Zahlungsverhalten und bewertet Kreditrisiko.
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("CLIENTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

require_once __DIR__ . '/../../services/ClaudeService.php';

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$claude = new ClaudeService($DBLIB, $instanceId);
if (!$claude->isFeatureEnabled('client_risk')) {
    finish(false, ["code" => "DISABLED", "message" => "Kunden-Risikobewertung ist deaktiviert."]);
}

$clientId = (int)($_POST['client_id'] ?? 0);
if (!$clientId) finish(false, ["code" => "INVALID", "message" => "Kunden-ID fehlt."]);

// Load client
$DBLIB->where('clients_id', $clientId);
$DBLIB->where('instances_id', $instanceId);
$client = $DBLIB->getOne('clients');
if (!$client) finish(false, ["code" => "NOT_FOUND"]);

// Get project history
$DBLIB->where('clients_id', $clientId);
$DBLIB->where('instances_id', $instanceId);
$DBLIB->where('projects_deleted', 0);
$DBLIB->orderBy('projects_dateStart', 'DESC');
$projects = $DBLIB->get('projects', 20) ?: [];

// Get payment history from document lifecycle
$sql = "SELECT dl.doc_type, dl.doc_number, dl.status, dl.gross_amount, dl.created_at, dl.due_date, dl.paid_at
        FROM document_lifecycle dl
        JOIN projects p ON dl.projects_id = p.projects_id
        WHERE p.clients_id = ? AND dl.instances_id = ?
        ORDER BY dl.created_at DESC LIMIT 30";
$payments = $DBLIB->rawQuery($sql, [$clientId, $instanceId]) ?: [];

// Get dunning history
$sql = "SELECT d.dunning_level, d.dunning_date, d.dunning_amount, d.dunning_status
        FROM dunning_records d
        JOIN projects p ON d.projects_id = p.projects_id
        WHERE p.clients_id = ? AND d.instances_id = ?
        ORDER BY d.dunning_date DESC LIMIT 10";
$dunnings = $DBLIB->rawQuery($sql, [$clientId, $instanceId]) ?: [];

$context = "Kunde: {$client['clients_name']}\n";
$context .= "E-Mail: {$client['clients_email']}\n";
$context .= "Anzahl Projekte: " . count($projects) . "\n\n";

$context .= "Rechnungs-/Zahlungshistorie:\n";
foreach ($payments as $p) {
    $late = '';
    if ($p['paid_at'] && $p['due_date']) {
        $diff = (strtotime($p['paid_at']) - strtotime($p['due_date'])) / 86400;
        if ($diff > 0) $late = " (+" . round($diff) . " Tage zu spaet)";
    }
    $context .= "- {$p['doc_type']} {$p['doc_number']}: {$p['gross_amount']} EUR, Status: {$p['status']}{$late}\n";
}

if (!empty($dunnings)) {
    $context .= "\nMahnungen:\n";
    foreach ($dunnings as $d) {
        $context .= "- Stufe {$d['dunning_level']}: {$d['dunning_date']}, {$d['dunning_amount']} EUR, Status: {$d['dunning_status']}\n";
    }
}

$systemPrompt = <<<'PROMPT'
Du bist ein Kreditrisiko-Analyst fuer ein Equipment-Verleihunternehmen.
Analysiere das Zahlungsverhalten des Kunden und bewerte das Risiko.

Gib die Antwort als JSON zurueck:
{
  "risk_score": 1-10,
  "risk_level": "niedrig|mittel|hoch|sehr_hoch",
  "payment_behavior": "Kurze Beschreibung des Zahlungsverhaltens",
  "avg_payment_delay_days": 0,
  "recommended_credit_limit": 0.00,
  "recommended_payment_terms": "Vorkasse|14 Tage|30 Tage",
  "warnings": ["Warnung 1"],
  "positive_factors": ["Positiver Faktor 1"],
  "recommendation": "Zusammenfassende Empfehlung"
}
Antworte NUR mit JSON.
PROMPT;

$response = $claude->ask('client_risk', $systemPrompt, $context);
$text = ClaudeService::extractText($response);

$result = json_decode($text, true);
if (!$result && preg_match('/\{[\s\S]*\}/', $text, $m)) {
    $result = json_decode($m[0], true);
}

finish(true, null, [
    'client' => $client['clients_name'],
    'assessment' => $result ?: ['recommendation' => $text],
]);
