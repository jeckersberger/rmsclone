<?php
/**
 * Feature 8: Preisvorschlaege basierend auf historischen Daten
 *
 * Analysiert bisherige Projekte und schlaegt optimale Tagespreise vor.
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

require_once __DIR__ . '/../../services/ClaudeService.php';

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$claude = new ClaudeService($DBLIB, $instanceId);
if (!$claude->isFeatureEnabled('price_suggestion')) {
    finish(false, ["code" => "DISABLED", "message" => "Preisvorschlaege sind deaktiviert."]);
}

$assetTypeId = (int)($_POST['asset_type_id'] ?? 0);

// Get asset type info
$DBLIB->where('assetTypes_id', $assetTypeId);
$assetType = $DBLIB->getOne('assetTypes');
if (!$assetType) finish(false, ["code" => "NOT_FOUND"]);

// Get historical pricing data
$sql = "SELECT aa.assetsAssignments_customPrice as price,
               DATEDIFF(p.projects_dateEnd, p.projects_dateStart) as days,
               p.projects_dateStart, c.clients_name
        FROM assetsAssignments aa
        JOIN assets a ON aa.assets_id = a.assets_id
        JOIN projects p ON aa.projects_id = p.projects_id
        JOIN clients c ON p.clients_id = c.clients_id
        WHERE a.assetTypes_id = ? AND aa.assetsAssignments_deleted = 0
        AND p.instances_id = ?
        ORDER BY p.projects_dateStart DESC
        LIMIT 30";
$history = $DBLIB->rawQuery($sql, [$assetTypeId, $instanceId]) ?: [];

$historyText = "Aktueller Tagespreis: {$assetType['assetTypes_dayRate']} EUR\n";
$historyText .= "Aktueller Wochenpreis: {$assetType['assetTypes_weekRate']} EUR\n";
$historyText .= "Neupreis/Wert: {$assetType['assetTypes_value']} EUR\n\n";
$historyText .= "Bisherige Vermietungen:\n";
foreach ($history as $h) {
    $historyText .= "- {$h['projects_dateStart']}: {$h['days']} Tage, {$h['price']} EUR, Kunde: {$h['clients_name']}\n";
}

$systemPrompt = <<<'PROMPT'
Du bist ein Pricing-Berater fuer ein Equipment-Verleihunternehmen.
Analysiere die bisherigen Vermietungen und schlage optimale Preise vor.
Beruecksichtige: Marktdurchschnitt, Auslastung, Neupreis-Verhaeltnis.
Eine Faustregel: Der Tagespreis sollte ca. 1-3% des Neuwerts betragen.

Gib die Antwort als JSON zurueck:
{
  "current_day_rate": 0.00,
  "suggested_day_rate": 0.00,
  "suggested_week_rate": 0.00,
  "change_pct": 0.0,
  "reasoning": "Begruendung auf Deutsch",
  "market_position": "guenstig|marktgerecht|premium",
  "confidence": 0.8
}
Antworte NUR mit JSON.
PROMPT;

$response = $claude->ask('price_suggestion', $systemPrompt, "Equipment: {$assetType['assetTypes_name']}\n\n{$historyText}");
$text = ClaudeService::extractText($response);

$suggestion = json_decode($text, true);
if (!$suggestion && preg_match('/\{[\s\S]*\}/', $text, $m)) {
    $suggestion = json_decode($m[0], true);
}

finish(true, null, [
    'asset_type' => $assetType['assetTypes_name'],
    'suggestion' => $suggestion,
    'history_count' => count($history),
]);
