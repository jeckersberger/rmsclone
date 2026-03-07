<?php
/**
 * Cron-Job: Abgelaufene Angebote automatisch als 'expired' markieren
 *
 * Aufruf: php src/cron/quote-expiry-check.php
 * Empfohlener Crontab-Eintrag: 0 6 * * * php /path/to/src/cron/quote-expiry-check.php >> /var/log/adamrms-cron.log 2>&1
 *
 * Prueft alle Angebote (quotes) die:
 *   - Status 'sent' haben
 *   - valid_until in der Vergangenheit liegt
 * und setzt deren Status auf 'expired'.
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('CLI only');
}

require_once __DIR__ . '/../common/head.php';
require_once __DIR__ . '/../services/DocumentLifecycleService.php';

echo "[" . date('Y-m-d H:i:s') . "] Quote expiry check started\n";

$DBLIB->where('instances_deleted', 0);
$instances = $DBLIB->get('instances', null, ['instances_id', 'instances_name']);

$totalExpired = 0;
$today = date('Y-m-d');

foreach ($instances as $inst) {
    $instanceId = $inst['instances_id'];
    $startTime = microtime(true);

    // Find quotes past valid_until that are still in 'sent' status
    $DBLIB->where('instances_id', $instanceId);
    $DBLIB->where('doc_type', 'quote');
    $DBLIB->where('status', 'sent');
    $DBLIB->where('valid_until', $today, '<');
    $DBLIB->where('valid_until IS NOT NULL');
    $expiredQuotes = $DBLIB->get('document_lifecycle') ?: [];

    $expiredCount = 0;
    $lifecycle = new DocumentLifecycleService($DBLIB);

    foreach ($expiredQuotes as $quote) {
        $changed = $lifecycle->changeStatus(
            (int)$quote['id'],
            'expired',
            0, // System user
            'Automatisch als abgelaufen markiert (Gueltig bis: ' . date('d.m.Y', strtotime($quote['valid_until'])) . ')',
            $instanceId
        );
        if ($changed) {
            $expiredCount++;
            echo "  [!] {$inst['instances_name']}: Angebot {$quote['doc_number']} abgelaufen (gueltig bis " . date('d.m.Y', strtotime($quote['valid_until'])) . ")\n";
        }
    }
    $totalExpired += $expiredCount;

    // Log cron run
    $elapsed = round(microtime(true) - $startTime, 3);
    $DBLIB->insert('cron_runs', [
        'instances_id' => $instanceId,
        'job_type' => 'quote_expiry_check',
        'status' => 'success',
        'items_processed' => $expiredCount,
        'details_json' => json_encode([
            'expired_quotes' => $expiredCount,
            'elapsed_seconds' => $elapsed,
        ]),
        'completed_at' => date('Y-m-d H:i:s'),
    ]);
}

echo "[" . date('Y-m-d H:i:s') . "] Done. Marked {$totalExpired} quotes as expired.\n";
