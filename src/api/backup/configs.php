<?php
/**
 * Backup Configuration Endpoints
 *
 * GET  - Retrieve all backup configurations
 * POST - Create or update backup configuration
 */
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->instancePermissionCheck("BACKUP:MANAGE")) {
    finish(false, ["code" => "PERMISSIONS"]);
}

require_once __DIR__ . '/../../services/BackupService.php';
$service = new BackupService($DBLIB);
$instanceId = (int)$AUTH->data['instance']['instances_id'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get all configurations
        $configs = $service->getConfigs($instanceId);
        finish(true, null, ['configs' => $configs]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Save configuration
        $data = json_decode(file_get_contents('php://input'), true) ?: [];

        $configId = $service->saveConfig($instanceId, $data);
        finish(true, null, ['config_id' => $configId]);

    } else {
        finish(false, ["code" => "METHOD_NOT_ALLOWED"]);
    }

} catch (Exception $e) {
    finish(false, ["message" => $e->getMessage()]);
}
