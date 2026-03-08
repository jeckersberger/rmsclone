<?php
/**
 * Kassenbuch: CSV-Export fuer Steuerberater
 * GET: year, month
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_STATS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$year = (int)($_REQUEST['year'] ?? date('Y'));
$month = (int)($_REQUEST['month'] ?? date('m'));

if ($month < 1 || $month > 12) finish(false, ["message" => "Ungueltiger Monat."]);
if ($year < 2000 || $year > 2100) finish(false, ["message" => "Ungueltiges Jahr."]);

require_once __DIR__ . '/../../services/KassenbuchService.php';
$service = new KassenbuchService($DBLIB);

$result = $service->exportCsv($instanceId, $year, $month);

// UTF-8 BOM fuer Excel-Kompatibilitaet
$bom = "\xEF\xBB\xBF";

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
echo $bom . $result['csv'];
exit;
