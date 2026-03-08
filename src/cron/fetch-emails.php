<?php
/**
 * Cron-Job: E-Mails ueber IMAP abrufen (pro Instance/Firma)
 *
 * Aufruf: php src/cron/fetch-emails.php
 * Empfohlener Crontab-Eintrag: every 5 min - php /path/to/src/cron/fetch-emails.php >> /var/log/adamrms-cron.log 2>&1
 *
 * Funktionen:
 *   1. Alle Instanzen mit aktiviertem IMAP durchgehen
 *   2. Neue E-Mails abrufen und in DB speichern
 *   3. Anhaenge lokal speichern
 *   4. Kunden automatisch zuordnen (anhand Absender)
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('CLI only');
}

require_once __DIR__ . '/../common/head.php';
require_once __DIR__ . '/../services/ImapMailService.php';

echo "[" . date('Y-m-d H:i:s') . "] IMAP email fetch started\n";

// Alle aktiven Instanzen laden
$DBLIB->where('instances_deleted', 0);
$instances = $DBLIB->get('instances', null, ['instances_id', 'instances_name']);

$totalFetched = 0;
$totalErrors = 0;

foreach ($instances as $inst) {
    $instanceId = (int)$inst['instances_id'];

    // IMAP-Konfiguration fuer diese Instance laden
    $DBLIB->where('instances_id', $instanceId);
    $DBLIB->where('config_key', 'IMAP_ENABLED');
    $imapEnabled = $DBLIB->getOne('config', ['config_value']);

    if (!$imapEnabled || $imapEnabled['config_value'] !== 'Enabled') {
        continue;
    }

    // Alle IMAP-Configs laden
    $configKeys = ['IMAP_SERVER', 'IMAP_PORT', 'IMAP_ENCRYPTION', 'IMAP_USERNAME', 'IMAP_PASSWORD', 'IMAP_FOLDER', 'IMAP_PROCESS_ATTACHMENTS'];
    $imapConfig = [];

    foreach ($configKeys as $key) {
        $DBLIB->where('instances_id', $instanceId);
        $DBLIB->where('config_key', $key);
        $row = $DBLIB->getOne('config', ['config_value']);
        $imapConfig[$key] = $row ? $row['config_value'] : null;
    }

    // Pflichtfelder pruefen
    if (empty($imapConfig['IMAP_SERVER']) || empty($imapConfig['IMAP_USERNAME']) || empty($imapConfig['IMAP_PASSWORD'])) {
        echo "  [!] {$inst['instances_name']}: IMAP nicht vollstaendig konfiguriert, uebersprungen\n";
        continue;
    }

    $startTime = microtime(true);
    echo "  [*] {$inst['instances_name']}: IMAP-Abruf gestartet...\n";

    $imapService = new ImapMailService($DBLIB, $instanceId);

    $connected = $imapService->connect(
        $imapConfig['IMAP_SERVER'],
        (int)($imapConfig['IMAP_PORT'] ?: 993),
        $imapConfig['IMAP_ENCRYPTION'] ?: 'SSL',
        $imapConfig['IMAP_USERNAME'],
        $imapConfig['IMAP_PASSWORD'],
        $imapConfig['IMAP_FOLDER'] ?: 'INBOX'
    );

    if (!$connected) {
        echo "  [!] {$inst['instances_name']}: IMAP-Verbindung fehlgeschlagen\n";
        $totalErrors++;

        $DBLIB->insert('cron_runs', [
            'instances_id' => $instanceId,
            'job_type' => 'email_fetch',
            'status' => 'error',
            'items_processed' => 0,
            'details_json' => json_encode(['error' => 'IMAP connection failed']),
            'completed_at' => date('Y-m-d H:i:s'),
        ]);
        continue;
    }

    $result = $imapService->fetchNewEmails(50);

    $elapsed = round(microtime(true) - $startTime, 3);
    $totalFetched += $result['fetched'];
    $totalErrors += $result['errors'];

    echo "  [+] {$inst['instances_name']}: {$result['fetched']} neue E-Mails, {$result['errors']} Fehler ({$elapsed}s)\n";

    if (!empty($result['messages'])) {
        foreach ($result['messages'] as $msg) {
            echo "      {$msg}\n";
        }
    }

    // Cron-Run protokollieren
    $DBLIB->insert('cron_runs', [
        'instances_id' => $instanceId,
        'job_type' => 'email_fetch',
        'status' => $result['errors'] > 0 ? 'partial' : 'success',
        'items_processed' => $result['fetched'],
        'details_json' => json_encode([
            'fetched' => $result['fetched'],
            'errors' => $result['errors'],
            'elapsed_seconds' => $elapsed,
            'messages' => $result['messages'],
        ]),
        'completed_at' => date('Y-m-d H:i:s'),
    ]);
}

echo "[" . date('Y-m-d H:i:s') . "] Done. Fetched {$totalFetched} emails, {$totalErrors} errors.\n";
