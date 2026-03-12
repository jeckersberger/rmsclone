<?php
/**
 * Predictive Maintenance: Analysiert Nutzungshistorie und sagt
 * Wartungsbedarf und potenzielle Ausfaelle voraus.
 */
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("ASSETS:VIEW")) finish(false, ["code" => "PERMISSIONS"]);

require_once __DIR__ . '/../../services/ClaudeService.php';

$instanceId = (int)$AUTH->data['instance']['instances_id'];
$claude = new ClaudeService($DBLIB, $instanceId);
if (!$claude->isFeatureEnabled('predict_maintenance')) {
    finish(false, ["code" => "DISABLED", "message" => "Predictive Maintenance ist deaktiviert."]);
}

$assetTypeId = (int)($_POST['asset_type_id'] ?? 0);
$scope = trim($_POST['scope'] ?? 'type'); // 'type' = ganzer Typ, 'all' = alle Assets

$context = '';

if ($assetTypeId && $scope === 'type') {
    // Single asset type analysis
    $DBLIB->where('assetTypes_id', $assetTypeId);
    $type = $DBLIB->getOne('assetTypes');
    if (!$type) finish(false, ["code" => "NOT_FOUND"]);

    // Get assets of this type with maintenance history
    $sql = "SELECT a.assets_id, a.assets_tag, a.assets_purchaseDate, a.assets_lastMaintenanceDate,
                   at.assetTypes_maintenanceInterval,
                   (SELECT COUNT(*) FROM assetsAssignments aa WHERE aa.assets_id = a.assets_id AND aa.assetsAssignments_deleted = 0) as rental_count,
                   (SELECT MAX(p.projects_dateEnd) FROM assetsAssignments aa JOIN projects p ON aa.projects_id = p.projects_id
                    WHERE aa.assets_id = a.assets_id AND aa.assetsAssignments_deleted = 0) as last_rental_end
            FROM assets a
            JOIN assetTypes at ON a.assetTypes_id = at.assetTypes_id
            WHERE a.assetTypes_id = ? AND a.assets_deleted = 0
            ORDER BY a.assets_tag";
    $assets = $DBLIB->rawQuery($sql, [$assetTypeId]) ?: [];

    // Get maintenance log entries
    $sql = "SELECT ml.assets_id, ml.performed_at, ml.notes
            FROM maintenance_log ml
            JOIN assets a ON ml.assets_id = a.assets_id
            WHERE a.assetTypes_id = ?
            ORDER BY ml.performed_at DESC LIMIT 50";
    $logs = $DBLIB->rawQuery($sql, [$assetTypeId]) ?: [];

    // Get damage reports / maintenance jobs
    $sql = "SELECT mj.maintenanceJobs_id, mj.maintenanceJobs_assets as maintenance_asset,
                   mj.maintenanceJobs_faultDescription as maintenance_notes,
                   mj.maintenanceJobs_timestamp_added as maintenance_flagDate,
                   mjs.maintenanceJobsStatuses_name as maintenance_status
            FROM maintenanceJobs mj
            LEFT JOIN maintenanceJobsStatuses mjs ON mj.maintenanceJobsStatuses_id = mjs.maintenanceJobsStatuses_id
            WHERE mj.maintenanceJobs_assets LIKE CONCAT('%', ?, '%') AND mj.instances_id = ?
            AND mj.maintenanceJobs_deleted = 0
            ORDER BY mj.maintenanceJobs_timestamp_added DESC LIMIT 20";
    $damages = $DBLIB->rawQuery($sql, [$assetTypeId, $instanceId]) ?: [];

    $context = "Equipment-Typ: {$type['assetTypes_name']}\n";
    $context .= "Wartungsintervall: " . ($type['assetTypes_maintenanceInterval'] ?: 'nicht definiert') . " Tage\n";
    $context .= "Anzahl Geraete: " . count($assets) . "\n\n";

    $context .= "Geraete-Details:\n";
    foreach ($assets as $a) {
        $context .= "- {$a['assets_tag']}: Kauf {$a['assets_purchaseDate']}, letzte Wartung {$a['assets_lastMaintenanceDate']}, Vermietungen: {$a['rental_count']}, letzter Einsatz: {$a['last_rental_end']}\n";
    }

    $context .= "\nWartungsprotokoll:\n";
    foreach ($logs as $l) {
        $context .= "- Asset {$l['assets_id']} am {$l['performed_at']}: {$l['notes']}\n";
    }

    if (!empty($damages)) {
        $context .= "\nSchadensberichte:\n";
        foreach ($damages as $d) {
            $context .= "- Asset {$d['maintenance_asset']} ({$d['maintenance_flagDate']}): {$d['maintenance_notes']} (Status: {$d['maintenance_status']})\n";
        }
    }
} else {
    // Overview: All types with maintenance needs
    $sql = "SELECT at.assetTypes_id, at.assetTypes_name, at.assetTypes_maintenanceInterval,
                   COUNT(a.assets_id) as asset_count,
                   SUM(CASE WHEN a.assets_lastMaintenanceDate IS NOT NULL
                       AND DATEDIFF(NOW(), a.assets_lastMaintenanceDate) > COALESCE(at.assetTypes_maintenanceInterval, 365)
                       THEN 1 ELSE 0 END) as overdue_count,
                   AVG(DATEDIFF(NOW(), COALESCE(a.assets_lastMaintenanceDate, a.assets_purchaseDate))) as avg_days_since_maintenance
            FROM assetTypes at
            JOIN assets a ON at.assetTypes_id = a.assetTypes_id AND a.assets_deleted = 0
            WHERE at.instances_id = ?
            GROUP BY at.assetTypes_id
            HAVING asset_count > 0
            ORDER BY overdue_count DESC, avg_days_since_maintenance DESC";
    $types = $DBLIB->rawQuery($sql, [$instanceId]) ?: [];

    $context = "Wartungsuebersicht aller Equipment-Typen:\n";
    foreach ($types as $t) {
        $context .= "- {$t['assetTypes_name']}: {$t['asset_count']} Geraete, Intervall: " .
            ($t['assetTypes_maintenanceInterval'] ?: '?') . " Tage, ueberfaellig: {$t['overdue_count']}, " .
            "Durchschnitt seit letzter Wartung: " . round($t['avg_days_since_maintenance']) . " Tage\n";
    }
}

