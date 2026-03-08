<?php
/**
 * Duplikat-Erkennung: Findet potenzielle Duplikate bei Kunden,
 * Lieferanten und Asset-Typen.
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("CLIENTS:VIEW") && !$AUTH->instancePermissionCheck("ASSETS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

require_once __DIR__ . '/../../services/ClaudeService.php';

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$claude = new ClaudeService($DBLIB, $instanceId);
if (!$claude->isFeatureEnabled('duplicate_detect')) {
    finish(false, ["code" => "DISABLED", "message" => "Duplikat-Erkennung ist deaktiviert."]);
}

$entityType = trim($_POST['entity_type'] ?? 'clients'); // clients, asset_types

$context = '';
$entityLabel = '';

if ($entityType === 'clients') {
    $entityLabel = 'Kunden';
    $DBLIB->where('instances_id', $instanceId);
    $DBLIB->where('clients_deleted', 0);
    $DBLIB->orderBy('clients_name', 'ASC');
    $clients = $DBLIB->get('clients', null, [
        'clients_id', 'clients_name', 'clients_email', 'clients_phone',
        'clients_address', 'clients_website', 'clients_vatId'
    ]) ?: [];

    $context = "Kundenliste (" . count($clients) . " Eintraege):\n";
    foreach ($clients as $c) {
        $context .= "- ID {$c['clients_id']}: \"{$c['clients_name']}\"";
        if ($c['clients_email']) $context .= ", E-Mail: {$c['clients_email']}";
        if ($c['clients_phone']) $context .= ", Tel: {$c['clients_phone']}";
        if ($c['clients_address']) $context .= ", Adr: " . mb_substr($c['clients_address'], 0, 50);
        if ($c['clients_vatId']) $context .= ", USt-IdNr: {$c['clients_vatId']}";
        $context .= "\n";
    }
} elseif ($entityType === 'asset_types') {
    $entityLabel = 'Asset-Typen';
    $sql = "SELECT at.assetTypes_id, at.assetTypes_name, at.assetTypes_dayRate,
                   m.manufacturers_name, ac.assetCategories_name
            FROM assetTypes at
            LEFT JOIN manufacturers m ON at.manufacturers_id = m.manufacturers_id
            LEFT JOIN assetCategories ac ON at.assetCategories_id = ac.assetCategories_id
            WHERE at.instances_id = ?
            ORDER BY at.assetTypes_name ASC";
    $types = $DBLIB->rawQuery($sql, [$instanceId]) ?: [];

    $context = "Asset-Typen (" . count($types) . " Eintraege):\n";
    foreach ($types as $t) {
        $context .= "- ID {$t['assetTypes_id']}: \"{$t['assetTypes_name']}\"";
        if ($t['manufacturers_name']) $context .= ", Hersteller: {$t['manufacturers_name']}";
        if ($t['assetCategories_name']) $context .= ", Kategorie: {$t['assetCategories_name']}";
        $context .= ", Tagespreis: {$t['assetTypes_dayRate']} EUR";
        $context .= "\n";
    }
} else {
    finish(false, ["code" => "INVALID", "message" => "Ungueltiger Entity-Typ. Erlaubt: clients, asset_types"]);
}

$systemPrompt = <<<PROMPT
Du bist ein Datenqualitaets-Experte. Analysiere die {$entityLabel}-Liste und finde potenzielle Duplikate.
Pruefe auf:
- Aehnliche oder identische Namen (auch mit Tippfehlern, Abkuerzungen, unterschiedlicher Schreibweise)
- Gleiche E-Mail-Adressen oder Telefonnummern bei verschiedenen Eintraegen
- Gleiche Adressen bei unterschiedlichen Namen
- Gleiche USt-IdNr bei verschiedenen Eintraegen

Gib die Antwort als JSON zurueck:
{
  "duplicates": [
    {
      "ids": [1, 2],
      "names": ["Name 1", "Name 2"],
      "confidence": 0.95,
      "reason": "Begruendung warum es ein Duplikat ist",
      "recommended_action": "zusammenfuehren|pruefen|ignorieren"
    }
  ],
  "data_quality_score": 1-100,
  "issues": ["Allgemeines Datenqualitaets-Problem 1"],
  "summary": "X potenzielle Duplikate gefunden unter Y Eintraegen."
}
Antworte NUR mit JSON.
PROMPT;

$response = $claude->ask('duplicate_detect', $systemPrompt, $context);
$text = ClaudeService::extractText($response);

$result = json_decode($text, true);
if (!$result && preg_match('/\{[\s\S]*\}/', $text, $m)) {
    $result = json_decode($m[0], true);
}

finish(true, null, [
    'entity_type' => $entityType,
    'detection' => $result ?: ['summary' => $text],
]);
