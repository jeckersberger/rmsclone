<?php
/**
 * Cron-Job: Ueberfaellige Rechnungen automatisch markieren und Mahnungen vorschlagen
 *
 * Aufruf: php src/cron/overdue-invoices.php
 * Empfohlener Crontab-Eintrag: 0 7 * * * php /path/to/src/cron/overdue-invoices.php >> /var/log/myrms-cron.log 2>&1
 *
 * Funktionen:
 *   1. Rechnungen mit ueberschrittenem Faelligkeitsdatum auf 'overdue' setzen
 *   2. Automatische Zahlungserinnerungen senden (wenn konfiguriert)
 *   3. Cron-Run protokollieren
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('CLI only');
}

require_once __DIR__ . '/../common/head.php';

echo "[" . date('Y-m-d H:i:s') . "] Overdue invoice check started\n";

$DBLIB->where('instances_deleted', 0);
$instances = $DBLIB->get('instances', null, ['instances_id', 'instances_name']);

$totalMarked = 0;
$totalReminders = 0;
$today = date('Y-m-d');

foreach ($instances as $inst) {
    $instanceId = $inst['instances_id'];
    $startTime = microtime(true);

    // 1. Mark overdue invoices
    $DBLIB->where('instances_id', $instanceId);
    $DBLIB->where('doc_type', 'invoice');
    $DBLIB->where('status', 'sent');
    $DBLIB->where('due_date', $today, '<');
    $DBLIB->where('due_date IS NOT NULL');
    $overdueInvoices = $DBLIB->get('document_lifecycle') ?: [];

    $markedCount = 0;
    $lifecycle = new DocumentLifecycleService($DBLIB);
    foreach ($overdueInvoices as $inv) {
        $lifecycle->changeStatus(
            (int)$inv['id'],
            'overdue',
            0, // System user
            'Automatisch als ueberfaellig markiert (Faelligkeit: ' . date('d.m.Y', strtotime($inv['due_date'])) . ')'
        );
        $markedCount++;
        echo "  [!] {$inst['instances_name']}: {$inv['doc_number']} ueberfaellig (seit " . date('d.m.Y', strtotime($inv['due_date'])) . ")\n";
    }
    $totalMarked += $markedCount;

    // 2. Check for pending reminder emails (dunning level 0 = Zahlungserinnerung)
    $dunning = new DunningService($DBLIB);
    $overdueAll = $dunning->getOverdueInvoices($instanceId);
    $reminderCount = 0;

    foreach ($overdueAll as $inv) {
        // Only auto-send if next level is available and days threshold met
        if (!$inv['next_dunning_level']) continue;
        if ((int)$inv['next_dunning_level']['level'] !== 0) continue; // Only auto-send Zahlungserinnerung

        $daysOverdue = $inv['days_overdue'];
        if ($daysOverdue >= 7 && $daysOverdue <= 14 && !$inv['last_dunning']) {
            // Auto-create dunning level 0 entry (Zahlungserinnerung)
            $result = $dunning->createDunning($instanceId, (int)$inv['id'], 0);
            if ($result) {
                $reminderCount++;
                echo "  [*] {$inst['instances_name']}: Zahlungserinnerung fuer {$inv['doc_number']} erstellt\n";
            }
        }
    }
    $totalReminders += $reminderCount;

    // 3. Log cron run
    $elapsed = round(microtime(true) - $startTime, 3);
    $DBLIB->insert('cron_runs', [
        'instances_id' => $instanceId,
        'job_type' => 'overdue_check',
        'status' => 'success',
        'items_processed' => $markedCount + $reminderCount,
        'details_json' => json_encode([
            'marked_overdue' => $markedCount,
            'reminders_created' => $reminderCount,
            'elapsed_seconds' => $elapsed,
        ]),
        'completed_at' => date('Y-m-d H:i:s'),
    ]);
}

echo "[" . date('Y-m-d H:i:s') . "] Done. Marked {$totalMarked} overdue, created {$totalReminders} reminders.\n";
