<?php
/**
 * Kontoauszug hochladen und importieren.
 *
 * POST multipart/form-data:
 *   file       - Die Kontoauszugsdatei (MT940, CAMT.053 XML, oder CSV)
 *   format     - 'mt940', 'camt053', oder 'csv'
 *   account_id - (optional) Bank-Konto ID
 *   csv_mapping - (optional, nur CSV) JSON-String mit Spalten-Mapping
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:CREATE")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$userId = (int)$AUTH->data['users_userid'];
$format = trim($_POST['format'] ?? '');
$accountId = !empty($_POST['account_id']) ? (int)$_POST['account_id'] : null;

if (!in_array($format, ['mt940', 'camt053', 'csv'])) {
    finish(false, ["message" => "Ungueltiges Format. Erlaubt: mt940, camt053, csv"]);
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    finish(false, ["message" => "Keine Datei hochgeladen oder Upload-Fehler."]);
}

$file = $_FILES['file'];
$maxSize = 10 * 1024 * 1024; // 10 MB
if ($file['size'] > $maxSize) {
    finish(false, ["message" => "Datei zu gross (max. 10 MB)."]);
}

// CSV-Mapping parsen
$csvMapping = [];
if ($format === 'csv' && !empty($_POST['csv_mapping'])) {
    $csvMapping = json_decode($_POST['csv_mapping'], true);
    if (!is_array($csvMapping)) {
        finish(false, ["message" => "Ungueltiges CSV-Mapping (kein gueltiges JSON)."]);
    }
}

// Bank-Konto validieren falls angegeben
if ($accountId) {
    $DBLIB->where('id', $accountId);
    $DBLIB->where('instances_id', $instanceId);
    $DBLIB->where('deleted', 0);
    if (!$DBLIB->getOne('bank_accounts')) {
        finish(false, ["message" => "Bankkonto nicht gefunden."]);
    }
}

require_once __DIR__ . '/../../services/DocumentLifecycleService.php';
require_once __DIR__ . '/../../services/BankImportService.php';

$service = new BankImportService($DBLIB);

try {
    $result = $service->importFile(
        $instanceId,
        $file['tmp_name'],
        $file['name'],
        $format,
        $userId,
        $accountId,
        $csvMapping
    );

    finish(true, null, $result);
} catch (\Exception $e) {
    finish(false, ["message" => "Import fehlgeschlagen: " . $e->getMessage()]);
}
