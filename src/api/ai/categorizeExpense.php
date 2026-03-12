<?php
/**
 * Feature 6: Ausgaben automatisch kategorisieren
 *
 * Nimmt eine Beschreibung/Vendor und schlaegt EUeR-Kategorie vor.
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

require_once __DIR__ . '/../../services/ClaudeService.php';
require_once __DIR__ . '/../../services/EuerService.php';

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$claude = new ClaudeService($DBLIB, $instanceId);
if (!$claude->isFeatureEnabled('expense_category')) {
    finish(false, ["code" => "DISABLED", "message" => "Ausgaben-Kategorisierung ist deaktiviert."]);
}

$vendorName = trim($_POST['vendor_name'] ?? '');
$description = trim($_POST['description'] ?? '');
$amount = trim($_POST['amount'] ?? '');

if (!$vendorName && !$description) finish(false, ["code" => "EMPTY"]);

// Get available EUeR categories
$euer = new EuerService($DBLIB);
$categories = $euer->getCategories($instanceId);
$catList = "";
foreach ($categories as $c) {
    if ($c['category_type'] === 'expense') {
        $catList .= "- ID:{$c['id']} - {$c['name']} (EUeR-Zeile: {$c['euer_line']})\n";
    }
}

$systemPrompt = <<<PROMPT
Du bist ein Buchhaltungs-Assistent fuer die EUeR (Einnahmenueberschussrechnung).
Ordne die folgende Ausgabe der passenden Kategorie zu.

Verfuegbare Kategorien:
{$catList}

Gib die Antwort als JSON zurueck:
{
  "category_id": 123,
  "category_name": "Name der Kategorie",
  "confidence": 0.9,
  "reasoning": "Kurze Begruendung auf Deutsch"
}
Antworte NUR mit JSON.
PROMPT;

$input = "";
if ($vendorName) $input .= "Lieferant: {$vendorName}\n";
if ($description) $input .= "Beschreibung: {$description}\n";
if ($amount) $input .= "Betrag: {$amount} EUR\n";

$response = $claude->ask('expense_category', $systemPrompt, $input);
$text = ClaudeService::extractText($response);

$result = json_decode($text, true);
if (!$result && preg_match('/\{[\s\S]*\}/', $text, $m)) {
    $result = json_decode($m[0], true);
}

finish(true, null, $result ?: ['category_id' => null, 'reasoning' => $text]);
