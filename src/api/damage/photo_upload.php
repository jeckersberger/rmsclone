<?php
/**
 * POST /api/damage/photo_upload.php
 * Upload damage photo documentation
 */
require_once __DIR__ . '/../apiHeadSecure.php';
require_once __DIR__ . '/../../services/DamageWorkflowService.php';

if (!$AUTH->instancePermissionCheck("DAMAGE:EDIT")) {
    finish(false, ["message" => "Permission denied"]);
}

$damageReportId = intval($_POST['damage_report_id'] ?? 0);
$photoType = $_POST['photo_type'] ?? 'initial';
$description = $_POST['description'] ?? null;

if (!$damageReportId) {
    finish(false, ["message" => "damage_report_id required"]);
}

// Validate photo type
if (!in_array($photoType, ['initial', 'during_repair', 'after_repair'])) {
    finish(false, ["message" => "Invalid photo_type"]);
}

// Verify report belongs to instance
$DBLIB->where('id', $damageReportId);
$DBLIB->where('instances_id', $AUTH->data['instance']['instances_id']);
$report = $DBLIB->getOne('damage_reports', ['id']);
if (!$report) {
    finish(false, ["code" => "NOT_FOUND"]);
}

// Handle file upload
if (!isset($_FILES['photo']) || $_FILES['photo']['error'] != UPLOAD_ERR_OK) {
    finish(false, ["message" => "No file provided or upload error"]);
}

$file = $_FILES['photo'];
$uploadDir = __DIR__ . '/../../static-assets/damage_photos/' . date('Y/m');
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$fileName = uniqid('damage_') . '_' . time() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
$filePath = 'damage_photos/' . date('Y/m') . '/' . $fileName;
$fullPath = __DIR__ . '/../../static-assets/' . $filePath;

if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
    finish(false, ["message" => "Failed to save file"]);
}

$service = new DamageWorkflowService($DBLIB);
$photoId = $service->addPhoto(
    $damageReportId,
    $filePath,
    $photoType,
    $description,
    $AUTH->data['user']['users_userid']
);

if (!$photoId) {
    unlink($fullPath);
    finish(false, ["message" => "Failed to save photo record"]);
}

finish(true, null, ['photo_id' => $photoId, 'file_path' => $filePath]);
