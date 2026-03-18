<?php
/**
 * Backup CRON Script
 *
 * Called by system crontab to process scheduled backups
 * Add to crontab: * * * * * /usr/bin/php /path/to/scripts/backup_cron.php >> /var/log/myrms_backup.log 2>&1
 *
 * Or less frequently (every 6 hours):
 * 0 */6 * * * /usr/bin/php /path/to/scripts/backup_cron.php >> /var/log/myrms_backup.log 2>&1
 */

// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set long timeout for backup operations
set_time_limit(3600); // 1 hour

// Bootstrap application
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/common/head.php';

// Import BackupService
require_once __DIR__ . '/../src/services/BackupService.php';

try {
    // Log start
    error_log('[' . date('Y-m-d H:i:s') . '] Backup CRON started');

    // Initialize service
    $backupService = new BackupService($DBLIB);

    // Process scheduled backups
    $backupService->processCronBackups();

    // Log completion
    error_log('[' . date('Y-m-d H:i:s') . '] Backup CRON completed successfully');

} catch (Exception $e) {
    error_log('[' . date('Y-m-d H:i:s') . '] Backup CRON error: ' . $e->getMessage());
    exit(1);
}

exit(0);
