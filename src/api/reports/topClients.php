<?php
/**
 * Top-Kunden Ranking API
 *
 * POST-Parameter:
 *   action    - 'ranking' | 'trend' (Standard: ranking)
 *   year      - Jahr (Standard: aktuelles Jahr)
 *   client_id - Kunden-ID (nur bei trend)
 *   months    - Anzahl Monate (nur bei trend, Standard: 12)
 *   limit     - Max. Anzahl Kunden (nur bei ranking, Standard: 10)
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:PROJECT_PAYMENTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$action = $_POST['action'] ?? 'ranking';
$year = (int)($_POST['year'] ?? date('Y'));

$svc = new ProfitCalculationService($DBLIB);

switch ($action) {
    case 'ranking':
        $limit = (int)($_POST['limit'] ?? 10);
        $report = $svc->getTopClients($instanceId, $year, $limit);
        finish(true, null, ["report" => $report, "year" => $year]);
        break;

    case 'trend':
        $clientId = (int)($_POST['client_id'] ?? 0);
        if ($clientId <= 0) finish(false, ["code" => "MISSING_CLIENT_ID"]);
        $months = (int)($_POST['months'] ?? 12);
        $report = $svc->getClientRevenueTrend($instanceId, $clientId, $months);
        finish(true, null, ["report" => $report]);
        break;

    default:
        finish(false, ["code" => "INVALID_ACTION"]);
}
