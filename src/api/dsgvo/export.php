<?php
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/DsgvoService.php';

if (!$AUTH->instancePermissionCheck("CLIENTS:VIEW")) finish(false, ["message" => "Permission denied"]);

$clientId = intval($_POST['client_id'] ?? 0);
if (!$clientId) finish(false, ["message" => "client_id required"]);

$service = new DsgvoService($DBLIB);

$format = $_POST['format'] ?? 'json';

if ($format === 'download') {
    // Art. 20 - Maschinenlesbarer Export
    $json = $service->exportClientDataJson($clientId, $AUTH->data['instance']['instances_id']);
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="dsgvo_export_client_' . $clientId . '_' . date('Y-m-d') . '.json"');
    die($json);
}

if ($format === 'encrypted') {
    // Verschluesselter Export als passwortgeschuetztes ZIP
    $json = $service->exportClientDataJson($clientId, $AUTH->data['instance']['instances_id']);
    $password = $_POST['export_password'] ?? '';
    if (mb_strlen($password) < 8) {
        finish(false, ["message" => "Export-Passwort muss mindestens 8 Zeichen lang sein"]);
    }

    $tmpDir = sys_get_temp_dir() . '/dsgvo_exports';
    if (!is_dir($tmpDir)) mkdir($tmpDir, 0700, true);

    $jsonFile = $tmpDir . '/export_' . $clientId . '_' . bin2hex(random_bytes(8)) . '.json';
    $zipFile = $tmpDir . '/dsgvo_export_' . $clientId . '_' . date('Y-m-d') . '_' . bin2hex(random_bytes(4)) . '.zip';

    file_put_contents($jsonFile, $json);

    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::CREATE) === true) {
        $zip->setPassword($password);
        $zip->addFile($jsonFile, 'dsgvo_export_client_' . $clientId . '.json');
        $zip->setEncryptionName('dsgvo_export_client_' . $clientId . '.json', ZipArchive::EM_AES_256);
        $zip->close();
    }

    // JSON-Quelldatei sofort loeschen
    unlink($jsonFile);

    $service->logDsgvoAction($AUTH->data['instance']['instances_id'], $clientId, 'encrypted_export', $AUTH->data['users_userid']);

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="dsgvo_export_client_' . $clientId . '_' . date('Y-m-d') . '.zip"');
    header('Content-Length: ' . filesize($zipFile));
    readfile($zipFile);

    // Temp-ZIP nach Download loeschen
    unlink($zipFile);
    exit;
}

// Art. 15 - Auskunft
$data = $service->getClientDataExport($clientId, $AUTH->data['instance']['instances_id']);

// Protokolliere Datenzugriff
$service->logDsgvoAction($AUTH->data['instance']['instances_id'], $clientId, 'data_export', $AUTH->data['users_userid']);

finish(true, null, $data);
