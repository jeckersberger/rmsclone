<?php
/**
 * Cron-Job: Wiederkehrende Projekte automatisch erstellen
 *
 * Aufruf: php src/cron/recurring-projects.php
 * Empfohlener Crontab-Eintrag: 0 6 * * * php /path/to/src/cron/recurring-projects.php >> /var/log/myrms-cron.log 2>&1
 *
 * Geht alle aktiven Instances durch und erstellt Projekte aus
 * wiederkehrenden Vorlagen (daily, weekly, biweekly, monthly).
 */

// CLI only
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('CLI only');
}

require_once __DIR__ . '/../common/head.php';

echo "[" . date('Y-m-d H:i:s') . "] Recurring projects cron started\n";

$DBLIB->where('instances_deleted', 0);
$instances = $DBLIB->get('instances', null, ['instances_id', 'instances_name']);

$totalCreated = 0;
$svc = new RecurringProjectService($DBLIB);

foreach ($instances as $inst) {
    $created = $svc->generateUpcoming($inst['instances_id']);
    if (!empty($created)) {
        foreach ($created as $p) {
            echo "  [+] {$inst['instances_name']}: {$p['name']} (ID: {$p['projects_id']})\n";
        }
        $totalCreated += count($created);
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Done. Created {$totalCreated} projects.\n";
