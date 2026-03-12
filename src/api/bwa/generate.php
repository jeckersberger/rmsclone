<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("BUSINESS:BUSINESS_STATS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$year = (int)($_POST['year'] ?? date('Y'));
$month = isset($_POST['month']) && $_POST['month'] !== '' ? (int)$_POST['month'] : null;
$mode = $_POST['mode'] ?? 'monthly'; // monthly, annual, compare

$svc = new BwaService($DBLIB);

switch ($mode) {
    case 'annual':
        $report = $svc->generateBwaReport($instanceId, $year);
        finish(true, null, $report);
        break;

    case 'compare':
        $year2 = (int)($_POST['year2'] ?? $year);
        $month2 = (int)($_POST['month2'] ?? $month);
        $year1 = (int)($_POST['year1'] ?? ($year - 1));
        $month1 = (int)($_POST['month1'] ?? $month);

        if ($month1 < 1 || $month1 > 12 || $month2 < 1 || $month2 > 12) {
            finish(false, ["error" => "Ungueltige Monatsangabe"]);
        }

        $report = $svc->comparePeriods($instanceId, $year1, $month1, $year2, $month2);
        finish(true, null, $report);
        break;

    case 'monthly':
    default:
        if ($month === null) $month = (int)date('n');
        if ($month < 1 || $month > 12) {
            finish(false, ["error" => "Ungueltige Monatsangabe"]);
        }
        $bwa = $svc->generateBwa($instanceId, $year, $month);
        finish(true, null, $bwa);
        break;
}
