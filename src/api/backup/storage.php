<?php
/**
 * Backup Storage Usage Statistics
 *
 * GET - Returns storage consumption data
 */
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->instancePermissionCheck("BACKUP:VIEW")) {
    finish(false, ["code" => "PERMISSIONS"]);
}

require_once __DIR__ . '/../../services/BackupService.php';
$service = new BackupService($DBLIB);
$instanceId = (int)$AUTH->data['instance']['instances_id'];

try {
    $storageData = $service->calculateStorageUsed($instanceId);
    finish(true, null, $storageData);

} catch (Exception $e) {
    finish(false, ["message" => $e->getMessage()]);
}
