<?php
/**
 * Backup Health Status (for dashboard widget)
 *
 * GET - Returns health metrics for backup system
 */
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->instancePermissionCheck("BACKUP:VIEW")) {
    finish(false, ["code" => "PERMISSIONS"]);
}

require_once __DIR__ . '/../../services/BackupService.php';
$service = new BackupService($DBLIB);
$instanceId = (int)$AUTH->data['instance']['instances_id'];

try {
    $health = $service->getBackupHealth($instanceId);
    finish(true, null, $health);

} catch (Exception $e) {
    finish(false, ["message" => $e->getMessage()]);
}
