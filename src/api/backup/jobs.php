<?php
/**
 * Backup Jobs Listing
 *
 * GET ?status=pending|running|completed|failed|cancelled&limit=50
 */
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->instancePermissionCheck("BACKUP:VIEW")) {
    finish(false, ["code" => "PERMISSIONS"]);
}

require_once __DIR__ . '/../../services/BackupService.php';
$service = new BackupService($DBLIB);
$instanceId = (int)$AUTH->data['instance']['instances_id'];

try {
    $status = isset($_GET['status']) ? trim($_GET['status']) : null;
    $limit = isset($_GET['limit']) ? max(1, min(500, (int)$_GET['limit'])) : 50;

    $jobs = $service->getJobs($instanceId, $status, $limit);

    finish(true, null, ['jobs' => $jobs, 'count' => count($jobs)]);

} catch (Exception $e) {
    finish(false, ["message" => $e->getMessage()]);
}
