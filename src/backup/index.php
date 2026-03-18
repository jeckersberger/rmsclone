<?php
/**
 * Backup & Disaster Recovery Dashboard
 */
require_once __DIR__ . '/../common/headSecure.php';

if (!$AUTH->instancePermissionCheck("BACKUP:VIEW")) {
    die($TWIG->render('404.twig', $PAGEDATA));
}

require_once __DIR__ . '/../services/BackupService.php';
$service = new BackupService($DBLIB);
$instanceId = (int)$AUTH->data['instance']['instances_id'];

$PAGEDATA['pageConfig'] = [
    "TITLE" => "Backup & Disaster Recovery",
    "BREADCRUMB" => false
];

try {
    // Get backup health and statistics
    $PAGEDATA['health'] = $service->getBackupHealth($instanceId);

    // Get recent backup jobs
    $PAGEDATA['recent_jobs'] = $service->getJobs($instanceId, null, 20);

    // Get all backup configurations
    $PAGEDATA['configs'] = $service->getConfigs($instanceId);

    // Get available backups for restore
    $PAGEDATA['available_backups'] = $service->listAvailableBackups($instanceId);

    // Get storage usage
    $PAGEDATA['storage'] = $service->calculateStorageUsed($instanceId);

    // Pass permission info
    $PAGEDATA['canManage'] = $AUTH->instancePermissionCheck("BACKUP:MANAGE");
    $PAGEDATA['canRestore'] = $AUTH->instancePermissionCheck("BACKUP:RESTORE");

    echo $TWIG->render('backup/backup_index.twig', $PAGEDATA);

} catch (Exception $e) {
    $PAGEDATA['error'] = $e->getMessage();
    echo $TWIG->render('backup/backup_index.twig', $PAGEDATA);
}
