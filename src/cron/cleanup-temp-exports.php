<?php
/**
 * Automatische Loeschung temporaerer Export-Dateien (Cronjob)
 *
 * Loescht DSGVO-Exports, PDF-Previews und andere temporaere Dateien
 * die aelter als 1 Stunde sind.
 *
 * Crontab: */15 * * * * php /path/to/src/cron/cleanup-temp-exports.php
 */

$tempDirs = [
    sys_get_temp_dir() . '/dsgvo_exports',
    sys_get_temp_dir() . '/pdf_previews',
    sys_get_temp_dir() . '/report_exports',
];

$maxAge = 3600; // 1 Stunde
$cutoff = time() - $maxAge;
$totalDeleted = 0;

foreach ($tempDirs as $dir) {
    if (!is_dir($dir)) {
        continue;
    }

    $files = glob($dir . '/*');
    if (!$files) continue;

    foreach ($files as $file) {
        if (is_file($file) && filemtime($file) < $cutoff) {
            unlink($file);
            $totalDeleted++;
        }
    }

    // Leeres Verzeichnis aufraemen
    $remaining = glob($dir . '/*');
    if (empty($remaining)) {
        rmdir($dir);
    }
}

if ($totalDeleted > 0) {
    error_log("[Cleanup] {$totalDeleted} temporaere Export-Dateien geloescht.");
}
