<?php
/**
 * Kontoauszug hochladen und importieren.
 *
 * POST multipart/form-data:
 *   file   - Die Kontoauszugsdatei (MT940, CAMT.053 XML, oder CSV)
 *   format - 'mt940', 'camt053', oder 'csv'
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:CREATE")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$format = trim($_POST['format'] ?? '');

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

$fileContent = file_get_contents($file['tmp_name']);
if ($fileContent === false) {
    finish(false, ["message" => "Datei konnte nicht gelesen werden."]);
}

require_once __DIR__ . '/../../services/BankImportService.php';
$service = new BankImportService($DBLIB);

try {
    switch ($format) {
        case 'mt940':
            $result = $service->importMt940($instanceId, $fileContent);
            break;
        case 'camt053':
            $result = $service->importCamt053($instanceId, $fileContent);
            break;
        case 'csv':
            $result = $service->importCsv($instanceId, $fileContent);
            break;
    }

    finish(true, null, $result);
} catch (\Exception $e) {
    finish(false, ["message" => "Import fehlgeschlagen: " . $e->getMessage()]);
}
