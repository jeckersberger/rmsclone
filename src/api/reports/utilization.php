<?php
/**
 * Equipment-Auslastungsbericht API
 *
 * POST-Parameter:
 *   action    - 'overview' | 'top_performers' | 'underutilized' (Standard: overview)
 *   year      - Jahr (Standard: aktuelles Jahr)
 *   month     - Monat 1-12 (nur bei overview, Standard: aktueller Monat)
 *   threshold - Schwellenwert % (nur bei underutilized, Standard: 30)
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$action = $_POST['action'] ?? 'overview';
$year = (int)($_POST['year'] ?? date('Y'));
$month = (int)($_POST['month'] ?? date('n'));

$svc = new UtilizationReportService($DBLIB);

switch ($action) {
    case 'overview':
        $report = $svc->getUtilizationOverview($instanceId, $year, $month);
        finish(true, null, ["report" => $report, "year" => $year, "month" => $month]);
        break;

    case 'top_performers':
        $report = $svc->getTopPerformers($instanceId, $year);
        finish(true, null, ["report" => $report, "year" => $year]);
        break;

    case 'underutilized':
        $threshold = (int)($_POST['threshold'] ?? 30);
        $report = $svc->getUnderutilized($instanceId, $year, $threshold);
        finish(true, null, ["report" => $report, "year" => $year, "threshold" => $threshold]);
        break;

    default:
        finish(false, ["code" => "INVALID_ACTION"]);
}
