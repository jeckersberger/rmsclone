<?php
require_once __DIR__ . '/../apiHeadSecure.php';

if (!$AUTH->instancePermissionCheck("MAINTENANCE:EDIT")) {
    http_response_code(403);
    die(json_encode(['error' => 'Keine Berechtigung']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Nur POST erlaubt']));
}

$jobId = isset($_POST['job_id']) ? (int) $_POST['job_id'] : null;
$description = isset($_POST['description']) ? $_POST['description'] : null;

if (!$jobId) {
    http_response_code(400);
    die(json_encode(['error' => 'job_id erforderlich']));
}

if (!isset($_FILES['photo'])) {
    http_response_code(400);
    die(json_encode(['error' => 'Datei erforderlich']));
}

$maintenanceService = new MaintenanceService($DBLIB);

try {
    // Datei verarbeiten (vereinfacht - würde normalerweise zu S3 gehen)
    $file = $_FILES['photo'];
    $filePath = 'maintenance/job_' . $jobId . '_' . time() . '.jpg';

    // In Production würde dies S3 verwenden
    // $s3Path = $bCMS->s3Upload($file, $filePath);

    // Für Demo: lokaler Pfad
    $photoId = $maintenanceService->addJobPhoto($jobId, $filePath, $description, $AUTH->data['user']['users_userid']);

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'photo_id' => $photoId]);
} catch (Exception $e) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
?>
