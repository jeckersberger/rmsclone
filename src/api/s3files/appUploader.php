<?php
//Very similar code in uploader for PDF invoices from projects
require_once __DIR__ . '/../apiHeadSecure.php';
if ($CONFIG['FILES_ENABLED'] !== "Enabled") {
    finish(false, ["code" => null, "message" => "File uploads are disabled"]);
}
if(isset($_FILES['file'])) {
    $temp_file_location = $_FILES['file']['tmp_name'];
    $extension = strtolower(pathinfo($_POST['filename'], PATHINFO_EXTENSION));

    // Block dangerous file types
    $blockedExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'phar', 'cgi', 'pl', 'py', 'sh', 'bash', 'exe', 'bat', 'cmd', 'com', 'htaccess', 'htpasswd', 'shtml'];
    if (in_array($extension, $blockedExtensions, true) || empty($extension)) {
        finish(false, ["code" => null, "message" => "File type not allowed"]);
    }

    // Max file size: 64MB
    if ($_FILES['file']['size'] > 67108864) {
        finish(false, ["code" => null, "message" => "File too large (max 64MB)"]);
    }

    // Sanitize typename to prevent path traversal
    $typename = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['typename'] ?? 'GENERAL');
    $storagePath = "uploads/" . $typename;
    $filename = time() . "-" . random_int(1000000000, 9999999999) . "." . $extension;

    $storageRoot = getenv('LOCAL_STORAGE_PATH') ?: '/var/www/html/storage';
    $fullDir = $storageRoot . "/" . $storagePath;
    $fullPath = $fullDir . "/" . $filename;

    if (!is_dir($fullDir)) {
        mkdir($fullDir, 0775, true);
    }

    if (!move_uploaded_file($temp_file_location, $fullPath)) {
        finish(false, ["code" => null, "message" => "Failed to save file"]);
    }

    $fileData = [
        "s3files_extension" => $extension,
        "s3files_path" => $storagePath,
        "s3files_meta_size" => $_FILES['file']['size'],
        "s3files_meta_type" => $_POST['typeid'],
        "s3files_meta_subType" => is_numeric($_POST['subtype']) ? $bCMS->sanitizeString($_POST['subtype']) : null,
        "users_userid" => $AUTH->data['users_userid'],
        "s3files_original_name" => $bCMS->sanitizeString($_POST['filename']),
        "s3files_filename" => pathinfo($filename, PATHINFO_FILENAME),
        "s3files_name" => pathinfo($bCMS->sanitizeString($_POST['filename']), PATHINFO_FILENAME),
        "s3files_meta_public" => $bCMS->sanitizeString($_POST['public']),
        "instances_id" => $AUTH->data['instance']['instances_id']
    ];
    $id = $DBLIB->insert("s3files",$fileData);
    if (!$id) finish(false, ["code" => null, "message" => "Error"]);
    else finish(true, null, ["id" => $id, "resize" => false,"url" => $CONFIG['ROOTURL'] . '/api/file/?f=' . $id]);
}

/** @OA\Post(
 *     path="/s3files/appUploader.php", 
 *     summary="App Uploader", 
 *     description="Upload a file to S3
", 
 *     operationId="appUploader", 
 *     tags={"s3files"}, 
 *     @OA\Response(
 *         response="200", 
 *         description="Success",
 *     ), 
 *     @OA\Parameter(
 *         name="filename",
 *         in="query",
 *         description="File Name",
 *         required="true", 
 *         @OA\Schema(
 *             type="string"), 
 *         ), 
 *     @OA\Parameter(
 *         name="typename",
 *         in="query",
 *         description="File Type",
 *         required="true", 
 *         @OA\Schema(
 *             type="string"), 
 *         ), 
 *     @OA\Parameter(
 *         name="typeid",
 *         in="query",
 *         description="File Type ID",
 *         required="true", 
 *         @OA\Schema(
 *             type="number"), 
 *         ), 
 *     @OA\Parameter(
 *         name="subtype",
 *         in="query",
 *         description="File Subtype",
 *         required="false", 
 *         @OA\Schema(
 *             type="number"), 
 *         ), 
 *     @OA\Parameter(
 *         name="public",
 *         in="query",
 *         description="Public File",
 *         required="true", 
 *         @OA\Schema(
 *             type="boolean"), 
 *         ), 
 *     @OA\Parameter(
 *         name="file",
 *         in="files",
 *         description="File",
 *         required="true", 
 *         ), 
 * )
 */