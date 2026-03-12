<?php
/**
 * Cron-Job: Automatischer Rechnungsversand per E-Mail
 *
 * Aufruf: php src/cron/auto-invoice-email.php
 * Empfohlener Crontab-Eintrag: every 5 min - php /path/to/src/cron/auto-invoice-email.php >> /var/log/myrms-cron.log 2>&1
 *
 * Funktionen:
 *   1. Sucht Dokumente in document_lifecycle mit auto_send_email = 1 und status = 'draft'
 *   2. Versendet diese per E-Mail an den jeweiligen Kunden
 *   3. Aktualisiert den Status auf 'sent'
 *   4. Protokolliert den Versand in document_email_log
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('CLI only');
}

require_once __DIR__ . '/../common/head.php';
require_once __DIR__ . '/../services/DocumentLifecycleService.php';
require_once __DIR__ . '/../services/InvoiceEmailService.php';
require_once __DIR__ . '/../services/InvoiceMailService.php';

echo "[" . date('Y-m-d H:i:s') . "] Auto invoice email check started\n";

$DBLIB->where('instances_deleted', 0);
$DBLIB->where('auto_invoice_email_enabled', 1);
$instances = $DBLIB->get('instances', null, ['instances_id', 'instances_name']);

$totalSent = 0;
$totalFailed = 0;

foreach ($instances as $inst) {
    $instanceId = $inst['instances_id'];
    $startTime = microtime(true);

    $emailSvc = new InvoiceEmailService($DBLIB);
    $results = $emailSvc->processAutoSend($instanceId);

    $totalSent += $results['sent'];
    $totalFailed += $results['failed'];

    foreach ($results['details'] as $detail) {
        if ($detail['sent']) {
            echo "  [OK] {$inst['instances_name']}: {$detail['doc_number']} an {$detail['email']} versendet\n";
        } else {
            $err = $detail['error'] ?? $detail['message'] ?? 'Unbekannter Fehler';
            echo "  [!!] {$inst['instances_name']}: {$detail['doc_number']} Versand fehlgeschlagen: {$err}\n";
        }
    }

    // Log cron run
    $elapsed = round(microtime(true) - $startTime, 3);
    $DBLIB->insert('cron_runs', [
        'instances_id' => $instanceId,
        'job_type' => 'auto_invoice_email',
        'status' => 'success',
        'items_processed' => $results['sent'] + $results['failed'],
        'details_json' => json_encode([
            'sent' => $results['sent'],
            'failed' => $results['failed'],
            'elapsed_seconds' => $elapsed,
        ]),
        'completed_at' => date('Y-m-d H:i:s'),
    ]);
}

echo "[" . date('Y-m-d H:i:s') . "] Done. Sent {$totalSent}, failed {$totalFailed}.\n";
