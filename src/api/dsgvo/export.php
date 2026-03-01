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

// Art. 15 - Auskunft
$data = $service->getClientDataExport($clientId, $AUTH->data['instance']['instances_id']);

// Protokolliere Datenzugriff
$service->logDsgvoAction($AUTH->data['instance']['instances_id'], $clientId, 'data_export', $AUTH->data['users_userid']);

finish(true, null, $data);