$systemPrompt = <<<'PROMPT'
Du bist ein Wartungs-Experte fuer ein Equipment-Verleihunternehmen.
Analysiere die Daten und erstelle eine Wartungsprognose.

Gib die Antwort als JSON zurueck:
{
  "risk_assets": [
    {"asset_tag": "ID", "risk": "hoch|mittel|niedrig", "reason": "Begruendung", "recommended_action": "Empfohlene Massnahme", "urgency_days": 0}
  ],
  "patterns": ["Erkanntes Muster 1"],
  "recommendations": [
    {"action": "Empfehlung", "priority": "hoch|mittel|niedrig", "estimated_effort": "Aufwand"}
  ],
  "maintenance_schedule": [
    {"asset_tag": "ID", "next_maintenance": "YYYY-MM-DD", "type": "Wartungsart"}
  ],
  "summary": "Zusammenfassung in 2-3 Saetzen"
}
Antworte NUR mit JSON.
PROMPT;

$response = $claude->ask('predict_maintenance', $systemPrompt, $context);
$text = ClaudeService::extractText($response);

$result = json_decode($text, true);
if (!$result && preg_match('/\{[\s\S]*\}/', $text, $m)) {
    $result = json_decode($m[0], true);
}

finish(true, null, [
    'scope' => $scope,
    'prediction' => $result ?: ['summary' => $text],
]);
