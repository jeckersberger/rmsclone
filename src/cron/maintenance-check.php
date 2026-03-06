<?php
/**
 * Cron-Job: Equipment-Wartungsintervalle pruefen
 *
 * Aufruf: php src/cron/maintenance-check.php
 * Empfohlener Crontab-Eintrag: 0 6 * * * php /path/to/src/cron/maintenance-check.php >> /var/log/adamrms-cron.log 2>&1
 *
 * Funktionen:
 *   1. Faellige Wartungen identifizieren
 *   2. Interne Benachrichtigungen an Verantwortliche senden
 *   3. In 7 Tagen faellige Wartungen als Vorwarnung
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('CLI only');
}

require_once __DIR__ . '/../common/head.php';
require_once __DIR__ . '/../services/MaintenanceScheduleService.php';
require_once __DIR__ . '/../api/notifications/email/email.php';

echo "[" . date('Y-m-d H:i:s') . "] Maintenance check started\n";

$DBLIB->where('instances_deleted', 0);
$instances = $DBLIB->get('instances', null, ['instances_id', 'instances_name']);

$totalNotified = 0;

foreach ($instances as $inst) {
    $instanceId = (int)$inst['instances_id'];
    $maintenance = new MaintenanceScheduleService($DBLIB);

    // 1. Ueberfaellige Wartungen
    $dueItems = $maintenance->getDueMaintenance($instanceId);
    // 2. In 7 Tagen faellig
    $upcomingItems = $maintenance->getUpcomingMaintenance($instanceId, 7);

    if (empty($dueItems) && empty($upcomingItems)) continue;

    echo "  [*] {$inst['instances_name']}: {" . count($dueItems) . "} faellig, " . count($upcomingItems) . " bald faellig\n";

    // Admin-Benutzer finden fuer Benachrichtigung
    $DBLIB->where('instances_id', $instanceId);
    $DBLIB->where('instancePositions_deleted', 0);
    $DBLIB->where("instancePositions_permissions LIKE '%ASSETS:EDIT%'");
    $DBLIB->join('users', 'instancePositions.users_userid=users.users_userid', 'INNER');
    $admins = $DBLIB->get('instancePositions', null, [
        'users.users_email', 'users.users_name1', 'users.users_name2'
    ]) ?: [];

    if (empty($admins)) continue;

    // Zusammenfassungs-E-Mail erstellen
    $html = "<h2>Wartungs-Bericht - " . date('d.m.Y') . "</h2>";

    if (!empty($dueItems)) {
        $html .= "<h3 style='color:red;'>Ueberfaellige Wartungen (" . count($dueItems) . ")</h3><table border='1' cellpadding='8' style='border-collapse:collapse;'>";
        $html .= "<tr style='background:#dc3545;color:#fff;'><th>Equipment</th><th>Tag</th><th>Tage ueberfaellig</th><th>Intervall</th></tr>";
        foreach ($dueItems as $item) {
            $overdueDays = (int)$item['days_since_maintenance'] - (int)$item['assetTypes_maintenanceInterval'];
            $html .= "<tr><td>{$item['assetTypes_name']}</td><td>{$item['assets_tag']}</td><td>{$overdueDays} Tage</td><td>alle {$item['assetTypes_maintenanceInterval']} Tage</td></tr>";
        }
        $html .= "</table>";
    }

    if (!empty($upcomingItems)) {
        $html .= "<h3 style='color:orange;'>Bald faellig (" . count($upcomingItems) . ")</h3><table border='1' cellpadding='8' style='border-collapse:collapse;'>";
        $html .= "<tr style='background:#ffc107;'><th>Equipment</th><th>Tag</th><th>Tage bis Wartung</th></tr>";
        foreach ($upcomingItems as $item) {
            $html .= "<tr><td>{$item['assetTypes_name']}</td><td>{$item['assets_tag']}</td><td>{$item['days_until_due']} Tage</td></tr>";
        }
        $html .= "</table>";
    }

    // An alle Admins senden
    foreach ($admins as $admin) {
        if (empty($admin['users_email'])) continue;
        @sendEmail(
            ['userData' => $admin],
            $instanceId,
            "Wartungs-Bericht: " . count($dueItems) . " faellig, " . count($upcomingItems) . " bald faellig",
            $html
        );
        $totalNotified++;
        echo "    [>] Benachrichtigt: {$admin['users_email']}\n";
    }

    // Cron-Run loggen
    $DBLIB->insert('cron_runs', [
        'instances_id' => $instanceId,
        'job_type' => 'maintenance_check',
        'status' => 'success',
        'items_processed' => count($dueItems) + count($upcomingItems),
        'details_json' => json_encode([
            'overdue' => count($dueItems),
            'upcoming' => count($upcomingItems),
            'notified_admins' => count($admins),
        ]),
        'completed_at' => date('Y-m-d H:i:s'),
    ]);
}

echo "[" . date('Y-m-d H:i:s') . "] Done. Notified {$totalNotified} admins.\n";
