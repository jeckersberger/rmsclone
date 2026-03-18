<?php
/**
 * Local file upload endpoint for Uppy XHRUpload plugin.
 * Receives file via multipart form upload, saves to local storage, records in DB.
 * Replaces the old S3 presigned URL flow.
 */
require_once __DIR__ . '/../apiHeadSecure.php';

if ($CONFIG['FILES_ENABLED'] !== "Enabled") {
    finish(false, ["code" => null, "message" => "File uploads are disabled"]);
}

$DBLIB->where("instances_id", $AUTH->data['instance']['instances_id']);
$storageCapacity = $DBLIB->getvalue("instances", "instances_storageLimit");
$storageUsed = $bCMS->s3StorageUsed($AUTH->data['instance']['instances_id']);
if ($storageCapacity > 0 and $storageCapacity < $storageUsed) {
    finish(false, ["code" => null, "message" => "Storage limit reached"]);
}

if (!isset($_FILES['file'])) {
    finish(false, ["code" => null, "message" => "No file uploaded"]);
}

$typeid = isset($_POST['typeid']) ? $bCMS->sanitizeString($_POST['typeid']) : null;
$subtype = isset($_POST['subtype']) && is_numeric($_POST['subtype']) ? $bCMS->sanitizeString($_POST['subtype']) : null;
$isPublic = isset($_POST['public']) ? $bCMS->sanitizeString($_POST['public']) : 0;
$type = isset($_POST['type']) ? $bCMS->sanitizeString($_POST['type']) : 'GENERAL';
$originalName = isset($_POST['originalName']) ? $bCMS->sanitizeString($_POST['originalName']) : $_FILES['file']['name'];

$extension = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));

// Block dangerous file types that could lead to RCE
$blockedExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'phar', 'cgi', 'pl', 'py', 'sh', 'bash', 'exe', 'bat', 'cmd', 'com', 'htaccess', 'htpasswd', 'shtml'];
if (in_array($extension, $blockedExtensions, true) || empty($extension)) {
    finish(false, ["code" => null, "message" => "File type not allowed"]);
}

// Max file size: 64MB (matches PHP config)
if ($_FILES['file']['size'] > 67108864) {
    finish(false, ["code" => null, "message" => "File too large (max 64MB)"]);
}

// Enhanced security: MIME type validation using finfo (not user-supplied type)
$finfo = new finfo(FILEINFO_MIME_TYPE);
$detectedMime = $finfo->file($_FILES['file']['tmp_name']);

// Block executable MIME types
$blockedMimes = ['application/x-httpd-php', 'application/x-php', 'text/x-php', 'application/x-executable', 'application/x-sharedlib'];
if (in_array($detectedMime, $blockedMimes, true)) {
    finish(false, ["code" => null, "message" => "File type not allowed"]);
}

// Check for embedded PHP code (web shell prevention)
$tempContent = file_get_contents($_FILES['file']['tmp_name'], false, null, 0, 8192);
if ($tempContent !== false && preg_match('/<\?php|<\?=/i', $tempContent)) {
    finish(false, ["code" => null, "message" => "File contains potentially dangerous code"]);
}

// Virus scan with ClamAV (if available)
require_once __DIR__ . '/../../services/VirusScanService.php';
$virusScanner = new VirusScanService();
$scanResult = $virusScanner->scan($_FILES['file']['tmp_name']);
if (!$scanResult['clean']) {
    finish(false, ["code" => null, "message" => "File rejected: virus detected (" . ($scanResult['threat'] ?? 'unknown') . ")"]);
}

$storagePath = "uploads/" . $type;
$filename = time() . "-" . random_int(1000000000, 9999999999) . "." . $extension;

// Store uploads outside webroot for security
$storageRoot = getenv('LOCAL_STORAGE_PATH') ?: '/data/uploads';
$fullDir = $storageRoot . "/" . $storagePath;
$fullPath = $fullDir . "/" . $filename;

if (!is_dir($fullDir)) {
    mkdir($fullDir, 0775, true);
}

if (!move_uploaded_file($_FILES['file']['tmp_name'], $fullPath)) {
    finish(false, ["code" => null, "message" => "Failed to save uploaded file"]);
}

$fileData = [
    "s3files_extension" => $extension,
    "s3files_path" => $storagePath,
    "s3files_meta_size" => $_FILES['file']['size'],
    "s3files_meta_type" => $typeid,
    "s3files_meta_subType" => $subtype,
    "users_userid" => $AUTH->data['users_userid'],
    "s3files_original_name" => $originalName,
    "s3files_filename" => pathinfo($filename, PATHINFO_FILENAME),
    "s3files_name" => pathinfo($originalName, PATHINFO_FILENAME),
    "s3files_meta_public" => $isPublic,
    "instances_id" => $AUTH->data['instance']['instances_id']
];

// File encryption support (GoBD compliance)
$encryptionEnabled = getenv('FILE_ENCRYPTION_ENABLED') === 'true';
$integrityHash = '';
if ($encryptionEnabled) {
    require_once __DIR__ . '/../../services/FileEncryptionService.php';
    try {
        $encryptionKey = getenv('FILE_ENCRYPTION_KEY');
        if (!$encryptionKey) {
            finish(false, ["code" => null, "message" => "File encryption not properly configured"]);
        }

        $encryptionService = new FileEncryptionService($encryptionKey);

        // Calculate integrity hash before encryption
        $integrityHash = $encryptionService->calculateHash($fullPath);

        // Encrypt the file in place
        $encryptedPath = $fullPath . '.encrypted';
        if (!$encryptionService->encryptFile($fullPath, $encryptedPath)) {
            @unlink($fullPath);
            @unlink($encryptedPath);
            finish(false, ["code" => null, "message" => "File encryption failed"]);
        }

        // Replace original with encrypted version
        if (!unlink($fullPath) || !rename($encryptedPath, $fullPath)) {
            @unlink($encryptedPath);
            @unlink($fullPath);
            finish(false, ["code" => null, "message" => "Failed to finalize encrypted file"]);
        }

        // Store encryption metadata
        $fileData['s3files_encrypted'] = 1;
        $fileData['s3files_integrity_hash'] = $integrityHash;

    } catch (Exception $e) {
        error_log("Upload encryption error: " . $e->getMessage());
        @unlink($fullPath);
        finish(false, ["code" => null, "message" => "File encryption error: " . $e->getMessage()]);
    }
}

$id = $DBLIB->insert("s3files", $fileData);
if (!$id) {
    unlink($fullPath);
    finish(false, ["code" => null, "message" => "Database error"]);
}

finish(true, null, ["id" => $id, "url" => $CONFIG['ROOTURL'] . '/api/file/?f=' . $id]);
