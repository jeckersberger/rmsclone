<?php
require_once __DIR__ . '/../apiHeadSecure.php';
// S3 upload is disabled - all files are stored locally
// Use localUpload.php instead
finish(false, ["message" => "S3 uploads disabled. Use local upload endpoint."]);
