<?php
/**
 * Kunden-Import: CSV hochladen und Vorschau anzeigen
 *
 * Erwartet: POST mit Datei-Upload (file) und optionalem delimiter
 * Gibt zurueck: Erste 5 Zeilen + Header zur Vorschau und Spalten-Mapping
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/ClientImportService.php';

if (!$AUTH->instancePermissionCheck("CLIENTS:CREATE")) die("404");

if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    finish(false, ["code" => "UPLOAD-ERROR", "message" => "Keine Datei hochgeladen oder Fehler beim Upload"]);
}

$file = $_FILES['file'];
$filename = basename($file['name']);
$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

if (!in_array($extension, ['csv', 'txt', 'xls', 'xlsx'])) {
    finish(false, ["code" => "FORMAT-ERROR", "message" => "Unterstuetztes Format: CSV, TXT, XLS, XLSX"]);
}

$content = file_get_contents($file['tmp_name']);
if ($content === false || strlen($content) === 0) {
    finish(false, ["code" => "READ-ERROR", "message" => "Datei konnte nicht gelesen werden"]);
}

$importService = new ClientImportService($DBLIB);
$delimiter = $_POST['delimiter'] ?? ';';

if (in_array($extension, ['xls', 'xlsx'])) {
    $parsed = $importService->parseExcel($content);
} else {
    $parsed = $importService->parseCsv($content, $delimiter);
}

if (empty($parsed['headers'])) {
    finish(false, ["code" => "PARSE-ERROR", "message" => "Keine Daten in der Datei gefunden"]);
}

// Vorschau: Erste 5 Zeilen
$previewRows = array_slice($parsed['rows'], 0, 5);

// Verfuegbare Zielfelder
$targetFields = ClientImportService::FIELD_MAP;

// Auto-Mapping versuchen (Header-Namen vergleichen)
$autoMapping = [];
foreach ($parsed['headers'] as $index => $header) {
    $headerNorm = strtolower(trim($header));
    foreach ($targetFields as $label => $dbField) {
        $labelNorm = strtolower($label);
        if ($headerNorm === $labelNorm || strpos($headerNorm, $labelNorm) !== false || strpos($labelNorm, $headerNorm) !== false) {
            $autoMapping[$index] = $dbField;
            break;
        }
    }
}

// Datei temporaer speichern fuer den Import
$tmpPath = sys_get_temp_dir() . '/client_import_' . session_id() . '_' . time() . '.csv';
move_uploaded_file($file['tmp_name'], $tmpPath);
$_SESSION['client_import_file'] = $tmpPath;
$_SESSION['client_import_filename'] = $filename;
$_SESSION['client_import_delimiter'] = $delimiter;

finish(true, null, [
    'filename'     => $filename,
    'headers'      => $parsed['headers'],
    'preview'      => $previewRows,
    'total_rows'   => count($parsed['rows']),
    'target_fields' => $targetFields,
    'auto_mapping' => $autoMapping,
]);
