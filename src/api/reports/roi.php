<?php
/**
 * Equipment ROI API
 *
 * POST-Parameter:
 *   action        - 'single' | 'all' (Standard: all)
 *   asset_type_id - Asset-Typ-ID (nur bei single)
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

$instanceId = $AUTH->data['instance']['instances_id'];
$action = $_POST['action'] ?? 'all';

$svc = new UtilizationReportService($DBLIB);

switch ($action) {
    case 'single':
        $assetTypeId = (int)($_POST['asset_type_id'] ?? 0);
        if ($assetTypeId <= 0) finish(false, ["code" => "MISSING_ASSET_TYPE_ID"]);
        $report = $svc->calculateRoi($assetTypeId);
        finish(true, null, ["report" => $report]);
        break;

    case 'all':
        // Alle Asset-Typen der Instanz
        $sql = "SELECT DISTINCT at.assetTypes_id
                FROM assetTypes at
                JOIN assets a ON a.assetTypes_id = at.assetTypes_id AND a.assets_deleted = 0
                WHERE a.instances_id = ?
                ORDER BY at.assetTypes_name";
        $types = $DBLIB->rawQuery($sql, [$instanceId]) ?: [];

        $report = [];
        foreach ($types as $t) {
            $roi = $svc->calculateRoi((int)$t['assetTypes_id']);
            if (!isset($roi['error'])) {
                $report[] = $roi;
            }
        }

        // Sortiere nach ROI absteigend
        usort($report, fn($a, $b) => ($b['roi_pct'] ?? 0) <=> ($a['roi_pct'] ?? 0));
        finish(true, null, ["report" => $report]);
        break;

    default:
        finish(false, ["code" => "INVALID_ACTION"]);
}
