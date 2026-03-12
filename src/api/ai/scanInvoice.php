<?php
/**
 * Feature 1: PDF-Rechnung hochladen und per KI auslesen
 *
 * Akzeptiert PDF-Upload, speichert lokal, extrahiert Daten per Claude Vision.
 * Gibt extrahierte Felder zurueck (Lieferant, Betrag, Datum, etc.)
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

require_once __DIR__ . '/../../services/ClaudeService.php';
require_once __DIR__ . '/../../services/LocalFileStorage.php';

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$userId = (int)$AUTH->data['users_userid'];

$claude = new ClaudeService($DBLIB, $instanceId);
if (!$claude->isFeatureEnabled('invoice_scan')) {
    finish(false, ["code" => "DISABLED", "message" => "PDF-Scan ist deaktiviert. Bitte in den KI-Einstellungen aktivieren."]);
}

if (empty($_FILES['file'])) finish(false, ["code" => "NO_FILE", "message" => "Keine Datei hochgeladen."]);

$file = $_FILES['file'];
if ($file['size'] > 10 * 1024 * 1024) finish(false, ["code" => "TOO_LARGE", "message" => "Datei zu gross (max 10 MB)."]);

// Store locally
$stored = LocalFileStorage::store($instanceId, 'expenses', $file);

// Read as base64 for Claude
$base64 = LocalFileStorage::readBase64($stored['path']);
if (!$base64) finish(false, ["code" => "READ_ERROR", "message" => "Datei konnte nicht gelesen werden."]);

$systemPrompt = <<<'PROMPT'
Du bist ein Buchhaltungs-Assistent. Extrahiere aus der hochgeladenen Rechnung/Beleg folgende Daten und gib sie als JSON zurueck:
{
  "vendor_name": "Firmenname des Lieferanten",
  "invoice_number": "Rechnungsnummer",
  "invoice_date": "YYYY-MM-DD",
  "net_amount": 0.00,
  "vat_amount": 0.00,
  "gross_amount": 0.00,
  "vat_rate": 19.0,
  "currency": "EUR",
  "description": "Kurze Beschreibung was gekauft/geleistet wurde",
  "category_suggestion": "Eine der Kategorien: Buero, Miete, Versicherung, Fahrzeug, Reise, Telefon/Internet, Software/IT, Werbung, Beratung, Wareneinkauf, Abschreibung, Sonstiges"
}
Antworte NUR mit dem JSON-Objekt, ohne Markdown-Formatierung oder Erklaerungen.
Falls ein Feld nicht erkennbar ist, setze null.
PROMPT;

$response = $claude->askWithPdf('invoice_scan', $systemPrompt, 'Bitte extrahiere die Rechnungsdaten aus diesem Dokument.', $base64);
$text = ClaudeService::extractText($response);

// Parse JSON from response
$extracted = json_decode($text, true);
if (!$extracted) {
    // Try to extract JSON from markdown code block
    if (preg_match('/\{[\s\S]*\}/', $text, $m)) {
        $extracted = json_decode($m[0], true);
    }
}

// Save to expense_receipts
$receiptId = $DBLIB->insert('expense_receipts', [
    'instances_id' => $instanceId,
    'file_path' => $stored['path'],
    'original_name' => $stored['original_name'],
    'mime_type' => $stored['mime'],
    'file_size' => $stored['size'],
    'vendor_name' => $extracted['vendor_name'] ?? null,
    'invoice_number' => $extracted['invoice_number'] ?? null,
    'invoice_date' => $extracted['invoice_date'] ?? null,
    'net_amount' => $extracted['net_amount'] ?? null,
    'vat_amount' => $extracted['vat_amount'] ?? null,
    'gross_amount' => $extracted['gross_amount'] ?? null,
    'vat_rate' => $extracted['vat_rate'] ?? null,
    'currency' => $extracted['currency'] ?? 'EUR',
    'ai_extracted_json' => json_encode($extracted),
    'ai_confidence' => $extracted ? 0.85 : 0,
    'status' => $extracted ? 'extracted' : 'pending',
    'uploaded_by' => $userId,
]);

finish(true, null, [
    'receipt_id' => $receiptId,
    'extracted' => $extracted,
    'file_path' => $stored['path'],
    'original_name' => $stored['original_name'],
]);
