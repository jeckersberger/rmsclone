<?php
/**
 * Cron-Job: GoBD-konforme Archivierung und Aufbewahrungsfristen
 *
 * Aufruf: php src/cron/gobd-archive-check.php
 * Empfohlener Crontab-Eintrag: 0 3 1 * * php /path/to/src/cron/gobd-archive-check.php >> /var/log/myrms-cron.log 2>&1
 *
 * Funktionen:
 *   1. retention_expires_at fuer neue Dokumente setzen (10 Jahre)
 *   2. Abgelaufene Aufbewahrungsfristen markieren (NICHT loeschen - nur markieren)
 *   3. Luecken in Nummernkreisen pruefen und warnen
 *   4. Archivierungsbericht generieren
 *
 * WICHTIG: Dokumente werden NIEMALS automatisch geloescht!
 * Nach Ablauf der 10-Jahres-Frist wird nur der Status auf 'retention_expired' gesetzt.
 * Die tatsaechliche Loeschung erfordert eine manuelle Freigabe.
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('CLI only');
}

require_once __DIR__ . '/../common/head.php';
require_once __DIR__ . '/../services/SequenceService.php';

echo "[" . date('Y-m-d H:i:s') . "] GoBD archive check started\n";

$DBLIB->where('instances_deleted', 0);
$instances = $DBLIB->get('instances', null, ['instances_id', 'instances_name']);

$totalUpdated = 0;
$totalExpired = 0;
$totalGaps = 0;

foreach ($instances as $inst) {
    $instanceId = $inst['instances_id'];

    // 1. Aufbewahrungsfristen fuer neue Dokumente setzen
    $DBLIB->where('instances_id', $instanceId);
    $DBLIB->where('retention_expires_at IS NULL');
    $DBLIB->where('generated_at IS NOT NULL');
    $docsWithoutRetention = $DBLIB->get('document_exports') ?: [];

    foreach ($docsWithoutRetention as $doc) {
        $retentionDate = date('Y-m-d H:i:s', strtotime($doc['generated_at'] . ' +10 years'));
        $DBLIB->where('id', $doc['id']);
        $DBLIB->update('document_exports', [
            'retention_expires_at' => $retentionDate,
        ]);
        $totalUpdated++;
    }

    if (count($docsWithoutRetention) > 0) {
        echo "  [{$inst['instances_name']}] {$totalUpdated} Dokumente mit Aufbewahrungsfrist versehen\n";
    }

    // 2. Abgelaufene Fristen markieren (NICHT loeschen!)
    $DBLIB->where('instances_id', $instanceId);
    $DBLIB->where('archive_status', 'active');
    $DBLIB->where('retention_expires_at', date('Y-m-d H:i:s'), '<');
    $DBLIB->where('retention_expires_at IS NOT NULL');
    $expiredDocs = $DBLIB->get('document_exports') ?: [];

    foreach ($expiredDocs as $doc) {
        $DBLIB->where('id', $doc['id']);
        $DBLIB->update('document_exports', [
            'archive_status' => 'retention_expired',
            'archived_at' => date('Y-m-d H:i:s'),
        ]);
        $totalExpired++;
        echo "  [{$inst['instances_name']}] Aufbewahrungsfrist abgelaufen: {$doc['doc_number']} (erstellt: " . date('d.m.Y', strtotime($doc['generated_at'])) . ")\n";
    }

    // 3. Luecken in Nummernkreisen pruefen
    foreach (['invoice', 'quote', 'delivery_note'] as $seqType) {
        $gaps = SequenceService::findGaps($DBLIB, $instanceId, $seqType);
        if (!empty($gaps)) {
            $totalGaps += count($gaps);
            echo "  [WARNUNG] [{$inst['instances_name']}] Luecken in {$seqType}-Nummernkreis: " . implode(', ', array_slice($gaps, 0, 10));
            if (count($gaps) > 10) echo " ... (" . count($gaps) . " gesamt)";
            echo "\n";
        }
    }

    // 4. Cron-Run protokollieren
    $DBLIB->insert('cron_runs', [
        'instances_id' => $instanceId,
        'job_type' => 'gobd_archive_check',
        'status' => 'success',
        'items_processed' => $totalUpdated + $totalExpired,
        'details_json' => json_encode([
            'retention_set' => count($docsWithoutRetention),
            'retention_expired' => count($expiredDocs),
            'gaps_found' => $totalGaps,
        ]),
        'completed_at' => date('Y-m-d H:i:s'),
    ]);
}

echo "[" . date('Y-m-d H:i:s') . "] Done. Updated {$totalUpdated}, expired {$totalExpired}, gaps {$totalGaps}\n";
