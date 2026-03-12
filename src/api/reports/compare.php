<?php
/**
 * Zeitraum-Vergleich API
 *
 * POST-Parameter:
 *   period1_start - Startdatum Zeitraum 1 (YYYY-MM-DD)
 *   period1_end   - Enddatum Zeitraum 1 (YYYY-MM-DD)
 *   period2_start - Startdatum Zeitraum 2 (YYYY-MM-DD)
 *   period2_end   - Enddatum Zeitraum 2 (YYYY-MM-DD)
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];

$period1Start = $_POST['period1_start'] ?? null;
$period1End = $_POST['period1_end'] ?? null;
$period2Start = $_POST['period2_start'] ?? null;
$period2End = $_POST['period2_end'] ?? null;

if (!$period1Start || !$period1End || !$period2Start || !$period2End) {
    finish(false, ["code" => "MISSING_PARAMETERS", "message" => "Alle vier Datumsparameter sind erforderlich."]);
}

$svc = new ProfitCalculationService($DBLIB);
$report = $svc->comparePeriods($instanceId, $period1Start, $period1End, $period2Start, $period2End);

finish(true, null, ["report" => $report]);
