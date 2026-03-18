<?php
/**
 * Restore from Backup
 *
 * POST {
 *   "job_id": int (required),
 *   "confirm": true (required)
 * }
 */
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->instancePermissionCheck("BACKUP:RESTORE")) {
    finish(false, ["code" => "PERMISSIONS"]);
}

require_once __DIR__ . '/../../services/BackupService.php';
$service = new BackupService($DBLIB);
$instanceId = (int)$AUTH->data['instance']['instances_id'];
$userId = (int)$AUTH->data['users_userid'];

try {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];

    // Require explicit confirmation
    if (empty($data['confirm'])) {
        finish(false, ["message" => "Restore must be explicitly confirmed"]);
    }

    if (empty($data['job_id'])) {
        finish(false, ["message" => "job_id is required"]);
    }

    $jobId = (int)$data['job_id'];

    // Verify job belongs to this instance
    $job = $service->getJob($jobId);
    if ($job['instances_id'] !== $instanceId) {
        finish(false, ["code" => "PERMISSIONS"]);
    }

    // Perform restore
    $restoreLogId = $service->restoreBackup($jobId, $userId);

    finish(true, null, ['restore_log_id' => $restoreLogId]);

} catch (Exception $e) {
    finish(false, ["message" => $e->getMessage()]);
}
