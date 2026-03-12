<?php
/**
 * Saisonalitaets-Analyse API
 *
 * POST-Parameter:
 *   action - 'overview' | 'peaks' (Standard: overview)
 *   years  - Anzahl Jahre (Standard: 3)
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$action = $_POST['action'] ?? 'overview';

$svc = new ProfitCalculationService($DBLIB);

switch ($action) {
    case 'overview':
        $years = (int)($_POST['years'] ?? 3);
        $report = $svc->getSeasonality($instanceId, $years);
        finish(true, null, ["report" => $report]);
        break;

    case 'peaks':
        $report = $svc->getPeakMonths($instanceId);
        finish(true, null, ["report" => $report]);
        break;

    default:
        finish(false, ["code" => "INVALID_ACTION"]);
}
