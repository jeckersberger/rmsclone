<?php
require_once __DIR__ . '/../apiHead.php';
/*
 * Local file server.
 *  Parameters
 *      f (required) - the file id as specified in the database
 *      d (optional, default false) - should a download be forced or should it be displayed in the browser? (if set it will download)
 *      r (optional, default false) - should the file be served directly or return a JSON response with the URL?
 *      key (optional) - share key for public file access
 */

$fileid = isset($_POST['f']) ? $_POST['f'] : (isset($_GET['f']) ? $_GET['f'] : null);
$forceDownload = isset($_POST['d']) || isset($_GET['d']);
$returnDirect = isset($_POST['r']) || isset($_GET['r']);
$shareKey = isset($_POST['key']) ? $_POST['key'] : (isset($_GET['key']) ? $_GET['key'] : null);

if (!$fileid) finish(false, ["message" => "File ID required"]);

// Access control check via s3URL (reuses existing permission logic)
$accessUrl = $bCMS->s3URL($fileid, $forceDownload, '+10 minutes', $shareKey);
if (!$accessUrl) finish(false, ["message" => "File not found - please check to ensure you are still logged in"]);

// Get local file path
$localPath = $bCMS->localFilePath($fileid);
if (!$localPath || !file_exists($localPath)) {
    finish(false, ["message" => "File not found on disk"]);
}

if ($returnDirect) {
    // Serve the file directly
    global $DBLIB;
    $DBLIB->where("s3files_id", intval($fileid));
    $fileInfo = $DBLIB->getone("s3files", ["s3files_name", "s3files_extension"]);

    $mimeTypes = [
        'pdf' => 'application/pdf',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'jfif' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'zip' => 'application/zip',
        'heic' => 'image/heic',
        'heif' => 'image/heif',
    ];

    $ext = strtolower($fileInfo['s3files_extension']);
    $contentType = isset($mimeTypes[$ext]) ? $mimeTypes[$ext] : 'application/octet-stream';
    $safeName = preg_replace('/[^A-Za-z0-9 _\-]/', '_', $fileInfo['s3files_name']) . '.' . $ext;
    $disposition = $forceDownload ? 'attachment' : 'inline';

    header('Content-Type: ' . $contentType);
    header('Content-Disposition: ' . $disposition . '; filename="' . $safeName . '"');
    header('Content-Length: ' . filesize($localPath));
    header('Cache-Control: private, max-age=3600');

    readfile($localPath);
    exit;
} else {
    finish(true, null, ["url" => $accessUrl]);
}

/** @OA\Post(
 *     path="/file/index.php", 
 *     summary="Get File", 
 *     description="Get a file
", 
 *     operationId="getFile", 
 *     tags={"file_uploads"}, 
 *     @OA\Response(
 *         response="200", 
 *         description="Success",
 *         @OA\MediaType(
 *             mediaType="application/json", 
 *             @OA\Schema( 
 *                 type="object", 
 *                 @OA\Property(
 *                     property="result", 
 *                     type="boolean", 
 *                     description="Whether the request was successful",
 *                 ),
 *             ),
 *         ),
 *     ), 
 *     @OA\Response(
 *         response="308", 
 *         description="Success - Redirect to this address",
 *     ), 
 *     @OA\Response(
 *         response="default", 
 *         description="Error",
 *         @OA\MediaType(
 *             mediaType="application/json", 
 *             @OA\Schema( 
 *                 type="object", 
 *                 @OA\Property(
 *                     property="result", 
 *                     type="boolean", 
 *                     description="Whether the request was successful",
 *                 ),
 *             ),
 *         ),
 *     ), 
 *     @OA\Parameter(
 *         name="f",
 *         in="query",
 *         description="The file id",
 *         required="true", 
 *         @OA\Schema(
 *             type="integer"), 
 *         ), 
 *     @OA\Parameter(
 *         name="d",
 *         in="query",
 *         description="should a download be forced or should it be displayed in the browser? (if set it will download)",
 *         required="false", 
 *         @OA\Schema(
 *             type="boolean"), 
 *         ), 
 *     @OA\Parameter(
 *         name="r",
 *         in="query",
 *         description="should the url be returned by the script as plain text or a redirect triggered? (if set it will redirect)",
 *         required="false", 
 *         @OA\Schema(
 *             type="boolean"), 
 *         ), 
 *     @OA\Parameter(
 *         name="e",
 *         in="query",
 *         description="when should the link expire? Must be a string describing how long in words basically. If this file type has security features then it will default to 1 minute.",
 *         required="false", 
 *         @OA\Schema(
 *             type="boolean"), 
 *         ), 
 * )
 */