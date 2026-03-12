<?php
/**
 * Steuer-CSV-Export (fuer WISO und eigene Steuererklaerung)
 * Einfacher CSV-Download: Einnahmen, Ausgaben, Kunden, EUER-Zusammenfassung
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("DATEV:EXPORT") && !$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_SETTINGS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$exportType = $_REQUEST['type'] ?? 'einnahmen';
$from = $_REQUEST['from'] ?? date('Y-01-01');
$to = $_REQUEST['to'] ?? date('Y-m-d');
$year = (int)($_REQUEST['year'] ?? date('Y'));

$svc = new SteuerExportService($DBLIB);

switch ($exportType) {
    case 'ausgaben':
        $result = $svc->exportAusgaben($instanceId, $from, $to);
        break;
    case 'kunden':
        $result = $svc->exportKunden($instanceId);
        break;
    case 'euer':
        $result = $svc->exportEuerZusammenfassung($instanceId, $year);
        break;
    case 'einnahmen':
    default:
        $result = $svc->exportEinnahmen($instanceId, $from, $to);
        break;
}

// UTF-8 BOM fuer Excel/WISO Kompatibilitaet
$bom = "\xEF\xBB\xBF";

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
echo $bom . $result['csv'];
exit;
