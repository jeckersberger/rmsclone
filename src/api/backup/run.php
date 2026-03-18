<?php
/**
 * Trigger Manual Backup
 *
 * POST {
 *   "config_id": int (optional, uses first config if not specified)
 * }
 */
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->instancePermissionCheck("BACKUP:MANAGE")) {
    finish(false, ["code" => "PERMISSIONS"]);
}

require_once __DIR__ . '/../../services/BackupService.php';
$service = new BackupService($DBLIB);
$instanceId = (int)$AUTH->data['instance']['instances_id'];

try {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];

    // Get config ID
    $configId = null;
    if (!empty($data['config_id'])) {
        $configId = (int)$data['config_id'];
    } else {
        // Use first active config
        $configs = $service->getConfigs($instanceId);
        if (empty($configs)) {
            finish(false, ["message" => "No backup configuration found"]);
        }
        $configId = (int)$configs[0]['id'];
    }

    // Run backup
    $jobId = $service->runBackup($configId, 'manual');

    finish(true, null, ['job_id' => $jobId]);

} catch (Exception $e) {
    finish(false, ["message" => $e->getMessage()]);
}
