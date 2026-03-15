<?php
require_once __DIR__ . '/../apiHeadSecure.php';
if (!$AUTH->instancePermissionCheck("PROJECTS:VIEW") or !isset($_POST['id']) or $CONFIG['FILES_ENABLED'] !== "Enabled") finish(false);

if (isset($_FILES['file'])) {
    $temp_file_location = $_FILES['file']['tmp_name'];
    $filenamePrefix = "invoice";
    $storagePath = "uploads/PROJECT_INVOICES";
    $fileType = 20;
    $originalName = "invoice.pdf";

    switch ($_POST['type']) {
        case 'quote':
            $filenamePrefix = "quote";
            $storagePath = "uploads/PROJECT_QUOTES";
            $fileType = 21;
            $originalName = "quote.pdf";
            break;
        case 'deliveryNote':
            $filenamePrefix = "delivery-note";
            $storagePath = "uploads/PROJECT_DELIVERY_NOTES";
            $fileType = 22;
            $originalName = "delivery-note.pdf";
            break;
        default:
            break;
    }

    $filename = sprintf("%s-", $filenamePrefix) . time() . "-" . random_int(1000000000, 9999999999) . ".pdf";

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
        "s3files_extension" => "pdf",
        "s3files_path" => $storagePath,
        "s3files_meta_size" => $_FILES['file']['size'],
        "s3files_meta_type" => $fileType,
        "s3files_meta_subType" => $_POST['id'],
        "users_userid" => $AUTH->data['users_userid'],
        "s3files_original_name" => $originalName,
        "s3files_filename" => pathinfo($filename, PATHINFO_FILENAME),
        "s3files_name" => "v" . $bCMS->sanitizeString($_POST['fileNumber']),
        "s3files_meta_public" => 0,
        "instances_id" => $AUTH->data['instance']['instances_id']
    ];
    $id = $DBLIB->insert("s3files", $fileData);
    if (!$id) finish(false, ["code" => null, "message" => "Error"]);
    else finish(true, null, ["id" => $id, "resize" => false, "url" => $CONFIG['ROOTURL'] . '/api/file/?r=true&f=' . $id]);
}
finish(false, ["code" => null, "message" => "HTTP Upload Error"]);
/** @OA\Post(
 *     path="/s3files/uploadProjectInvoice.php", 
 *     summary="Upload Project Invoice", 
 *     description="Upload a project invoice  
Requires Instance Permission PROJECTS:VIEW
", 
 *     operationId="uploadProjectInvoice", 
 *     tags={"s3files"}, 
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
 *     @OA\Parameter(
 *         name="id",
 *         in="query",
 *         description="Project ID",
 *         required="true", 
 *         @OA\Schema(
 *             type="number"), 
 *         ), 
 *     @OA\Parameter(
 *         name="type",
 *         in="query",
 *         description="What type of file is this - invoice, quote or delivery note?",
 *         required="true", 
 *         @OA\Schema(
 *             type="string"), 
 *         ), 
 *     @OA\Parameter(
 *         name="file",
 *         in="files",
 *         description="File",
 *         required="true", 
 *         ), 
 *     @OA\Parameter(
 *         name="fileNumber",
 *         in="query",
 *         description="File Version Number",
 *         required="true", 
 *         @OA\Schema(
 *             type="number"), 
 *         ), 
 * )
 */
