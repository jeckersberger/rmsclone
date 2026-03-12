<?php
/**
 * Kunden-Import: Import mit Spalten-Mapping ausfuehren
 *
 * Erwartet: POST mit mapping (JSON) und skip_duplicates (0|1)
 * Gibt zurueck: Import-Bericht (imported, skipped, errors)
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/ClientImportService.php';

if (!$AUTH->instancePermissionCheck("CLIENTS:CREATE")) die("404");

// Pruefen ob eine Datei in der Session vorliegt
if (empty($_SESSION['client_import_file']) || !file_exists($_SESSION['client_import_file'])) {
    finish(false, ["code" => "NO-FILE", "message" => "Keine Datei zum Import vorhanden. Bitte zuerst eine Datei hochladen."]);
}

// Mapping auslesen
$mappingJson = $_POST['mapping'] ?? '{}';
$mapping = json_decode($mappingJson, true);
if (empty($mapping) || !is_array($mapping)) {
    finish(false, ["code" => "MAPPING-ERROR", "message" => "Kein gueltiges Spalten-Mapping angegeben"]);
}

$skipDuplicates = (bool)($_POST['skip_duplicates'] ?? 1);

$importService = new ClientImportService($DBLIB);
$instanceId = $AUTH->data['instance']['instances_id'];
$userId = $AUTH->data['users_userid'];

// Datei erneut parsen
$content = file_get_contents($_SESSION['client_import_file']);
$delimiter = $_SESSION['client_import_delimiter'] ?? ';';
$filename = $_SESSION['client_import_filename'] ?? 'unbekannt.csv';

$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
if (in_array($extension, ['xls', 'xlsx'])) {
    $parsed = $importService->parseExcel($content);
} else {
    $parsed = $importService->parseCsv($content, $delimiter);
}

if (empty($parsed['rows'])) {
    finish(false, ["code" => "NO-DATA", "message" => "Keine Daten zum Import gefunden"]);
}

// Import durchfuehren
$result = $importService->importClients(
    $instanceId,
    $parsed['rows'],
    $mapping,
    $userId,
    $skipDuplicates
);

// Dateinamen im Log aktualisieren
if (!empty($result['log_id'])) {
    $importService->updateLogFilename($result['log_id'], $filename);
}

// Temporaere Datei aufraeumen
@unlink($_SESSION['client_import_file']);
unset($_SESSION['client_import_file']);
unset($_SESSION['client_import_filename']);
unset($_SESSION['client_import_delimiter']);

$bCMS->auditLog("IMPORT", "clients", json_encode([
    'filename' => $filename,
    'imported' => $result['imported'],
    'skipped'  => $result['skipped'],
]), $userId);

finish(true, null, $result);
