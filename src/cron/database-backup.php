<?php
/**
 * Automatische Datenbank-Backups (Cronjob)
 *
 * Erstellt taeglich einen mysqldump und verwaltet Retention-Policy.
 * Standard: 30 Tage Aufbewahrung, komprimiert mit gzip.
 *
 * Crontab: 0 2 * * * php /path/to/src/cron/database-backup.php
 *
 * Umgebungsvariablen:
 *   DB_HOST, DB_NAME, DB_USER, DB_PASS - Datenbankverbindung
 *   BACKUP_PATH - Verzeichnis fuer Backups (Standard: /data/backups/db)
 *   BACKUP_RETENTION_DAYS - Aufbewahrungsdauer (Standard: 30)
 */

$backupPath = getenv('BACKUP_PATH') ?: '/data/backups/db';
$retentionDays = (int)(getenv('BACKUP_RETENTION_DAYS') ?: 30);
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbName = getenv('DB_NAME') ?: '';
$dbUser = getenv('DB_USER') ?: '';
$dbPass = getenv('DB_PASS') ?: '';

if (empty($dbName) || empty($dbUser)) {
    error_log('[DB-Backup] DB_NAME und DB_USER muessen gesetzt sein.');
    exit(1);
}

// Backup-Verzeichnis erstellen
if (!is_dir($backupPath)) {
    mkdir($backupPath, 0700, true);
}

$timestamp = date('Y-m-d_His');
$filename = "backup_{$dbName}_{$timestamp}.sql.gz";
$filepath = "{$backupPath}/{$filename}";

// mysqldump ausfuehren
$passArg = !empty($dbPass) ? '--password=' . escapeshellarg($dbPass) : '';
$cmd = sprintf(
    'mysqldump --host=%s --user=%s %s --single-transaction --routines --triggers --events %s 2>&1 | gzip > %s',
    escapeshellarg($dbHost),
    escapeshellarg($dbUser),
    $passArg,
    escapeshellarg($dbName),
    escapeshellarg($filepath)
);

$output = [];
$returnCode = 0;
exec($cmd, $output, $returnCode);

if ($returnCode !== 0) {
    error_log('[DB-Backup] mysqldump fehlgeschlagen: ' . implode("\n", $output));
    // Leere/fehlerhafte Datei entfernen
    if (file_exists($filepath)) {
        unlink($filepath);
    }
    exit(1);
}

$filesize = filesize($filepath);
error_log(sprintf('[DB-Backup] Backup erstellt: %s (%s KB)', $filename, round($filesize / 1024)));

// Alte Backups loeschen (Retention-Policy)
$cutoff = time() - ($retentionDays * 86400);
$deleted = 0;
foreach (glob("{$backupPath}/backup_*.sql.gz") as $oldFile) {
    if (filemtime($oldFile) < $cutoff) {
        unlink($oldFile);
        $deleted++;
    }
}

if ($deleted > 0) {
    error_log("[DB-Backup] {$deleted} alte Backups geloescht (> {$retentionDays} Tage).");
}

echo "Backup completed: {$filename}\n";
