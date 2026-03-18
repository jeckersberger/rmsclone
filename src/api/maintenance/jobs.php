<?php
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->instancePermissionCheck("MAINTENANCE:VIEW")) {
    http_response_code(403);
    die(json_encode(['error' => 'Keine Berechtigung']));
}

$maintenanceService = new MaintenanceService($DBLIB);

// GET: Jobs auflisten
$status = isset($_GET['status']) ? $_GET['status'] : null;
$priority = isset($_GET['priority']) ? $_GET['priority'] : null;
$assetId = isset($_GET['asset_id']) ? (int) $_GET['asset_id'] : null;

$jobs = $maintenanceService->getOpenJobs($AUTH->data['instance']['instances_id'], $priority);

// Filtern nach Asset falls angegeben
if ($assetId) {
    $jobs = array_filter($jobs, function ($job) use ($assetId) {
        return $job['asset_id'] == $assetId;
    });
}

// Filtern nach Status falls angegeben
if ($status) {
    $jobs = array_filter($jobs, function ($job) use ($status) {
        return $job['status'] == $status;
    });
}

header('Content-Type: application/json');
echo json_encode(['success' => true, 'jobs' => array_values($jobs)]);
?>
