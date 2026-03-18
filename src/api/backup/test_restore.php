<?php
/**
 * Test Restore Integrity
 *
 * POST {
 *   "job_id": int (required)
 * }
 *
 * Validates backup file integrity without restoring to live database
 */
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->instancePermissionCheck("BACKUP:VIEW")) {
    finish(false, ["code" => "PERMISSIONS"]);
}

require_once __DIR__ . '/../../services/BackupService.php';
$service = new BackupService($DBLIB);
$instanceId = (int)$AUTH->data['instance']['instances_id'];

try {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];

    if (empty($data['job_id'])) {
        finish(false, ["message" => "job_id is required"]);
    }

    $jobId = (int)$data['job_id'];

    // Verify job belongs to this instance
    $job = $service->getJob($jobId);
    if ($job['instances_id'] !== $instanceId) {
        finish(false, ["code" => "PERMISSIONS"]);
    }

    // Test restore
    $isValid = $service->testRestore($jobId);

    finish(true, null, [
        'valid' => $isValid,
        'message' => $isValid ? 'Backup is valid and restorable' : 'Backup validation failed'
    ]);

} catch (Exception $e) {
    finish(false, ["message" => $e->getMessage()]);
}
